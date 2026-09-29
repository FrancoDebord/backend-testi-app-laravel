<?php

namespace Tests\Feature;

use App\Models\LiveSession;
use App\Models\LiveSpeaker;
use App\Models\User;
use App\Services\LiveKit\LiveKitClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Intervenants d'un direct : file, invitation, antenne, une personne à la fois. docs/fonctionnalites/lives-intervenants.md */
class LiveStageTest extends TestCase
{
    use RefreshDatabase;

    private User $moderator;
    private User $alice;
    private User $bruno;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'livekit.url'        => 'wss://lk.test',
            'livekit.api_key'    => 'devkey',
            'livekit.api_secret' => 'secret-de-test-suffisamment-long',
        ]);
        $this->app->forgetInstance(LiveKitClient::class);
        Http::fake(['lk.test/*' => Http::response(['participants' => []], 200)]);

        $this->moderator = $this->makeUser('moderateur', 'Modératrice');
        $this->alice     = $this->makeUser('utilisateur', 'Alice');
        $this->bruno     = $this->makeUser('utilisateur', 'Bruno');
    }

    private function makeUser(string $role, string $name): User
    {
        return User::create([
            'display_name' => $name, 'email' => strtolower($name) . '@example.com',
            'password' => 'secret123', 'role' => $role, 'status' => 'active',
        ]);
    }

    private function onAir(): LiveSession
    {
        $this->actingAs($this->moderator)->postJson('/api/v1/lives', ['title' => 'Grande soirée de témoignages'])->assertCreated();
        $live = LiveSession::active()->latest()->firstOrFail();
        $this->actingAs($this->moderator)->postJson("/lives/{$live->id}/go-live")->assertOk();

        return $live->fresh();
    }

    private function guest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    private function url(LiveSession $live, string $path = ''): string
    {
        return "/api/v1/lives/{$live->id}/stage{$path}";
    }

    private function requestAs(User $user, LiveSession $live, ?string $message = null)
    {
        return $this->actingAs($user)->postJson($this->url($live, '/requests'), array_filter(['message' => $message]));
    }

    private function speakerOf(User $user, LiveSession $live): LiveSpeaker
    {
        return LiveSpeaker::where('live_session_id', $live->id)->where('user_id', $user->id)->latest()->firstOrFail();
    }

    /** Appels UpdateParticipant envoyés à LiveKit, décodés. */
    private function permissionUpdates(): array
    {
        return Http::recorded()
            ->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/twirp/livekit.RoomService/UpdateParticipant'))
            ->map(fn ($pair) => $pair[0]->data())
            ->values()->all();
    }

    private function stageBroadcasts(): array
    {
        return Http::recorded()
            ->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/SendData'))
            ->map(fn ($pair) => json_decode(base64_decode($pair[0]->data()['data']), true))
            ->filter(fn ($m) => ($m['type'] ?? null) === 'stage')
            ->values()->all();
    }

    private function bringOnStage(User $user, LiveSession $live): LiveSpeaker
    {
        $this->requestAs($user, $live)->assertCreated();
        $speaker = $this->speakerOf($user, $live);
        $this->actingAs($this->moderator)->postJson($this->url($live, "/{$speaker->id}/invite"))->assertOk();
        $this->actingAs($user)->postJson($this->url($live, '/accept'), ['identity' => "user-{$user->id}-abcd1234", 'camera' => true])->assertOk();

        return $speaker->fresh();
    }

    // ─── Demandes ──────────────────────────────────────────────────────────

    public function test_a_connected_viewer_can_ask_to_speak_once(): void
    {
        $live = $this->onAir();

        $this->guest()->postJson($this->url($live, '/requests'))->assertUnauthorized();

        $this->requestAs($this->alice, $live, 'Guérison de ma mère')
            ->assertCreated()
            ->assertJsonPath('data.mine.status', 'waiting')
            ->assertJsonPath('data.mine.position', 1)
            ->assertJsonPath('data.mine.message', 'Guérison de ma mère');

        $this->requestAs($this->alice, $live)->assertStatus(409);
        $this->requestAs($this->moderator, $live)->assertForbidden();
        $this->requestAs($this->bruno, $live, str_repeat('a', 201))->assertStatus(422);

        $this->assertSame('requested', $this->stageBroadcasts()[0]['event']);
        $this->assertArrayNotHasKey('message', $this->stageBroadcasts()[0]['speaker'], 'Le sujet ne part jamais à tous les spectateurs.');
    }

    public function test_requests_need_an_open_live_and_open_requests(): void
    {
        $this->actingAs($this->moderator)->postJson('/api/v1/lives', ['title' => 'En préparation'])->assertCreated();
        $preparing = LiveSession::latest()->first();
        // En préparation, le direct est introuvable pour le public.
        $this->requestAs($this->alice, $preparing)->assertNotFound();
        $this->actingAs($this->moderator)->postJson("/api/v1/lives/{$preparing->id}/end")->assertOk();

        $live = $this->onAir();
        $this->actingAs($this->alice)->postJson($this->url($live, '/settings'), ['enabled' => false])->assertForbidden();
        $this->actingAs($this->moderator)->postJson($this->url($live, '/settings'), ['enabled' => false])->assertOk()
            ->assertJsonPath('data.enabled', false);
        $this->requestAs($this->alice, $live)->assertForbidden();

        $this->actingAs($this->moderator)->postJson($this->url($live, '/settings'), ['enabled' => true])->assertOk();
        $this->requestAs($this->alice, $live)->assertCreated();
    }

    public function test_queue_details_are_for_staff_only(): void
    {
        $live = $this->onAir();
        $this->requestAs($this->alice, $live, 'Mon sujet')->assertCreated();
        $this->requestAs($this->bruno, $live, 'Autre sujet')->assertCreated();

        $this->guest()->getJson($this->url($live))->assertOk()
            ->assertJsonPath('data.queueCount', 2)
            ->assertJsonPath('data.queue', null)
            ->assertJsonPath('data.canRequest', false);

        $this->actingAs($this->bruno)->getJson($this->url($live))->assertOk()
            ->assertJsonPath('data.queue', null)
            ->assertJsonPath('data.mine.position', 2);

        $this->actingAs($this->moderator)->getJson($this->url($live))->assertOk()
            ->assertJsonPath('data.queue.0.user.displayName', 'Alice')
            ->assertJsonPath('data.queue.0.message', 'Mon sujet')
            ->assertJsonPath('data.queue.1.position', 2);

        $this->getJson("/api/v1/lives/{$live->id}/stats")->assertOk()->assertJsonPath('data.stage.queueCount', 2);
    }

    public function test_viewer_can_withdraw_a_waiting_request(): void
    {
        $live = $this->onAir();
        $this->requestAs($this->alice, $live)->assertCreated();

        $this->actingAs($this->alice)->deleteJson($this->url($live, '/requests/mine'))->assertOk()
            ->assertJsonPath('data.mine', null)
            ->assertJsonPath('data.queueCount', 0);
        $this->assertSame(LiveSpeaker::CANCELLED, $this->speakerOf($this->alice, $live)->status);
    }

    // ─── Invitation et antenne ─────────────────────────────────────────────

    public function test_only_one_person_can_be_invited_or_on_stage_at_a_time(): void
    {
        $live = $this->onAir();
        $this->requestAs($this->alice, $live)->assertCreated();
        $this->requestAs($this->bruno, $live)->assertCreated();
        $alice = $this->speakerOf($this->alice, $live);
        $bruno = $this->speakerOf($this->bruno, $live);

        $this->actingAs($this->bruno)->postJson($this->url($live, "/{$alice->id}/invite"))->assertForbidden();
        $this->actingAs($this->moderator)->postJson($this->url($live, "/{$alice->id}/invite"))->assertOk()
            ->assertJsonPath('data.current.status', 'invited');
        $this->actingAs($this->moderator)->postJson($this->url($live, "/{$bruno->id}/invite"))->assertStatus(409);

        // À l'antenne : toujours une seule place.
        $this->actingAs($this->alice)->postJson($this->url($live, '/accept'), ['identity' => "user-{$this->alice->id}-x1"])->assertOk();
        $this->actingAs($this->moderator)->postJson($this->url($live, "/{$bruno->id}/invite"))->assertStatus(409);

        // Fin de l'intervention : la personne suivante peut être invitée.
        $this->actingAs($this->moderator)->postJson($this->url($live, "/{$alice->id}/remove"))->assertOk();
        $this->actingAs($this->moderator)->postJson($this->url($live, "/{$bruno->id}/invite"))->assertOk()
            ->assertJsonPath('data.current.user.id', $this->bruno->id);
    }

    public function test_accepting_opens_microphone_and_camera_on_the_viewer_connection(): void
    {
        $live = $this->onAir();
        $this->requestAs($this->alice, $live)->assertCreated();
        $speaker = $this->speakerOf($this->alice, $live);

        // Pas d'invitation : refus.
        $this->actingAs($this->alice)->postJson($this->url($live, '/accept'), ['identity' => "user-{$this->alice->id}-x1"])->assertStatus(409);

        $this->actingAs($this->moderator)->postJson($this->url($live, "/{$speaker->id}/invite"))->assertOk();

        // La connexion doit être celle de la personne invitée.
        $this->actingAs($this->alice)->postJson($this->url($live, '/accept'), ['identity' => "user-{$this->bruno->id}-x1"])->assertStatus(422);
        $this->actingAs($this->alice)->postJson($this->url($live, '/accept'), ['identity' => "host-{$this->moderator->id}"])->assertStatus(422);

        $this->actingAs($this->alice)->postJson($this->url($live, '/accept'), ['identity' => "user-{$this->alice->id}-x1", 'camera' => true])
            ->assertOk()
            ->assertJsonPath('data.current.status', 'on_stage')
            ->assertJsonPath('data.current.camera', true);

        $update = $this->permissionUpdates()[0];
        $this->assertSame($live->room_name, $update['room']);
        $this->assertSame("user-{$this->alice->id}-x1", $update['identity']);
        $this->assertTrue($update['permission']['can_publish']);
        $this->assertSame(['CAMERA', 'MICROPHONE'], $update['permission']['can_publish_sources']);
        $this->assertFalse($update['permission']['can_publish_data']);
        $this->assertFalse($update['permission']['hidden']);

        $this->assertSame(['requested', 'invited', 'on_stage'], array_column($this->stageBroadcasts(), 'event'));
    }

    public function test_invitation_expires_after_the_delay(): void
    {
        $live = $this->onAir();
        $this->requestAs($this->alice, $live)->assertCreated();
        $speaker = $this->speakerOf($this->alice, $live);
        $this->actingAs($this->moderator)->postJson($this->url($live, "/{$speaker->id}/invite"))->assertOk();

        $this->travel(config('livekit.stage_invite_timeout') + 5)->seconds();

        $this->actingAs($this->alice)->postJson($this->url($live, '/accept'), ['identity' => "user-{$this->alice->id}-x1"])->assertStatus(410);
        $this->assertSame(LiveSpeaker::EXPIRED, $speaker->fresh()->status);
        $this->actingAs($this->moderator)->getJson($this->url($live))->assertJsonPath('data.current', null);
    }

    public function test_staff_can_decline_a_request_or_cancel_an_invitation(): void
    {
        $live = $this->onAir();
        $this->requestAs($this->alice, $live)->assertCreated();
        $speaker = $this->speakerOf($this->alice, $live);

        $this->actingAs($this->bruno)->postJson($this->url($live, "/{$speaker->id}/decline"))->assertForbidden();
        $this->actingAs($this->moderator)->postJson($this->url($live, "/{$speaker->id}/decline"))->assertOk()
            ->assertJsonPath('data.queueCount', 0);
        $this->assertSame(LiveSpeaker::DECLINED, $speaker->fresh()->status);

        // La personne peut refaire une demande plus tard.
        $this->requestAs($this->alice, $live)->assertCreated();
    }

    public function test_leaving_or_being_removed_revokes_publishing(): void
    {
        $live = $this->onAir();
        $alice = $this->bringOnStage($this->alice, $live);

        $this->actingAs($this->alice)->deleteJson($this->url($live, '/requests/mine'))->assertOk()
            ->assertJsonPath('data.current', null);
        $this->assertSame(['done', 'left'], [$alice->fresh()->status, $alice->fresh()->ended_reason]);

        $revoke = collect($this->permissionUpdates())->last();
        $this->assertFalse($revoke['permission']['can_publish']);
        $this->assertTrue($revoke['permission']['hidden']);

        $bruno = $this->bringOnStage($this->bruno, $live);
        $this->actingAs($this->alice)->postJson($this->url($live, "/{$bruno->id}/remove"))->assertForbidden();
        $this->actingAs($this->moderator)->postJson($this->url($live, "/{$bruno->id}/remove"))->assertOk();
        $this->assertSame(['done', 'removed'], [$bruno->fresh()->status, $bruno->fresh()->ended_reason]);
        $this->assertFalse(collect($this->permissionUpdates())->last()['permission']['can_publish']);
    }

    // ─── Événements du direct ──────────────────────────────────────────────

    public function test_banning_takes_the_person_off_stage(): void
    {
        $live = $this->onAir();
        $speaker = $this->bringOnStage($this->alice, $live);

        $this->actingAs($this->moderator)->postJson("/api/v1/lives/{$live->id}/bans", ['user_id' => $this->alice->id])->assertOk();

        $this->assertSame(['done', 'banned'], [$speaker->fresh()->status, $speaker->fresh()->ended_reason]);
        $this->assertFalse(collect($this->permissionUpdates())->last()['permission']['can_publish']);
        $this->requestAs($this->alice, $live)->assertForbidden();
    }

    public function test_ending_the_live_closes_every_request(): void
    {
        $live = $this->onAir();
        $onStage = $this->bringOnStage($this->alice, $live);
        $this->requestAs($this->bruno, $live)->assertCreated();

        $this->actingAs($this->moderator)->postJson("/api/v1/lives/{$live->id}/end")->assertOk();

        $this->assertSame('live_ended', $onStage->fresh()->ended_reason);
        $this->assertSame(LiveSpeaker::CANCELLED, $this->speakerOf($this->bruno, $live)->status);
        $this->assertSame(0, LiveSpeaker::where('live_session_id', $live->id)->open()->count());
    }

    public function test_disconnection_frees_the_stage(): void
    {
        $live = $this->onAir();
        $speaker = $this->bringOnStage($this->alice, $live);

        $body = json_encode(['event' => 'participant_left', 'room' => ['name' => $live->room_name], 'participant' => ['identity' => $speaker->identity]]);
        $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $head = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $claims = $b64(json_encode(['iss' => 'devkey', 'exp' => time() + 60, 'sha256' => base64_encode(hash('sha256', $body, true))]));
        $jwt = "{$head}.{$claims}." . $b64(hash_hmac('sha256', "{$head}.{$claims}", config('livekit.api_secret'), true));

        $this->call('POST', '/api/v1/livekit/webhook', [], [], [], ['HTTP_AUTHORIZATION' => $jwt, 'CONTENT_TYPE' => 'application/webhook+json'], $body)
            ->assertNoContent();

        $this->assertSame(['done', 'disconnected'], [$speaker->fresh()->status, $speaker->fresh()->ended_reason]);
    }

    public function test_cleanup_reconciles_speakers_missing_from_the_room(): void
    {
        $live = $this->onAir();
        $speaker = $this->bringOnStage($this->alice, $live);

        // La salle ne contient plus que le diffuseur (nouveau client simulé : celui du setUp répondrait en premier).
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['lk.test/*' => Http::response(['participants' => [['identity' => "host-{$this->moderator->id}"]]], 200)]);
        $this->artisan('lives:cleanup')->assertSuccessful();

        $this->assertSame('disconnected', $speaker->fresh()->ended_reason);
    }

    // ─── Qui regarde ───────────────────────────────────────────────────────

    public function test_everyone_can_see_who_is_watching(): void
    {
        $live = $this->onAir();
        // Nouveau client HTTP simulé : la simulation du setUp répondrait en premier.
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['lk.test/*' => Http::response(['participants' => [
            ['identity' => "host-{$this->moderator->id}"],
            ['identity' => "user-{$this->alice->id}-aaaa1111"],
            ['identity' => "user-{$this->alice->id}-bbbb2222"], // deuxième onglet : une seule fois
            ['identity' => "user-{$this->bruno->id}-cccc3333"],
            ['identity' => 'guest-dddd4444'],
            ['identity' => 'guest-eeee5555'],
        ]], 200)]);

        // Sans compte : la liste est visible aussi.
        $this->guest()->getJson("/api/v1/lives/{$live->id}/viewers")->assertOk()
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.anonymous', 2)
            ->assertJsonCount(2, 'data.people')
            ->assertJsonPath('data.people.0.displayName', 'Alice')
            ->assertJsonPath('data.people.1.displayName', 'Bruno')
            ->assertJsonMissingPath('data.people.0.email');

        $this->get("/lives/{$live->id}")->assertOk()->assertSee('data-live-viewers-open', false);
    }

    // ─── Message épinglé du diffuseur ──────────────────────────────────────

    public function test_only_the_host_can_unpin_or_replace_their_own_pinned_message(): void
    {
        $live = $this->onAir();
        $otherModerator = $this->makeUser('moderateur', 'Zoé');

        $hostComment = $this->actingAs($this->moderator)->postJson("/api/v1/lives/{$live->id}/comments", ['body' => 'Bienvenue à tous'])
            ->assertCreated()->json('data.id');
        $this->actingAs($this->moderator)->postJson("/api/v1/lives/{$live->id}/comments/{$hostComment}/pin")->assertOk();
        $viewerComment = $this->actingAs($this->alice)->postJson("/api/v1/lives/{$live->id}/comments", ['body' => 'Amen'])
            ->assertCreated()->json('data.id');

        // Un modérateur qui regarde : ni retirer, ni remplacer, ni masquer le message du diffuseur.
        $this->actingAs($otherModerator)->deleteJson("/api/v1/lives/{$live->id}/pin")->assertForbidden();
        $this->actingAs($otherModerator)->postJson("/api/v1/lives/{$live->id}/comments/{$viewerComment}/pin")->assertForbidden();
        $this->actingAs($otherModerator)->deleteJson("/api/v1/lives/{$live->id}/comments/{$hostComment}")->assertForbidden();
        $this->actingAs($this->alice)->deleteJson("/api/v1/lives/{$live->id}/pin")->assertForbidden();
        $this->assertSame($hostComment, $live->fresh()->pinned_comment_id);

        // Le diffuseur, lui, peut.
        $this->actingAs($this->moderator)->deleteJson("/api/v1/lives/{$live->id}/pin")->assertOk();
        $this->assertNull($live->fresh()->pinned_comment_id);

        // Message d'un spectateur épinglé par un modérateur : un autre modérateur peut le retirer.
        $this->actingAs($otherModerator)->postJson("/api/v1/lives/{$live->id}/comments/{$viewerComment}/pin")->assertOk();
        $this->actingAs($otherModerator)->deleteJson("/api/v1/lives/{$live->id}/pin")->assertOk();
    }

    // ─── Pages web ─────────────────────────────────────────────────────────

    public function test_web_pages_show_the_stage_controls(): void
    {
        $live = $this->onAir();

        $this->actingAs($this->alice)->get("/lives/{$live->id}")->assertOk()
            ->assertSee('data-stage-request-form', false)
            ->assertSee('Demander à intervenir')
            ->assertDontSee('data-stage-queue aria-live', false);

        $this->guest()->get("/lives/{$live->id}")->assertOk()
            ->assertSee('pour demander à intervenir', false);

        $this->actingAs($this->moderator)->get("/lives/{$live->id}/studio")->assertOk()
            ->assertSee('data-stage-queue aria-live', false)
            ->assertSee('data-stage-pip', false)
            ->assertSee('Accepter les demandes');
    }
}

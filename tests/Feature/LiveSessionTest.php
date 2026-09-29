<?php

namespace Tests\Feature;

use App\Enums\LiveStatus;
use App\Models\LiveSession;
use App\Models\User;
use App\Services\LiveKit\LiveKitClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LiveSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $moderator;
    private User $user;
    private User $other;

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

        $this->admin     = $this->makeUser('administrateur');
        $this->moderator = $this->makeUser('moderateur');
        $this->user      = $this->makeUser('utilisateur');
        $this->other     = $this->makeUser('utilisateur', 'autre');
    }

    private function makeUser(string $role, string $suffix = ''): User
    {
        return User::create([
            'display_name' => ucfirst($role) . $suffix, 'email' => "{$role}{$suffix}@example.com",
            'password' => 'secret123', 'role' => $role, 'status' => 'active',
        ]);
    }

    private function startLive(?User $host = null, array $extra = []): LiveSession
    {
        $host ??= $this->moderator;
        $this->actingAs($host)->postJson('/api/v1/lives', array_merge(['title' => 'Soirée de témoignages'], $extra))->assertCreated();

        return LiveSession::where('host_id', $host->id)->latest()->firstOrFail();
    }

    private function onAir(?User $host = null, array $extra = []): LiveSession
    {
        $live = $this->startLive($host, $extra);
        $this->actingAs($host ?? $this->moderator)->postJson("/lives/{$live->id}/go-live")->assertOk();

        return $live->fresh();
    }

    /** Oublie l'utilisateur connecté par actingAs() : les requêtes suivantes sont anonymes. */
    private function guest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    private function jwtClaims(string $jwt): array
    {
        return json_decode(base64_decode(strtr(explode('.', $jwt)[1], '-_', '+/')), true);
    }

    private function sentTo(string $method): bool
    {
        return Http::recorded()->contains(fn ($pair) => str_ends_with($pair[0]->url(), "/twirp/livekit.RoomService/{$method}"));
    }

    // ─── Qui peut diffuser ─────────────────────────────────────────────────

    public function test_only_moderators_and_administrators_can_start_a_live(): void
    {
        $this->actingAs($this->user)->postJson('/api/v1/lives', ['title' => 'x'])->assertForbidden();
        $this->actingAs($this->user)->get('/lives/create')->assertForbidden();
        $this->guest()->post('/lives', ['title' => 'x'])->assertRedirect('/login');

        $this->actingAs($this->admin)->postJson('/api/v1/lives', ['title' => 'Admin en direct'])
            ->assertCreated()->assertJsonPath('data.live.status', 'preparing')->assertJsonStructure(['data' => ['video' => ['url', 'token']]]);
        $this->assertTrue($this->sentTo('CreateRoom'));
    }

    public function test_web_form_requires_successful_device_checks(): void
    {
        $this->actingAs($this->moderator)->post('/lives', ['title' => 'Sans vérification'])->assertSessionHasErrors('checks_passed');
        $this->actingAs($this->moderator)->post('/lives', ['title' => 'Vérifié', 'checks_passed' => '1', 'comments_enabled' => '1'])
            ->assertRedirect();
        $this->assertSame(1, LiveSession::count());
    }

    public function test_a_host_cannot_run_two_lives_at_once(): void
    {
        $live = $this->startLive();
        $this->actingAs($this->moderator)->postJson('/api/v1/lives', ['title' => 'Deuxième'])
            ->assertStatus(409)->assertJsonPath('errors.liveId', $live->id);
    }

    public function test_suspended_moderator_cannot_go_live(): void
    {
        $this->moderator->update(['status' => 'suspended']);
        $this->actingAs($this->moderator)->postJson('/api/v1/lives', ['title' => 'x'])->assertForbidden();
    }

    public function test_unconfigured_server_answers_503(): void
    {
        config(['livekit.url' => null]);
        $this->app->forgetInstance(LiveKitClient::class);
        $this->actingAs($this->moderator)->postJson('/api/v1/lives', ['title' => 'x'])->assertStatus(503);
    }

    // ─── Accès vidéo ───────────────────────────────────────────────────────

    public function test_preparing_live_is_hidden_from_the_public(): void
    {
        $live = $this->startLive();

        $this->guest()->get("/lives/{$live->id}")->assertNotFound();
        $this->actingAs($this->user)->getJson("/api/v1/lives/{$live->id}")->assertNotFound();
        $this->actingAs($this->admin)->get("/lives/{$live->id}")->assertOk();
    }

    public function test_host_and_viewer_tokens_have_the_right_permissions(): void
    {
        $live = $this->onAir();

        $this->actingAs($this->user)->postJson("/lives/{$live->id}/host-token")->assertForbidden();
        $this->actingAs($this->user)->get("/lives/{$live->id}/studio")->assertForbidden();

        $host = $this->jwtClaims($this->actingAs($this->moderator)->postJson("/lives/{$live->id}/host-token")->json('data.token'));
        $this->assertTrue($host['video']['canPublish']);
        $this->assertFalse($host['video']['canPublishData']);
        $this->assertSame($live->room_name, $host['video']['room']);

        $this->guest();
        $viewer = $this->jwtClaims($this->postJson("/lives/{$live->id}/viewer-token")->assertOk()->json('data.token'));
        $this->assertFalse($viewer['video']['canPublish']);
        $this->assertFalse($viewer['video']['canPublishData']);
        $this->assertTrue($viewer['video']['hidden']);
        $this->assertStringStartsWith('guest-', $viewer['sub']);
    }

    public function test_go_live_broadcasts_and_is_host_only(): void
    {
        $live = $this->startLive();
        $this->actingAs($this->admin)->postJson("/lives/{$live->id}/go-live")->assertForbidden();

        $this->actingAs($this->moderator)->postJson("/lives/{$live->id}/go-live")->assertOk();
        $this->assertSame(LiveStatus::Live, $live->fresh()->status);
        $this->assertNotNull($live->fresh()->started_at);
        $this->assertTrue($this->sentTo('SendData'));
        $this->get("/lives/{$live->id}")->assertOk()->assertSee('Soirée de témoignages');
    }

    // ─── Commentaires ──────────────────────────────────────────────────────

    public function test_comments_are_checked_saved_and_broadcast(): void
    {
        $live = $this->onAir();

        $this->guest()->postJson("/lives/{$live->id}/comments", ['body' => 'Invité'])->assertUnauthorized();
        $this->actingAs($this->user)->postJson("/lives/{$live->id}/comments", ['body' => '   '])->assertStatus(422);
        $this->actingAs($this->user)->postJson("/lives/{$live->id}/comments", ['body' => str_repeat('a', 501)])->assertStatus(422);

        $this->actingAs($this->user)->postJson("/lives/{$live->id}/comments", ['body' => 'Gloire à Dieu 🙏'])
            ->assertCreated()->assertJsonPath('data.body', 'Gloire à Dieu 🙏');
        $this->assertSame(1, $live->fresh()->comment_count);

        $sent = Http::recorded()->first(fn ($p) => str_ends_with($p[0]->url(), 'SendData') && str_contains(base64_decode($p[0]['data']), 'Gloire'));
        $this->assertNotNull($sent, 'le commentaire doit être rediffusé dans la salle');

        $this->getJson("/api/v1/lives/{$live->id}/comments")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_comments_are_rate_limited(): void
    {
        $live = $this->onAir();
        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($this->user)->postJson("/lives/{$live->id}/comments", ['body' => "Message {$i}"])->assertCreated();
        }
        $this->actingAs($this->user)->postJson("/lives/{$live->id}/comments", ['body' => 'Un de trop'])->assertStatus(429);
    }

    public function test_comments_can_be_disabled(): void
    {
        $live = $this->onAir(null, ['comments_enabled' => false]);
        $this->actingAs($this->user)->postJson("/lives/{$live->id}/comments", ['body' => 'Bonjour'])->assertForbidden();
    }

    public function test_moderators_can_hide_comments_but_viewers_cannot(): void
    {
        $live = $this->onAir();
        $id = $this->actingAs($this->user)->postJson("/lives/{$live->id}/comments", ['body' => 'À masquer'])->json('data.id');

        $this->actingAs($this->other)->deleteJson("/lives/{$live->id}/comments/{$id}")->assertForbidden();
        $this->actingAs($this->admin)->deleteJson("/lives/{$live->id}/comments/{$id}")->assertOk();

        $this->getJson("/lives/{$live->id}/comments")->assertJsonCount(0, 'data');
        $this->assertSame(0, $live->fresh()->comment_count);
    }

    public function test_banned_user_loses_comments_and_cannot_interact(): void
    {
        $live = $this->onAir();
        $this->actingAs($this->user)->postJson("/lives/{$live->id}/comments", ['body' => 'Premier'])->assertCreated();

        $this->actingAs($this->moderator)->postJson("/lives/{$live->id}/bans", ['user_id' => $this->admin->id])->assertStatus(422);
        $this->actingAs($this->other)->postJson("/lives/{$live->id}/bans", ['user_id' => $this->user->id])->assertForbidden();
        $this->actingAs($this->moderator)->postJson("/lives/{$live->id}/bans", ['user_id' => $this->user->id])->assertOk();

        $this->getJson("/lives/{$live->id}/comments")->assertJsonCount(0, 'data');
        $this->actingAs($this->user)->postJson("/lives/{$live->id}/comments", ['body' => 'Encore'])->assertForbidden();
        $this->actingAs($this->user)->postJson("/lives/{$live->id}/reactions", ['type' => 'amen'])->assertForbidden();
    }

    // ─── Commentaires du diffuseur et épinglage ─────────────────────────────

    public function test_host_can_comment_even_when_comments_are_disabled(): void
    {
        $live = $this->onAir(null, ['comments_enabled' => false]);

        $this->actingAs($this->user)->postJson("/lives/{$live->id}/comments", ['body' => 'Public'])->assertForbidden();
        $this->actingAs($this->moderator)->postJson("/lives/{$live->id}/comments", ['body' => 'Bienvenue à tous !'])
            ->assertCreated()->assertJsonPath('data.user.id', $this->moderator->id);
    }

    public function test_host_can_pin_and_unpin_a_comment(): void
    {
        $live = $this->onAir();
        $id = $this->actingAs($this->user)->postJson("/lives/{$live->id}/comments", ['body' => 'Merci Seigneur'])->json('data.id');

        // Un spectateur ne peut pas épingler.
        $this->actingAs($this->other)->postJson("/lives/{$live->id}/comments/{$id}/pin")->assertForbidden();

        $this->actingAs($this->moderator)->postJson("/lives/{$live->id}/comments/{$id}/pin")
            ->assertOk()->assertJsonPath('data.id', $id);
        $this->assertSame($id, $live->fresh()->pinned_comment_id);

        $sent = Http::recorded()->first(fn ($p) => str_ends_with($p[0]->url(), 'SendData') && str_contains(base64_decode($p[0]['data']), 'comment_pinned'));
        $this->assertNotNull($sent, "l'épinglage doit être rediffusé dans la salle");

        // Visible dans le détail et dans les statistiques (secours des clients).
        $this->getJson("/api/v1/lives/{$live->id}")->assertJsonPath('data.pinnedComment.id', $id);
        $this->getJson("/api/v1/lives/{$live->id}/stats")->assertJsonPath('data.pinnedComment.body', 'Merci Seigneur');

        $this->actingAs($this->other)->deleteJson("/lives/{$live->id}/pin")->assertForbidden();
        $this->actingAs($this->moderator)->deleteJson("/lives/{$live->id}/pin")->assertOk();
        $this->assertNull($live->fresh()->pinned_comment_id);
        $this->getJson("/api/v1/lives/{$live->id}")->assertJsonPath('data.pinnedComment', null);
    }

    public function test_hiding_or_banning_unpins_the_comment(): void
    {
        $live = $this->onAir();
        $id = $this->actingAs($this->user)->postJson("/lives/{$live->id}/comments", ['body' => 'À épingler'])->json('data.id');

        $this->actingAs($this->moderator)->postJson("/lives/{$live->id}/comments/{$id}/pin")->assertOk();
        $this->actingAs($this->moderator)->deleteJson("/lives/{$live->id}/comments/{$id}")->assertOk();
        $this->assertNull($live->fresh()->pinned_comment_id);
        $this->actingAs($this->moderator)->postJson("/lives/{$live->id}/comments/{$id}/pin")->assertStatus(422);

        $id2 = $this->actingAs($this->other)->postJson("/lives/{$live->id}/comments", ['body' => 'Autre'])->json('data.id');
        $this->actingAs($this->moderator)->postJson("/lives/{$live->id}/comments/{$id2}/pin")->assertOk();
        $this->actingAs($this->moderator)->postJson("/lives/{$live->id}/bans", ['user_id' => $this->other->id])->assertOk();
        $this->assertNull($live->fresh()->pinned_comment_id);
    }

    // ─── Réactions ─────────────────────────────────────────────────────────

    public function test_reactions_are_counted(): void
    {
        $live = $this->onAir();
        $this->actingAs($this->user)->postJson("/lives/{$live->id}/reactions", ['type' => 'pray'])
            ->assertOk()->assertJsonPath('data.reactions.pray', 1);
        $this->actingAs($this->user)->postJson("/lives/{$live->id}/reactions", ['type' => 'worship'])
            ->assertOk()->assertJsonPath('data.reactions.worship', 1);
        $this->get("/lives/{$live->id}")->assertSee('data-live-react="worship"', false);
        $this->actingAs($this->user)->postJson("/lives/{$live->id}/reactions", ['type' => 'bomb'])->assertStatus(422);
        $this->guest()->postJson("/lives/{$live->id}/reactions", ['type' => 'pray'])->assertUnauthorized();
    }

    // ─── Fin du direct ─────────────────────────────────────────────────────

    public function test_host_or_moderator_can_end_the_live(): void
    {
        $live = $this->onAir();
        $this->actingAs($this->user)->postJson("/lives/{$live->id}/end")->assertForbidden();
        $this->actingAs($this->admin)->postJson("/lives/{$live->id}/end")->assertOk()->assertJsonPath('data.endReason', 'moderator');

        $this->assertSame(LiveStatus::Ended, $live->fresh()->status);
        $this->assertTrue($this->sentTo('DeleteRoom'));
        $this->actingAs($this->user)->postJson("/lives/{$live->id}/comments", ['body' => 'Trop tard'])->assertStatus(409);
        $this->postJson("/lives/{$live->id}/viewer-token")->assertStatus(410);
    }

    // ─── Webhooks LiveKit ──────────────────────────────────────────────────

    private function webhook(array $event, bool $valid = true)
    {
        $body = json_encode($event);
        $secret = $valid ? config('livekit.api_secret') : 'mauvais-secret';
        $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $head = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $claims = $b64(json_encode(['iss' => 'devkey', 'exp' => time() + 60, 'sha256' => base64_encode(hash('sha256', $body, true))]));
        $jwt = "{$head}.{$claims}." . $b64(hash_hmac('sha256', "{$head}.{$claims}", $secret, true));

        return $this->call('POST', '/api/v1/livekit/webhook', [], [], [], [
            'HTTP_AUTHORIZATION' => $jwt, 'CONTENT_TYPE' => 'application/webhook+json',
        ], $body);
    }

    public function test_webhooks_are_verified_and_drive_the_status(): void
    {
        $live = $this->startLive();

        $this->webhook(['event' => 'track_published', 'room' => ['name' => $live->room_name], 'participant' => ['identity' => "host-{$this->moderator->id}"]], false)
            ->assertStatus(401);
        $this->assertSame(LiveStatus::Preparing, $live->fresh()->status);

        $this->webhook(['event' => 'track_published', 'room' => ['name' => $live->room_name], 'participant' => ['identity' => "host-{$this->moderator->id}"]])
            ->assertNoContent();
        $this->assertSame(LiveStatus::Live, $live->fresh()->status);

        $this->webhook(['event' => 'room_finished', 'room' => ['name' => $live->room_name]])->assertNoContent();
        $this->assertSame('connection', $live->fresh()->end_reason);
    }

    public function test_cleanup_closes_abandoned_lives(): void
    {
        $live = $this->startLive();
        $live->forceFill(['created_at' => now()->subHour()])->saveQuietly();

        $this->artisan('lives:cleanup')->assertSuccessful();
        $this->assertSame('abandoned', $live->fresh()->end_reason);
    }

    public function test_lives_page_lists_current_lives(): void
    {
        $live = $this->onAir();
        $this->get('/lives')->assertOk()->assertSee($live->title);
        $this->getJson('/api/v1/lives')->assertOk()->assertJsonPath('data.active.0.id', $live->id);
    }

    public function test_web_pages_show_the_pinned_comment_and_host_can_write_when_comments_are_closed(): void
    {
        $live = $this->onAir(null, ['comments_enabled' => false]);
        $id = $this->actingAs($this->moderator)->postJson("/lives/{$live->id}/comments", ['body' => 'Annonce du diffuseur'])->json('data.id');
        $this->actingAs($this->moderator)->postJson("/lives/{$live->id}/comments/{$id}/pin")->assertOk();

        // Diffuseur : formulaire (malgré les commentaires fermés), case « Épingler », URL d'épinglage.
        $this->actingAs($this->moderator)->get("/lives/{$live->id}/studio")->assertOk()
            ->assertSee('data-live-comment-form', false)
            ->assertSee('data-live-comment-pin', false)
            ->assertSee('Annonce du diffuseur')
            ->assertSee('/pin', false);

        // Spectateur : bandeau épinglé, mais ni formulaire ni actions d'épinglage.
        $this->actingAs($this->user)->get("/lives/{$live->id}")->assertOk()
            ->assertSee('data-live-pinned', false)
            ->assertSee('Annonce du diffuseur')
            ->assertDontSee('data-live-comment-form', false)
            ->assertDontSee('data-live-unpin', false);
    }
}

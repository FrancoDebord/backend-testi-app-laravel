<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LiveComment;
use App\Models\LiveSession;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Directs passés : revoir le chat et témoigner après la fin — docs/fonctionnalites/lives.md */
class PastLivesTest extends TestCase
{
    use RefreshDatabase;

    private User $host;
    private User $marie;

    protected function setUp(): void
    {
        parent::setUp();
        Category::create(['name' => 'Guérison', 'slug' => 'guerison', 'is_active' => true, 'display_order' => 1]);
        $this->host  = $this->user('Pasteur Paul', 'moderateur');
        $this->marie = $this->user('Marie');
    }

    private function user(string $name, string $role = 'utilisateur'): User
    {
        return User::create([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => $role, 'status' => 'active',
        ]);
    }

    private function live(string $status = 'ended', array $extra = []): LiveSession
    {
        return LiveSession::create(array_merge([
            'host_id' => $this->host->id, 'title' => 'Soirée de louange', 'room_name' => 'live-' . Str::random(8),
            'status' => $status,
            'started_at' => $status === 'preparing' ? null : Carbon::parse('2026-10-18 20:00:00'),
            'ended_at'   => $status === 'ended' ? Carbon::parse('2026-10-18 21:30:00') : null,
        ], $extra));
    }

    private function comment(LiveSession $live, User $user, string $body, string $at, bool $hidden = false): LiveComment
    {
        $comment = LiveComment::create(['live_session_id' => $live->id, 'user_id' => $user->id, 'body' => $body, 'is_hidden' => $hidden]);
        $comment->forceFill(['created_at' => Carbon::parse($at), 'updated_at' => Carbon::parse($at)])->save();

        return $comment;
    }

    private function testimony(array $extra = []): Testimony
    {
        return Testimony::create(array_merge([
            'user_id' => $this->marie->id, 'title' => 'Guérie pendant le direct', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Merci', 'visibility' => 'public', 'status' => 'approved',
        ], $extra));
    }

    // ─── Revoir le chat ──────────────────────────────────────────────────

    public function test_ended_live_chat_is_paginated_oldest_first_with_offsets(): void
    {
        $live = $this->live();
        $this->comment($live, $this->marie, 'Troisième', '2026-10-18 21:05:00');
        $this->comment($live, $this->host, 'Bienvenue à tous', '2026-10-18 20:00:30');
        $this->comment($live, $this->marie, 'Masqué', '2026-10-18 20:10:00', hidden: true);
        $this->comment($live, $this->marie, 'Amen', '2026-10-18 20:02:00');

        // Lisible sans compte.
        $res = $this->getJson("/api/v1/lives/{$live->id}/comments?limit=2")->assertOk()
            ->assertJsonPath('meta.currentPage', 1)->assertJsonPath('meta.lastPage', 2)->assertJsonPath('meta.total', 3)
            ->assertJsonCount(2, 'data');
        $this->assertSame(['Bienvenue à tous', 'Amen'], array_column($res->json('data'), 'body'));
        $this->assertSame([30, 120], array_column($res->json('data'), 'offsetSeconds'));
        $this->assertNotNull($res->json('data.0.createdAt'));
        $this->assertSame($this->host->id, $res->json('data.0.user.id'));

        $this->getJson("/api/v1/lives/{$live->id}/comments?limit=2&page=2")->assertOk()
            ->assertJsonPath('data.0.body', 'Troisième')->assertJsonPath('data.0.offsetSeconds', 3900);

        // limit plafonné à 100.
        $this->getJson("/api/v1/lives/{$live->id}/comments?limit=500")->assertOk()->assertJsonPath('meta.perPage', 100);
    }

    public function test_offset_is_null_when_start_is_unknown_and_ongoing_shape_is_kept(): void
    {
        $unknown = $this->live('ended', ['started_at' => null]);
        $this->comment($unknown, $this->marie, 'Bonsoir', '2026-10-18 20:05:00');
        $this->getJson("/api/v1/lives/{$unknown->id}/comments")->assertOk()->assertJsonPath('data.0.offsetSeconds', null);

        // Direct en cours : tableau des derniers commentaires, sans pagination, offsetSeconds ajouté.
        $live = $this->live('live');
        $this->comment($live, $this->marie, 'Gloire', '2026-10-18 20:01:00');
        $res = $this->getJson("/api/v1/lives/{$live->id}/comments")->assertOk()
            ->assertJsonPath('data.0.body', 'Gloire')->assertJsonPath('data.0.offsetSeconds', 60);
        $this->assertArrayNotHasKey('meta', $res->json());
    }

    public function test_live_not_visible_keeps_its_chat_private(): void
    {
        $live = $this->live('preparing');
        $this->getJson("/api/v1/lives/{$live->id}/comments")->assertNotFound();
        $this->getJson("/api/v1/lives/{$live->id}/testimonies")->assertNotFound();
    }

    // ─── Témoigner après la fin ─────────────────────────────────────────

    public function test_logged_in_user_testifies_on_an_ended_live(): void
    {
        $live = $this->live();

        $res = $this->actingAs($this->marie)->postJson('/api/v1/testimonies', [
            'title' => 'Touchée par ce direct', 'type' => 'text', 'category' => 'guerison', 'body_text' => 'Merci Seigneur', 'live_id' => $live->id,
        ])->assertCreated()
            ->assertJsonPath('data.live.id', $live->id)
            ->assertJsonPath('data.live.title', 'Soirée de louange')
            ->assertJsonPath('data.live.startedAt', $live->started_at->toIso8601String());

        $this->assertSame($live->id, Testimony::findOrFail($res->json('data.id'))->live_id);

        // Sans direct : live null.
        $this->actingAs($this->marie)->postJson('/api/v1/testimonies', [
            'title' => 'Autre', 'type' => 'text', 'category' => 'guerison', 'body_text' => 'Texte',
        ])->assertCreated()->assertJsonPath('data.live', null);
    }

    public function test_live_must_exist_be_visible_and_ended(): void
    {
        $payload = ['title' => 'Touchée', 'type' => 'text', 'category' => 'guerison', 'body_text' => 'Merci'];

        foreach ([
            'pas-un-uuid'                     => 'Le direct indiqué est invalide.',
            (string) Str::uuid()              => 'Ce direct est introuvable.',
            $this->live('preparing')->id      => 'Ce direct est introuvable.',
            $this->live('live')->id           => 'Vous pourrez témoigner sur ce direct une fois qu’il sera terminé.',
        ] as $liveId => $message) {
            $this->actingAs($this->marie)->postJson('/api/v1/testimonies', [...$payload, 'live_id' => $liveId])
                ->assertStatus(422)->assertJsonPath('errors.live_id.0', $message);
        }
        $this->assertSame(0, Testimony::count());
    }

    public function test_video_upload_init_checks_the_live_before_creating_anything(): void
    {
        config(['services.bunny_stream' => ['library_id' => '777434', 'api_key' => 'cle', 'cdn_host' => 'vz-test.b-cdn.net']]);
        Http::fake(['video.bunnycdn.com/*' => Http::response(['guid' => 'f1e2d3c4-b5a6-4798-8a9b-0c1d2e3f4a5b'])]);

        $this->actingAs($this->marie)->postJson('/api/v1/videos/upload-init', [
            'title' => 'Vidéo', 'category' => 'guerison', 'live_id' => $this->live('live')->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['live_id']);
        $this->assertSame(0, Testimony::count());
        Http::assertNothingSent();

        $ended = $this->live();
        $res = $this->actingAs($this->marie)->postJson('/api/v1/videos/upload-init', [
            'title' => 'Vidéo', 'category' => 'guerison', 'live_id' => $ended->id,
        ])->assertCreated();
        $this->assertSame($ended->id, Testimony::findOrFail($res->json('data.testimony_id'))->live_id);
    }

    public function test_live_testimonies_lists_published_ones_and_detail_has_count_and_can_testify(): void
    {
        $live = $this->live();
        $published = $this->testimony(['live_id' => $live->id]);
        $this->testimony(['live_id' => $live->id, 'title' => 'En attente', 'status' => 'pending']);
        $this->testimony(['live_id' => $live->id, 'title' => 'Carnet', 'visibility' => 'private', 'status' => 'draft']);
        $this->testimony(['title' => 'Sans direct']);

        $this->getJson("/api/v1/lives/{$live->id}/testimonies")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.live.id', $live->id)->assertJsonPath('meta.total', 1);

        $this->getJson("/api/v1/lives/{$live->id}")->assertOk()
            ->assertJsonPath('data.testimoniesCount', 1)->assertJsonPath('data.canTestify', false);
        $this->actingAs($this->marie)->getJson("/api/v1/lives/{$live->id}")->assertOk()
            ->assertJsonPath('data.canTestify', true);

        $ongoing = $this->live('live');
        $this->actingAs($this->marie)->getJson("/api/v1/lives/{$ongoing->id}")->assertOk()
            ->assertJsonPath('data.testimoniesCount', 0)->assertJsonPath('data.canTestify', false);
    }

    // ─── Site ────────────────────────────────────────────────────────────

    public function test_ended_live_page_shows_chat_replay_testify_button_and_testimonies(): void
    {
        $live = $this->live();
        $this->comment($live, $this->marie, 'Merci pour ce moment', '2026-10-18 21:01:05');
        $this->comment($live, $this->marie, 'Message masqué', '2026-10-18 20:30:00', hidden: true);
        $this->testimony(['live_id' => $live->id, 'title' => 'Ma guérison ce soir']);

        $this->actingAs($this->marie)->get("/lives/{$live->id}")->assertOk()
            ->assertSee('Revoir le chat')
            ->assertSee('Merci pour ce moment')->assertSee('1:01:05')
            ->assertDontSee('Message masqué')
            ->assertSee('Témoigner sur ce direct')
            ->assertSee(route('publish', ['live' => $live->id]), false)
            ->assertSee('Témoignages sur ce direct')->assertSee('Ma guérison ce soir')
            ->assertSee('"mode":"replay"', false);

        // Visiteur : invité à se connecter.
        $this->app['auth']->forgetGuards();
        $this->get("/lives/{$live->id}")->assertOk()->assertSee('Connectez-vous pour témoigner sur ce direct.');

        // Direct en cours : pas de rediffusion du chat.
        $ongoing = $this->live('live');
        $this->get("/lives/{$ongoing->id}")->assertOk()->assertDontSee('Revoir le chat')->assertSee('"mode":"viewer"', false);
    }

    public function test_web_publish_form_is_prefilled_and_links_the_testimony(): void
    {
        $live = $this->live();

        $this->actingAs($this->marie)->get("/publish?live={$live->id}")->assertOk()
            ->assertSee('Vous témoignez sur le direct')->assertSee('name="live_id" value="' . $live->id . '"', false);
        // Direct en cours : pas de pré-remplissage.
        $ongoing = $this->live('live');
        $this->actingAs($this->marie)->get("/publish?live={$ongoing->id}")->assertOk()->assertDontSee('name="live_id"', false);

        $form = ['title' => 'Touchée', 'type' => 'text', 'category' => 'guerison', 'body_text' => 'Merci', 'visibility' => 'public', 'consent_given' => '1'];
        $this->actingAs($this->marie)->post('/testimonies', [...$form, 'live_id' => $ongoing->id])
            ->assertSessionHasErrors(['live_id' => 'Vous pourrez témoigner sur ce direct une fois qu’il sera terminé.']);

        $this->actingAs($this->marie)->post('/testimonies', [...$form, 'live_id' => $live->id])->assertRedirect();
        $this->assertSame($live->id, Testimony::where('title', 'Touchée')->firstOrFail()->live_id);
    }

    public function test_deleting_a_live_unlinks_its_testimonies(): void
    {
        $live = $this->live();
        $testimony = $this->testimony(['live_id' => $live->id]);

        $live->delete();

        $this->assertNull($testimony->fresh()->live_id);
    }
}

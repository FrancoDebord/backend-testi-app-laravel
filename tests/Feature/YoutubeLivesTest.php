<?php

namespace Tests\Feature;

use App\Enums\LiveStatus;
use App\Models\AppNotification;
use App\Models\LiveSession;
use App\Models\User;
use App\Services\LiveKit\LiveKitClient;
use App\Services\LiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Directs diffusés sur YouTube (source « youtube ») : aucun usage de LiveKit.
 * Voir docs/fonctionnalites/lives.md › Direct YouTube
 */
class YoutubeLivesTest extends TestCase
{
    use RefreshDatabase;

    private const LINK = 'https://www.youtube.com/live/dQw4w9WgXcQ?si=abc';

    private User $admin;
    private User $moderator;
    private User $user;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        // LiveKit NON configuré, et aucune requête HTTP sortante tolérée.
        config(['livekit.url' => null, 'livekit.api_key' => null, 'livekit.api_secret' => null]);
        $this->app->forgetInstance(LiveKitClient::class);
        Http::preventStrayRequests();

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

    private function startYoutube(?User $host = null): LiveSession
    {
        $host ??= $this->moderator;
        $this->actingAs($host)->postJson('/api/v1/lives', [
            'title' => 'Louange en direct', 'source' => 'youtube', 'youtube_url' => self::LINK,
        ])->assertCreated();

        return LiveSession::where('host_id', $host->id)->latest()->firstOrFail();
    }

    private function guest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    public function test_youtube_live_goes_on_air_without_livekit(): void
    {
        DB::table('follows')->insert(['follower_id' => $this->user->id, 'following_id' => $this->moderator->id, 'created_at' => now()]);

        $res = $this->actingAs($this->moderator)->postJson('/api/v1/lives', [
            'title' => 'Louange en direct', 'source' => 'youtube', 'youtube_url' => self::LINK, 'record' => true,
        ])->assertCreated()
            ->assertJsonPath('data.live.status', 'live')
            ->assertJsonPath('data.live.source', 'youtube')
            ->assertJsonPath('data.live.youtube.id', 'dQw4w9WgXcQ')
            ->assertJsonPath('data.live.youtube.url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->assertJsonPath('data.live.youtube.thumbnailUrl', 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg')
            ->assertJsonPath('data.live.recording.enabled', false);

        $this->assertArrayHasKey('video', $res->json('data'));
        $this->assertNull($res->json('data.video'));
        $this->assertStringStartsWith('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $res->json('data.live.youtube.embedUrl'));

        $live = LiveSession::firstOrFail();
        $this->assertSame(LiveStatus::Live, $live->status);
        $this->assertNotNull($live->started_at);
        $this->assertFalse($live->record);
        $this->assertSame('dQw4w9WgXcQ', $live->youtube_video_id);
        // Mêmes effets que goLive : les abonnés sont prévenus.
        $this->assertSame(1, AppNotification::where('type', 'live_started')->where('recipient_id', $this->user->id)->count());

        // Un seul direct à la fois.
        $this->actingAs($this->moderator)->postJson('/api/v1/lives', [
            'title' => 'Encore', 'source' => 'youtube', 'youtube_url' => self::LINK,
        ])->assertStatus(409);

        // Les directs LiveKit restent refusés sans LiveKit.
        $this->actingAs($this->admin)->postJson('/api/v1/lives', ['title' => 'Caméra'])->assertStatus(503);
        // Droits inchangés : un simple utilisateur ne peut pas diffuser.
        $this->actingAs($this->other)->postJson('/api/v1/lives', [
            'title' => 'Non', 'source' => 'youtube', 'youtube_url' => self::LINK,
        ])->assertForbidden();
    }

    public function test_invalid_or_missing_youtube_link_is_rejected(): void
    {
        $this->actingAs($this->moderator)->postJson('/api/v1/lives', [
            'title' => 'Louange', 'source' => 'youtube', 'youtube_url' => 'https://vimeo.com/12345',
        ])->assertStatus(422)->assertJsonValidationErrors(['youtube_url']);

        app()->setLocale('en');
        $this->actingAs($this->moderator)->withHeader('Accept-Language', 'en')->postJson('/api/v1/lives', [
            'title' => 'Louange', 'source' => 'youtube',
        ])->assertStatus(422)->assertJsonValidationErrors(['youtube_url']);

        $this->assertSame(0, LiveSession::count());
    }

    public function test_livekit_only_endpoints_answer_409(): void
    {
        $live = $this->startYoutube();

        $this->guest()->postJson("/api/v1/lives/{$live->id}/viewer-token")
            ->assertStatus(409)->assertJsonPath('message', __('lives.errors.youtube_only'));
        $this->actingAs($this->moderator)->postJson("/api/v1/lives/{$live->id}/host-token")->assertStatus(409);
        $this->actingAs($this->user)->postJson("/api/v1/lives/{$live->id}/stage/requests")->assertStatus(409);
        $this->actingAs($this->moderator)->postJson("/api/v1/lives/{$live->id}/stage/settings", ['enabled' => true])->assertStatus(409);

        // État de la scène : fermé, sans erreur.
        $this->actingAs($this->user)->getJson("/api/v1/lives/{$live->id}/stage")->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.canRequest', false);
    }

    public function test_comments_reactions_and_moderation_work_without_livekit(): void
    {
        $live = $this->startYoutube();

        $comment = $this->actingAs($this->user)->postJson("/api/v1/lives/{$live->id}/comments", ['body' => 'Gloire à Dieu'])
            ->assertCreated()->json('data');
        $this->actingAs($this->user)->postJson("/api/v1/lives/{$live->id}/reactions", ['type' => 'amen'])
            ->assertOk()->assertJsonPath('data.reactions.amen', 1);

        $this->guest()->getJson("/api/v1/lives/{$live->id}/comments")->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->moderator)->postJson("/api/v1/lives/{$live->id}/comments/{$comment['id']}/pin")->assertOk();
        $this->actingAs($this->moderator)->deleteJson("/api/v1/lives/{$live->id}/pin")->assertOk();
        $this->actingAs($this->moderator)->postJson("/api/v1/lives/{$live->id}/bans", ['user_id' => $this->user->id])->assertOk();
        $this->actingAs($this->user)->postJson("/api/v1/lives/{$live->id}/comments", ['body' => 'Encore'])->assertForbidden();
        $this->guest()->getJson("/api/v1/lives/{$live->id}/comments")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_heartbeat_counts_distinct_viewers_and_updates_peak(): void
    {
        $live = $this->startYoutube();

        // Diffuseur : pas compté.
        $this->actingAs($this->moderator)->postJson("/api/v1/lives/{$live->id}/heartbeat")
            ->assertOk()->assertJsonPath('data.viewers', 0);

        $this->actingAs($this->user)->postJson("/api/v1/lives/{$live->id}/heartbeat", ['viewer_id' => 'appareil-1'])->assertOk();
        $this->actingAs($this->user)->postJson("/api/v1/lives/{$live->id}/heartbeat", ['viewer_id' => 'appareil-2'])->assertOk(); // même compte
        $this->guest()->postJson("/api/v1/lives/{$live->id}/heartbeat", ['viewer_id' => 'invite-1'])->assertOk();
        $res = $this->guest()->postJson("/api/v1/lives/{$live->id}/heartbeat", ['viewer_id' => 'invite-2'])->assertOk()
            ->assertJsonPath('data.status', 'live')
            ->assertJsonPath('data.viewers', 3)
            ->assertJsonPath('data.peakViewers', 3);
        $this->assertArrayHasKey('commentCount', $res->json('data'));
        $this->assertArrayHasKey('reactions', $res->json('data'));

        $this->assertSame(3, $live->fresh()->peak_viewers);
        $this->guest()->getJson("/api/v1/lives/{$live->id}/stats")->assertOk()->assertJsonPath('data.viewers', 3);
        $this->guest()->getJson("/api/v1/lives/{$live->id}")->assertOk()->assertJsonPath('data.liveStats.viewers', 3);
        $this->guest()->getJson("/api/v1/lives/{$live->id}/viewers")->assertOk()
            ->assertJsonPath('data.total', 3)->assertJsonPath('data.anonymous', 2)
            ->assertJsonPath('data.people.0.id', $this->user->id);

        $this->postJson("/api/v1/lives/{$live->id}/heartbeat", ['viewer_id' => str_repeat('x', 65)])->assertStatus(422);

        // Signaux expirés : plus comptés, le pic reste.
        $this->travel(2)->minutes();
        $this->guest()->getJson("/api/v1/lives/{$live->id}/stats")->assertOk()
            ->assertJsonPath('data.viewers', 0)->assertJsonPath('data.peakViewers', 3);
    }

    public function test_host_or_admin_can_end_and_heartbeat_then_stops_counting(): void
    {
        $live = $this->startYoutube();

        $this->actingAs($this->other)->postJson("/api/v1/lives/{$live->id}/end")->assertForbidden();
        $this->actingAs($this->moderator)->postJson("/api/v1/lives/{$live->id}/end")->assertOk()
            ->assertJsonPath('data.status', 'ended')
            ->assertJsonPath('data.endReason', 'host')
            ->assertJsonPath('data.youtube.id', 'dQw4w9WgXcQ'); // rediffusion

        $this->guest()->postJson("/api/v1/lives/{$live->id}/heartbeat", ['viewer_id' => 'invite-1'])->assertOk()
            ->assertJsonPath('data.status', 'ended')->assertJsonPath('data.viewers', 0);

        $second = $this->startYoutube($this->makeUser('moderateur', '2'));
        $this->actingAs($this->admin)->postJson("/api/v1/lives/{$second->id}/end")->assertOk()->assertJsonPath('data.endReason', 'moderator');
    }

    public function test_cleanup_ends_youtube_lives_after_twelve_hours_only(): void
    {
        $live = $this->startYoutube();
        $lives = app(LiveService::class);

        $this->assertSame(0, $lives->cleanup());
        $this->travel(11)->hours();
        $this->assertSame(0, $lives->cleanup());
        $this->assertSame(LiveStatus::Live, $live->fresh()->status);

        $this->travel(2)->hours();
        $this->assertSame(1, $lives->cleanup());
        $this->assertSame(LiveStatus::Ended, $live->fresh()->status);
        $this->assertSame('abandoned', $live->fresh()->end_reason);
    }

    public function test_index_exposes_youtube_enabled_and_lists_youtube_lives(): void
    {
        $live = $this->startYoutube();

        $this->guest()->getJson('/api/v1/lives')->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.youtubeEnabled', true)
            ->assertJsonPath('data.active.0.id', $live->id)
            ->assertJsonPath('data.active.0.youtube.id', 'dQw4w9WgXcQ');

        $this->get('/lives')->assertOk()->assertSee('data-live-youtube-badge', false);
    }

    public function test_web_show_page_renders_the_youtube_player_without_livekit_video(): void
    {
        $live = $this->startYoutube();

        $page = $this->guest()->get("/lives/{$live->id}")->assertOk()
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('<iframe', false)
            ->assertSee(str_replace('/', '\/', route('lives.heartbeat', $live->id)), false) // configuration JSON de live.js
            ->assertSee(__('lives.show.youtube_open'))
            ->assertDontSee('data-live-video', false)
            ->assertDontSee('data-stage-panel', false);
        $this->assertStringContainsString('"mode":"youtube"', $page->getContent());

        // Heartbeat et commentaires par les routes web.
        $this->postJson(route('lives.heartbeat', $live->id), ['viewer_id' => 'web-1'])->assertOk()->assertJsonPath('data.viewers', 1);
        $this->getJson(route('lives.comments', $live->id))->assertOk();

        // Diffuseur : bouton pour couper le direct.
        $this->actingAs($this->moderator)->get("/lives/{$live->id}")->assertOk()->assertSee('live-end-form', false);

        // Terminé : bilan avec la vidéo YouTube en rediffusion.
        $this->actingAs($this->moderator)->postJson(route('lives.end', $live->id))->assertOk();
        $this->guest()->get("/lives/{$live->id}")->assertOk()
            ->assertSee('data-live-ended', false)
            ->assertSee('data-youtube-replay', false)
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
    }

    public function test_create_page_offers_youtube_and_web_form_starts_it(): void
    {
        $this->actingAs($this->moderator)->get('/lives/create')->assertOk()
            ->assertSee('value="youtube"', false)
            ->assertSee('name="youtube_url"', false)
            ->assertSee(__('lives.create.youtube_url_hint'))
            ->assertSee(__('lives.create.youtube_only'));

        $this->actingAs($this->moderator)->post('/lives', [
            'title' => 'Louange', 'source' => 'youtube', 'youtube_url' => 'pas un lien',
        ])->assertSessionHasErrors('youtube_url');

        $res = $this->actingAs($this->moderator)->post('/lives', [
            'title' => 'Louange', 'source' => 'youtube', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ]);
        $live = LiveSession::firstOrFail();
        $res->assertRedirect(route('lives.show', $live->id));
        $this->assertTrue($live->isOnAir());
    }

    public function test_studio_redirects_to_the_show_page(): void
    {
        $live = $this->startYoutube();

        $this->actingAs($this->moderator)->get("/lives/{$live->id}/studio")->assertRedirect(route('lives.show', $live->id));
    }
}

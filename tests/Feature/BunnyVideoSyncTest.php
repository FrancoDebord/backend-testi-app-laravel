<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotificationJob;
use App\Models\Category;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Vidéos Bunny Stream lisibles partout : webhook fiable (statuts 3, 4, 5, GUID inconnu), commande
 * videos:sync-bunny-status (filet de sécurité, alerte des vidéos bloquées), objet « video » de l'API,
 * envoi direct depuis le site, lecture HLS. Voir docs/fonctionnalites/videos-bunny-stream.md
 */
class BunnyVideoSyncTest extends TestCase
{
    use RefreshDatabase;

    private const LIBRARY = '777434';
    private const CDN     = 'vz-test.b-cdn.net';
    private const GUID    = 'c51171c5-a620-408d-9745-85373ff8b0b7';

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.bunny_stream' => ['library_id' => self::LIBRARY, 'api_key' => 'cle', 'cdn_host' => 'https://' . self::CDN . '/', 'mp4_fallback' => true]]);
        Category::create(['name' => 'Guérison', 'slug' => 'guerison', 'is_active' => true, 'display_order' => 1]);
        Queue::fake([SendPushNotificationJob::class]);
        $this->author = $this->makeUser('marie');
    }

    private function makeUser(string $name, array $attrs = []): User
    {
        return User::create(array_merge([
            'display_name' => ucfirst($name), 'email' => "{$name}@example.com",
            'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active',
        ], $attrs));
    }

    private function video(string $status = Testimony::VIDEO_PROCESSING, array $extra = []): Testimony
    {
        return Testimony::create(array_merge([
            'user_id' => $this->author->id, 'title' => 'Ma guérison', 'type' => 'video', 'category_slug' => 'guerison',
            'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
            'bunny_video_id' => self::GUID, 'video_status' => $status,
        ], $extra));
    }

    /** Réponse de GET /library/{id}/videos/{guid} (statuts de l'API : 3 encodage, 4 terminée, 5 erreur). */
    private function bunnySays(int $status, int $length = 95, string $resolutions = '240p,360p,480p,720p,1080p'): void
    {
        Http::fake(['video.bunnycdn.com/library/' . self::LIBRARY . '/videos/*' => Http::response([
            'guid' => self::GUID, 'title' => 'Ma guérison', 'status' => $status, 'length' => $length, 'availableResolutions' => $resolutions,
        ])]);
    }

    private function webhook(int $status, string $guid = self::GUID, string $library = self::LIBRARY)
    {
        return $this->postJson('/api/v1/webhooks/bunny', ['VideoLibraryId' => (int) $library, 'VideoGuid' => $guid, 'Status' => $status]);
    }

    // ─── Webhook ─────────────────────────────────────────────────────────────

    public function test_webhook_finished_makes_video_playable_everywhere_and_notifies_once(): void
    {
        $t = $this->video();
        $this->bunnySays(4);

        $this->webhook(3)->assertOk()->assertExactJson(['ok' => true, 'action' => 'updated']);

        $t->refresh();
        $base = 'https://' . self::CDN . '/' . self::GUID;
        $this->assertSame(Testimony::VIDEO_READY, $t->video_status);
        $this->assertSame("{$base}/playlist.m3u8", $t->media_url);
        $this->assertSame("{$base}/thumbnail.jpg", $t->cover_url);
        $this->assertSame(95, $t->duration);
        $this->assertSame('240p,360p,480p,720p,1080p', $t->available_resolutions);
        Queue::assertPushed(SendPushNotificationJob::class, fn ($job) => $job->data === ['type' => 'video_ready', 'testimony_id' => $t->id]);

        // Rejoué (ou ResolutionFinished suivant) : rien de nouveau, pas de seconde notification.
        $this->webhook(4)->assertOk()->assertExactJson(['ok' => true, 'action' => 'ignored']);
        Queue::assertPushed(SendPushNotificationJob::class, 1);
    }

    public function test_webhook_resolution_finished_makes_video_playable_while_encoding_continues(): void
    {
        $t = $this->video();
        $this->bunnySays(3, 60, '240p,360p'); // encodage en cours, deux résolutions prêtes

        $this->webhook(4)->assertOk()->assertJsonPath('action', 'updated');
        $this->assertSame(Testimony::VIDEO_READY, $t->fresh()->video_status);
        $this->assertStringEndsWith('/playlist.m3u8', $t->fresh()->media_url);
    }

    public function test_webhook_failed_marks_failure_and_notifies_author(): void
    {
        $t = $this->video();
        $this->bunnySays(5, 0, '');

        $this->webhook(5)->assertOk()->assertExactJson(['ok' => true, 'action' => 'updated']);
        $this->assertSame(Testimony::VIDEO_FAILED, $t->fresh()->video_status);
        $this->assertNull($t->fresh()->media_url);
        Queue::assertPushed(SendPushNotificationJob::class, fn ($job) => $job->userId === $this->author->id
            && $job->data === ['type' => 'video_failed', 'testimony_id' => $t->id]);
    }

    public function test_webhook_unknown_guid_other_library_and_api_error_are_reported(): void
    {
        Http::fake();
        $this->webhook(3, 'inconnu-0000')->assertOk()->assertExactJson(['ok' => true, 'action' => 'not_found']);
        $this->webhook(3, self::GUID, '999999')->assertOk()->assertExactJson(['ok' => true, 'action' => 'ignored']);
        $this->postJson('/api/v1/webhooks/bunny', [])->assertOk()->assertExactJson(['ok' => true, 'action' => 'ignored']);
        Http::assertNothingSent();

        // Clé API refusée : rien ne change, la synchronisation planifiée réessaiera.
        $t = $this->video();
        Http::fake(['video.bunnycdn.com/*' => Http::response(['Message' => 'Unauthorized'], 401)]);
        $this->webhook(3)->assertOk()->assertExactJson(['ok' => true, 'action' => 'ignored']);
        $this->assertSame(Testimony::VIDEO_PROCESSING, $t->fresh()->video_status);
    }

    // ─── Commande videos:sync-bunny-status ───────────────────────────────────

    public function test_sync_command_repairs_processing_and_ready_videos_without_link(): void
    {
        $processing = $this->video();
        // Déjà « ready » avant ce correctif, mais sans lien de lecture : réparé sans nouvelle notification.
        $readyNoLink = $this->video(Testimony::VIDEO_READY, ['bunny_video_id' => 'aaaaaaaa-0000-0000-0000-000000000000']);
        $failed = $this->video(Testimony::VIDEO_FAILED, ['bunny_video_id' => 'ffffffff-0000-0000-0000-000000000000']);
        Http::fake(['video.bunnycdn.com/library/' . self::LIBRARY . '/videos/*' => Http::response(['status' => 4, 'length' => 30, 'availableResolutions' => '360p,720p'])]);

        $this->artisan('videos:sync-bunny-status')->assertSuccessful();

        $this->assertSame(Testimony::VIDEO_READY, $processing->fresh()->video_status);
        $this->assertStringEndsWith(self::GUID . '/playlist.m3u8', $processing->fresh()->media_url);
        $this->assertStringEndsWith('aaaaaaaa-0000-0000-0000-000000000000/playlist.m3u8', $readyNoLink->fresh()->media_url);
        $this->assertSame(Testimony::VIDEO_FAILED, $failed->fresh()->video_status); // échecs non relus
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'ffffffff'));
        Queue::assertPushed(SendPushNotificationJob::class, 1); // seulement la vidéo qui devient prête
    }

    public function test_sync_command_single_guid_and_unknown_guid(): void
    {
        $t = $this->video();
        $this->bunnySays(4);

        $this->artisan('videos:sync-bunny-status', ['--guid' => self::GUID])->assertSuccessful();
        $this->assertSame(Testimony::VIDEO_READY, $t->fresh()->video_status);

        $this->artisan('videos:sync-bunny-status', ['--guid' => 'inconnu-0000'])
            ->expectsOutputToContain('Aucun témoignage')
            ->assertSuccessful();
    }

    public function test_videos_processing_for_more_than_30_minutes_alert_super_admins_once(): void
    {
        $super = $this->makeUser('super', ['role' => 'administrateur']);
        $super->forceFill(['is_super_admin' => true])->save();
        $admin = $this->makeUser('admin', ['role' => 'administrateur']);
        $t = $this->video();
        $t->forceFill(['video_processing_at' => now()->subMinutes(45)])->save();
        $recent = $this->video(Testimony::VIDEO_PROCESSING, ['bunny_video_id' => 'bbbbbbbb-0000-0000-0000-000000000000']);
        $recent->forceFill(['video_processing_at' => now()->subMinutes(5)])->save();
        $this->bunnySays(2, 0, ''); // toujours en traitement

        $this->artisan('videos:sync-bunny-status')->assertSuccessful();
        $this->artisan('videos:sync-bunny-status')->assertSuccessful();

        $this->assertDatabaseCount('app_notifications', 1);
        $this->assertDatabaseHas('app_notifications', ['recipient_id' => $super->id, 'type' => 'video_stuck', 'testimony_id' => $t->id]);
        $this->assertDatabaseMissing('app_notifications', ['recipient_id' => $admin->id]);
        $this->assertNotNull($t->fresh()->video_alerted_at);
        $this->assertNull($recent->fresh()->video_alerted_at);
    }

    public function test_deploy_route_runs_the_bunny_sync(): void
    {
        config(['app.deploy_token' => 'jeton-de-test']);
        $t = $this->video();
        $this->bunnySays(4);

        $this->postJson('/api/v1/_deploy', [], ['X-Deploy-Token' => 'jeton-de-test'])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJson(fn ($json) => $json->where('bunny_sync', fn ($v) => str_contains($v, 'mises à jour : 1'))->etc());
        $this->assertSame(Testimony::VIDEO_READY, $t->fresh()->video_status);
    }

    // ─── Objet « video » de l'API ────────────────────────────────────────────

    public function test_video_object_in_processing_ready_and_failed_states(): void
    {
        $t = $this->video();
        $this->actingAs($this->author)->getJson("/api/v1/testimonies/{$t->id}")->assertOk()
            ->assertJsonPath('data.video.status', 'processing')
            ->assertJsonPath('data.video.playback_url', null)
            ->assertJsonPath('data.video.mp4_url', null)
            ->assertJsonPath('data.video.thumbnail_url', null)
            ->assertJsonPath('data.video.duration', null);

        $this->bunnySays(4, 95, '240p,480p,720p,1080p');
        $this->webhook(3);
        $base = 'https://' . self::CDN . '/' . self::GUID;
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/testimonies/{$t->id}")->assertOk()
            ->assertJsonPath('data.video.status', 'ready')
            ->assertJsonPath('data.video.playback_url', "{$base}/playlist.m3u8")
            ->assertJsonPath('data.video.mp4_url', "{$base}/play_720p.mp4") // meilleure qualité MP4 de secours
            ->assertJsonPath('data.video.thumbnail_url', "{$base}/thumbnail.jpg")
            ->assertJsonPath('data.video.duration', 95)
            ->assertJsonPath('data.mediaUrl', "{$base}/playlist.m3u8");

        // MP4 de secours désactivé dans la bibliothèque : pas de mp4_url.
        config(['services.bunny_stream.mp4_fallback' => false]);
        $this->getJson("/api/v1/testimonies/{$t->id}")->assertJsonPath('data.video.mp4_url', null);

        $failed = $this->video(Testimony::VIDEO_FAILED, ['bunny_video_id' => 'dddddddd-0000-0000-0000-000000000000']);
        $this->actingAs($this->author)->getJson("/api/v1/testimonies/{$failed->id}")->assertOk()
            ->assertJsonPath('data.video.status', 'failed')
            ->assertJsonPath('data.video.playback_url', null)
            ->assertJsonPath('data.video.mp4_url', null);
    }

    // ─── Site : envoi direct, lecture HLS ────────────────────────────────────

    public function test_web_form_prepares_direct_bunny_upload_without_receiving_the_file(): void
    {
        Http::fake(['video.bunnycdn.com/library/' . self::LIBRARY . '/videos' => Http::response(['guid' => self::GUID])]);

        $res = $this->actingAs($this->author)->postJson('/testimonies', [
            'title' => 'Guérie du dos', 'type' => 'video', 'category' => 'guerison', 'consent_given' => '1', 'bunny_upload' => '1',
        ])->assertCreated();

        $t = Testimony::findOrFail($res->json('testimony_id'));
        $this->assertSame(Testimony::VIDEO_UPLOADING, $t->video_status);
        $this->assertSame(self::GUID, $t->bunny_video_id);
        $this->assertNull($t->media_url);
        $this->assertSame(self::GUID, $res->json('upload.bunny_video_id'));
        $this->assertSame('https://video.bunnycdn.com/tusupload', $res->json('upload.tus_endpoint'));
        $this->assertArrayNotHasKey('api_key', $res->json('upload'));
        // La clé API ne quitte jamais le serveur : seule une signature (SHA-256) est transmise.
        $this->assertStringNotContainsString('"cle"', json_encode($res->json()));
        $this->assertSame(64, strlen($res->json('upload.signature')));

        $this->actingAs($this->author)->postJson($res->json('signature_url'))->assertOk()->assertJsonStructure(['data' => ['signature', 'expiration']]);
        $this->actingAs($this->author)->postJson($res->json('complete_url'))->assertOk();
        $this->assertSame(Testimony::VIDEO_PROCESSING, $t->fresh()->video_status);
        $this->assertNotNull($t->fresh()->video_processing_at);

        // Page du témoignage pendant le traitement (auteur).
        $this->actingAs($this->author)->get("/testimonies/{$t->id}")->assertOk()->assertSee('Vidéo en cours de traitement');
    }

    public function test_watch_page_plays_ready_bunny_video_with_hls(): void
    {
        $t = $this->video();
        $this->bunnySays(4);
        $this->webhook(3);

        $html = $this->get("/videos/{$t->id}")->assertOk()->getContent();
        $this->assertStringContainsString('data-hls="https://' . self::CDN . '/' . self::GUID . '/playlist.m3u8"', $html);
        $this->assertStringNotContainsString('<source src="https://' . self::CDN, $html);
        // Secours : meilleur MP4 Bunny (720p au plus), jamais la playlist.
        $this->assertStringContainsString('data-original="https://' . self::CDN . '/' . self::GUID . '/play_720p.mp4"', $html);
    }
}

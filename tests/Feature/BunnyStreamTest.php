<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotificationJob;
use App\Models\Category;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Vidéos des témoignages hébergées par Bunny Stream (docs/fonctionnalites/videos-bunny-stream.md). */
class BunnyStreamTest extends TestCase
{
    use RefreshDatabase;

    private const LIBRARY = '777434';
    private const KEY     = 'cle-api-de-test';
    private const CDN     = 'vz-test.b-cdn.net';
    private const GUID    = '11111111-2222-3333-4444-555555555555';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.bunny_stream' => ['library_id' => self::LIBRARY, 'api_key' => self::KEY, 'cdn_host' => self::CDN]]);
        Category::create(['name' => 'Guérison', 'slug' => 'guerison', 'is_active' => true, 'display_order' => 1]);
        Queue::fake([SendPushNotificationJob::class]);
    }

    private function user(string $name): User
    {
        return User::create([
            'display_name' => $name, 'email' => str()->slug($name) . '@example.com', 'password' => 'secret123',
            'role' => 'utilisateur', 'status' => 'active',
        ]);
    }

    private function bunnyTestimony(User $author, string $videoStatus, array $extra = []): Testimony
    {
        return Testimony::create(array_merge([
            'user_id' => $author->id, 'title' => 'Ma guérison', 'type' => 'video', 'category_slug' => 'guerison',
            'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
            'bunny_video_id' => self::GUID, 'video_status' => $videoStatus,
        ], $extra));
    }

    private function fakeBunnyVideo(int $status, int $length = 42, string $resolutions = '240p,360p,720p'): void
    {
        Http::fake([
            'video.bunnycdn.com/library/' . self::LIBRARY . '/videos/' . self::GUID => Http::response([
                'guid' => self::GUID, 'status' => $status, 'length' => $length, 'availableResolutions' => $resolutions,
            ]),
        ]);
    }

    private function publicIds(): array
    {
        return collect($this->getJson('/api/v1/testimonies')->assertOk()->json('data'))->pluck('id')->all();
    }

    // ─── upload-init ───────────────────────────────────────────────────────

    public function test_upload_init_creates_uploading_testimony_and_returns_valid_signature(): void
    {
        Http::fake(['video.bunnycdn.com/library/' . self::LIBRARY . '/videos' => Http::response(['guid' => self::GUID])]);
        $author = $this->user('Marie');

        $res = $this->actingAs($author)->postJson('/api/v1/videos/upload-init', [
            'title' => 'Ma guérison', 'category' => 'guerison', 'body_text' => 'Mon histoire', 'tags' => ['foi'],
        ])->assertCreated();

        $data = $res->json('data');
        $testimony = Testimony::findOrFail($data['testimony_id']);

        $this->assertSame($testimony->id, $data['video_id']);
        $this->assertSame((int) self::LIBRARY, $data['library_id']);
        $this->assertSame(self::GUID, $data['bunny_video_id']);
        $this->assertSame('https://video.bunnycdn.com/tusupload', $data['tus_endpoint']);
        $this->assertEqualsWithDelta(time() + 7200, $data['expiration'], 5);
        $this->assertSame(hash('sha256', self::LIBRARY . self::KEY . $data['expiration'] . self::GUID), $data['signature']);

        $this->assertSame('video', $testimony->type->value);
        $this->assertSame(Testimony::VIDEO_UPLOADING, $testimony->video_status);
        $this->assertSame(self::GUID, $testimony->bunny_video_id);
        $this->assertSame('pending', $testimony->status->value);

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST'
            && $r->url() === 'https://video.bunnycdn.com/library/' . self::LIBRARY . '/videos'
            && $r->header('AccessKey') === [self::KEY]
            && $r['title'] === 'Ma guérison');

        // Même approuvé, un témoignage en cours d'envoi reste hors des listes publiques et de la modération.
        $testimony->update(['status' => 'approved', 'approved_at' => now()]);
        $this->assertNotContains($testimony->id, $this->publicIds());
        $this->assertSame(0, Testimony::pending()->count());
        // L'auteur la voit, avec son état.
        $this->actingAs($author)->getJson("/api/v1/testimonies/{$testimony->id}")->assertOk()
            ->assertJsonPath('data.video.status', 'uploading')
            ->assertJsonPath('data.video.hls_url', null);
    }

    public function test_upload_init_uses_testimony_validation_rules(): void
    {
        Http::fake();

        $this->actingAs($this->user('Marie'))->postJson('/api/v1/videos/upload-init', ['category' => 'guerison'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);

        Http::assertNothingSent();
        $this->assertSame(0, Testimony::count());
    }

    public function test_upload_init_keeps_nothing_when_bunny_fails(): void
    {
        Http::fake(['video.bunnycdn.com/*' => Http::response(['message' => 'Unauthorized'], 401)]);

        $this->actingAs($this->user('Marie'))->postJson('/api/v1/videos/upload-init', [
            'title' => 'Ma guérison', 'category' => 'guerison',
        ])->assertStatus(502);

        $this->assertSame(0, Testimony::withTrashed()->count());
    }

    // ─── upload-signature ──────────────────────────────────────────────────

    public function test_upload_signature_renews_authorization_for_the_same_video(): void
    {
        $author = $this->user('Marie');
        $testimony = $this->bunnyTestimony($author, Testimony::VIDEO_UPLOADING);

        $data = $this->actingAs($author)->postJson("/api/v1/videos/{$testimony->id}/upload-signature")
            ->assertOk()->json('data');

        $this->assertSame(['signature', 'expiration'], array_keys($data));
        $this->assertEqualsWithDelta(time() + 7200, $data['expiration'], 5);
        $this->assertSame(hash('sha256', self::LIBRARY . self::KEY . $data['expiration'] . self::GUID), $data['signature']);
    }

    public function test_upload_signature_is_refused_to_others_and_after_upload(): void
    {
        $author = $this->user('Marie');
        $testimony = $this->bunnyTestimony($author, Testimony::VIDEO_UPLOADING);

        $this->actingAs($this->user('Paul'))->postJson("/api/v1/videos/{$testimony->id}/upload-signature")->assertForbidden();

        $testimony->update(['video_status' => Testimony::VIDEO_PROCESSING]);
        $this->actingAs($author)->postJson("/api/v1/videos/{$testimony->id}/upload-signature")->assertStatus(409);
    }

    // ─── upload-complete ───────────────────────────────────────────────────

    public function test_upload_complete_is_refused_to_another_user(): void
    {
        $author = $this->user('Marie');
        $testimony = $this->bunnyTestimony($author, Testimony::VIDEO_UPLOADING);

        $this->actingAs($this->user('Paul'))->postJson("/api/v1/videos/{$testimony->id}/upload-complete")->assertForbidden();
        $this->assertSame(Testimony::VIDEO_UPLOADING, $testimony->fresh()->video_status);

        $this->actingAs($author)->postJson("/api/v1/videos/{$testimony->id}/upload-complete")->assertOk()
            ->assertJsonPath('data.video.status', 'processing');
        $this->assertSame(Testimony::VIDEO_PROCESSING, $testimony->fresh()->video_status);
    }

    // ─── Webhook ───────────────────────────────────────────────────────────

    public function test_webhook_marks_video_ready_after_checking_bunny_and_notifies_author(): void
    {
        $author = $this->user('Marie');
        $testimony = $this->bunnyTestimony($author, Testimony::VIDEO_PROCESSING);
        $this->fakeBunnyVideo(4, 42, '240p,360p,720p');
        $this->assertNotContains($testimony->id, $this->publicIds());

        $this->postJson('/api/v1/webhooks/bunny', ['VideoLibraryId' => (int) self::LIBRARY, 'VideoGuid' => self::GUID, 'Status' => 3])
            ->assertOk();

        $testimony->refresh();
        $this->assertSame(Testimony::VIDEO_READY, $testimony->video_status);
        $this->assertSame(42, $testimony->duration);
        $this->assertSame(42, $testimony->duration_sec);
        $this->assertSame('240p,360p,720p', $testimony->available_resolutions);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'GET' && $r->header('AccessKey') === [self::KEY]);

        Queue::assertPushed(SendPushNotificationJob::class, 1);
        Queue::assertPushed(SendPushNotificationJob::class, fn ($job) => $job->userId === $author->id
            && $job->data === ['type' => 'video_ready', 'testimony_id' => $testimony->id]);

        // Publiée : dans les listes, avec l'objet « video » (MP4 des seules qualités produites).
        $this->assertContains($testimony->id, $this->publicIds());
        $video = $this->getJson("/api/v1/testimonies/{$testimony->id}")->assertOk()->json('data.video');
        $base = 'https://' . self::CDN . '/' . self::GUID;
        $this->assertSame([
            'status'        => 'ready',
            'hls_url'       => "{$base}/playlist.m3u8",
            'thumbnail_url' => "{$base}/thumbnail.jpg",
            'mp4_urls'      => ['240' => "{$base}/play_240p.mp4", '360' => "{$base}/play_360p.mp4", '720' => "{$base}/play_720p.mp4"],
            'duration'      => 42,
        ], $video);

        // Webhook suivant (autre qualité encodée) : pas de seconde notification.
        $this->postJson('/api/v1/webhooks/bunny', ['VideoLibraryId' => (int) self::LIBRARY, 'VideoGuid' => self::GUID, 'Status' => 4])
            ->assertOk();
        Queue::assertPushed(SendPushNotificationJob::class, 1);
    }

    public function test_webhook_from_another_library_is_ignored(): void
    {
        $testimony = $this->bunnyTestimony($this->user('Marie'), Testimony::VIDEO_PROCESSING);
        Http::fake();

        $this->postJson('/api/v1/webhooks/bunny', ['VideoLibraryId' => 999999, 'VideoGuid' => self::GUID, 'Status' => 3])
            ->assertOk();

        Http::assertNothingSent();
        $this->assertSame(Testimony::VIDEO_PROCESSING, $testimony->fresh()->video_status);
        Queue::assertNothingPushed();
    }

    public function test_webhook_for_unknown_video_is_ignored(): void
    {
        Http::fake();

        $this->postJson('/api/v1/webhooks/bunny', ['VideoLibraryId' => (int) self::LIBRARY, 'VideoGuid' => 'inconnu', 'Status' => 3])
            ->assertOk();

        Http::assertNothingSent();
    }

    public function test_webhook_payload_is_never_trusted_alone(): void
    {
        $testimony = $this->bunnyTestimony($this->user('Marie'), Testimony::VIDEO_PROCESSING);
        $this->fakeBunnyVideo(2, 0, ''); // Bunny encode encore, quoi qu'en dise le webhook

        $this->postJson('/api/v1/webhooks/bunny', ['VideoLibraryId' => (int) self::LIBRARY, 'VideoGuid' => self::GUID, 'Status' => 3])
            ->assertOk();

        $this->assertSame(Testimony::VIDEO_PROCESSING, $testimony->fresh()->video_status);
        Queue::assertNothingPushed();
    }

    public function test_webhook_marks_failed_video(): void
    {
        $testimony = $this->bunnyTestimony($this->user('Marie'), Testimony::VIDEO_PROCESSING);
        $this->fakeBunnyVideo(5, 0, '');

        $this->postJson('/api/v1/webhooks/bunny', ['VideoLibraryId' => (int) self::LIBRARY, 'VideoGuid' => self::GUID, 'Status' => 5])
            ->assertOk();

        $this->assertSame(Testimony::VIDEO_FAILED, $testimony->fresh()->video_status);
        Queue::assertNothingPushed();
    }

    // ─── Ressource ─────────────────────────────────────────────────────────

    public function test_video_object_is_null_without_video(): void
    {
        $text = Testimony::create([
            'user_id' => $this->user('Marie')->id, 'title' => 'Texte', 'type' => 'text', 'category_slug' => 'guerison',
            'body_text' => 'Mon histoire', 'visibility' => 'public', 'status' => 'approved', 'approved_at' => now(),
        ]);

        $this->getJson("/api/v1/testimonies/{$text->id}")->assertOk()->assertJsonPath('data.video', null);
    }

    // ─── Envois abandonnés, suppression, reprise des anciennes vidéos ─────

    public function test_stale_uploads_are_marked_failed_and_deleted_at_bunny(): void
    {
        Http::fake(['video.bunnycdn.com/*' => Http::response(['success' => true])]);
        $author = $this->user('Marie');
        $stale  = $this->bunnyTestimony($author, Testimony::VIDEO_UPLOADING);
        $stale->forceFill(['created_at' => now()->subHours(25)])->save();
        $recent = $this->bunnyTestimony($author, Testimony::VIDEO_UPLOADING, ['bunny_video_id' => 'recent-guid']);

        $this->artisan('videos:expire-uploads')->assertSuccessful();

        $this->assertSame(Testimony::VIDEO_FAILED, $stale->fresh()->video_status);
        $this->assertNull($stale->fresh()->bunny_video_id);
        $this->assertSame(Testimony::VIDEO_UPLOADING, $recent->fresh()->video_status);
        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE'
            && $r->url() === 'https://video.bunnycdn.com/library/' . self::LIBRARY . '/videos/' . self::GUID);
    }

    /** Corbeille (restaurable) : la vidéo Bunny est gardée ; suppression définitive : supprimée, même si Bunny échoue. */
    public function test_deleting_a_testimony_deletes_its_bunny_video_even_if_bunny_fails(): void
    {
        Http::fake(['video.bunnycdn.com/*' => Http::response('Erreur', 500)]);
        $author = $this->user('Marie');
        $testimony = $this->bunnyTestimony($author, Testimony::VIDEO_READY);

        $this->actingAs($author)->deleteJson("/api/v1/testimonies/{$testimony->id}")->assertOk();

        $this->assertSoftDeleted($testimony);
        Http::assertNothingSent();

        // Suppression définitive : super-administrateur, depuis la corbeille du back-office.
        $super = $this->user('Super');
        $super->forceFill(['role' => 'administrateur', 'is_super_admin' => true])->save();
        $this->actingAs($super)->delete("/moderation/{$testimony->id}/definitif")->assertRedirect();
        $this->assertDatabaseMissing('testimonies', ['id' => $testimony->id]);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/videos/' . self::GUID));
    }

    public function test_migrate_to_bunny_asks_bunny_to_fetch_old_videos(): void
    {
        Http::fake(['video.bunnycdn.com/library/' . self::LIBRARY . '/videos/fetch' => Http::response(['guid' => self::GUID])]);
        $old = Testimony::create([
            'user_id' => $this->user('Marie')->id, 'title' => 'Ancienne vidéo', 'type' => 'video', 'category_slug' => 'guerison',
            'media_url' => 'http://ancien-domaine.test/storage/media/video.mp4', 'visibility' => 'public', 'status' => 'approved',
        ]);

        $this->artisan('videos:migrate-to-bunny', ['--dry-run' => true])->assertSuccessful();
        Http::assertNothingSent();
        $this->assertNull($old->fresh()->bunny_video_id);

        $this->artisan('videos:migrate-to-bunny')->assertSuccessful();
        $old->refresh();
        $this->assertSame(self::GUID, $old->bunny_video_id);
        $this->assertSame(Testimony::VIDEO_PROCESSING, $old->video_status);
        Http::assertSent(fn (HttpRequest $r) => $r['url'] === asset('storage/media/video.mp4'));

        // Ancien fichier toujours lu pendant la reprise ; aucune notification quand Bunny a terminé.
        $this->assertContains($old->id, $this->publicIds());
        $this->fakeBunnyVideo(4);
        $this->postJson('/api/v1/webhooks/bunny', ['VideoLibraryId' => (int) self::LIBRARY, 'VideoGuid' => self::GUID, 'Status' => 3]);
        $this->assertSame(Testimony::VIDEO_READY, $old->fresh()->video_status);
        Queue::assertNothingPushed();
    }
}

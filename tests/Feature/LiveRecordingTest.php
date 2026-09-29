<?php

namespace Tests\Feature;

use App\Jobs\TranscodeMediaJob;
use App\Models\LiveSession;
use App\Models\MediaFile;
use App\Models\Testimony;
use App\Models\User;
use App\Services\LiveKit\LiveKitClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Enregistrement des directs → témoignage vidéo à relire (docs/fonctionnalites/lives.md). */
class LiveRecordingTest extends TestCase
{
    use RefreshDatabase;

    private User $host;
    private array $egressResponse = ['egress_id' => 'EG_test', 'status' => 'EGRESS_STARTING'];
    private int $egressStatus = 200;
    private ?array $listEgress = null;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'livekit.url' => 'wss://lk.test', 'livekit.api_key' => 'devkey', 'livekit.api_secret' => 'secret-de-test-suffisamment-long',
            'livekit.recording.enabled' => true,
            'livekit.recording.public_url' => 'https://videos.exemple.org/',
            'livekit.recording.s3' => ['access_key' => 'AK', 'secret' => 'SK', 'bucket' => 'testiapp-videos', 'region' => 'auto', 'endpoint' => 'https://r2.exemple.com', 'force_path_style' => true],
        ]);
        $this->app->forgetInstance(LiveKitClient::class);
        Storage::fake(MediaFile::RECORDINGS_DISK); // conversion en file « sync » : jamais de vrai S3

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_ends_with($url, 'StartRoomCompositeEgress')) {
                return Http::response($this->egressResponse, $this->egressStatus);
            }
            if (str_ends_with($url, 'ListEgress')) {
                return Http::response(['items' => $this->listEgress ? [$this->listEgress] : []]);
            }
            return Http::response(['participants' => []]);
        });

        $this->host = User::create(['display_name' => 'Pasteur', 'email' => 'mod@example.com', 'password' => 'secret123', 'role' => 'moderateur', 'status' => 'active']);
    }

    private function goLive(array $extra = []): LiveSession
    {
        $this->actingAs($this->host)->postJson('/api/v1/lives', array_merge(['title' => 'Soirée de louange', 'description' => 'Présentation'], $extra))->assertCreated();
        $live = LiveSession::firstOrFail();
        $this->actingAs($this->host)->postJson("/lives/{$live->id}/go-live")->assertOk();

        return $live->fresh();
    }

    private function egressEnded(string $status = 'EGRESS_COMPLETE', int $seconds = 125, bool $asWebhook = true): array
    {
        return [
            'egressId' => 'EG_test', 'status' => $status,
            'fileResults' => [['filename' => 'x.mp4', 'duration' => (string) ($seconds * 1_000_000_000), 'size' => '1048576']],
        ];
    }

    private function webhook(array $event)
    {
        $body = json_encode($event);
        $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $head = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $claims = $b64(json_encode(['iss' => 'devkey', 'exp' => time() + 60, 'sha256' => base64_encode(hash('sha256', $body, true))]));
        $jwt = "{$head}.{$claims}." . $b64(hash_hmac('sha256', "{$head}.{$claims}", config('livekit.api_secret'), true));

        return $this->call('POST', '/api/v1/livekit/webhook', [], [], [], ['HTTP_AUTHORIZATION' => $jwt, 'CONTENT_TYPE' => 'application/webhook+json'], $body);
    }

    public function test_going_live_starts_recording_to_the_configured_storage(): void
    {
        $live = $this->goLive();

        $this->assertSame('recording', $live->recording_status);
        $this->assertSame('EG_test', $live->egress_id);
        $this->assertMatchesRegularExpression('#^lives/\d{4}/\d{2}/' . $live->id . '\.mp4$#', $live->recording_path);

        $sent = Http::recorded()->first(fn ($p) => str_ends_with($p[0]->url(), 'StartRoomCompositeEgress'))[0];
        $this->assertSame($live->room_name, $sent['room_name']);
        $this->assertSame('MP4', $sent['file_outputs'][0]['file_type']);
        $this->assertSame('testiapp-videos', $sent['file_outputs'][0]['s3']['bucket']);
        $this->assertTrue($sent['file_outputs'][0]['s3']['force_path_style']);
    }

    public function test_recording_can_be_turned_off_or_is_off_without_storage(): void
    {
        $live = $this->goLive(['record' => false]);
        $this->assertFalse($live->record);
        $this->assertNull($live->egress_id);

        $this->actingAs($this->host)->postJson("/lives/{$live->id}/end")->assertOk();
        config(['livekit.recording.public_url' => null]);
        $this->actingAs($this->host)->postJson('/api/v1/lives', ['title' => 'Sans stockage'])->assertCreated();
        $this->assertFalse(LiveSession::latest()->first()->record);
    }

    public function test_recording_failure_never_blocks_the_live(): void
    {
        $this->egressStatus = 500;
        $live = $this->goLive();

        $this->assertTrue($live->isOnAir());
        $this->assertSame('failed', $live->recording_status);
        $this->assertStringContainsString('Démarrage impossible', $live->recording_error);
    }

    public function test_finished_recording_becomes_a_pending_video_testimony(): void
    {
        $live = $this->goLive();
        $this->actingAs($this->host)->postJson("/lives/{$live->id}/end")->assertOk();
        $this->assertTrue(Http::recorded()->contains(fn ($p) => str_ends_with($p[0]->url(), 'StopEgress')));
        $this->assertSame('processing', $live->fresh()->recording_status);

        $this->webhook(['event' => 'egress_ended', 'egressInfo' => $this->egressEnded()])->assertNoContent();
        $this->webhook(['event' => 'egress_ended', 'egressInfo' => $this->egressEnded()])->assertNoContent(); // rejoué : pas de doublon

        $this->assertSame(1, Testimony::count());
        $testimony = Testimony::first();
        $live->refresh();

        $this->assertSame('ready', $live->recording_status);
        $this->assertSame($testimony->id, $live->testimony_id);
        $this->assertSame('video', $testimony->type->value);
        $this->assertSame('pending', $testimony->status->value);
        $this->assertSame('Soirée de louange', $testimony->title);
        $this->assertSame('Présentation', $testimony->body_text);
        $this->assertSame('autre', $testimony->category_slug);
        $this->assertSame(125, $testimony->duration_sec);
        $this->assertSame($this->host->id, $testimony->user_id);
        $this->assertSame('https://videos.exemple.org/' . $live->recording_path, $testimony->media_url);

        // Rediffusion proposée seulement après validation par la modération.
        $this->get('/lives')->assertDontSee('Rediffusion');
        $testimony->update(['status' => 'approved', 'approved_at' => now()]);
        $this->get('/lives')->assertSee('Rediffusion');
        $this->getJson("/api/v1/lives/{$live->id}")->assertJsonPath('data.recording.replayTestimonyId', $testimony->id);
    }

    public function test_finished_recording_queues_the_transcoding_of_its_media_file(): void
    {
        Queue::fake();
        config(['media.transcoding_enabled' => true]);
        $live = $this->goLive();
        $this->actingAs($this->host)->postJson("/lives/{$live->id}/end")->assertOk();

        $this->webhook(['event' => 'egress_ended', 'egressInfo' => $this->egressEnded()])->assertNoContent();
        $this->webhook(['event' => 'egress_ended', 'egressInfo' => $this->egressEnded()])->assertNoContent(); // rejoué

        $live->refresh();
        $media = MediaFile::sole();
        $this->assertSame(MediaFile::RECORDINGS_DISK, $media->disk);
        $this->assertSame($live->recording_path, $media->path);
        $this->assertSame(Testimony::first()->media_url, $media->url);
        $this->assertSame(
            ['video', 'video/mp4', 125, 1048576, $this->host->id],
            [$media->type, $media->mime_type, $media->duration_sec, $media->size_bytes, $media->user_id]
        );
        $this->assertSame(MediaFile::STATUS_PENDING, $media->processing_status);
        Queue::assertPushed(TranscodeMediaJob::class, 1);
        Queue::assertPushed(TranscodeMediaJob::class, fn ($job) => $job->mediaFileId === $media->id);
    }

    public function test_too_short_or_failed_recordings_do_not_create_testimonies(): void
    {
        $live = $this->goLive();
        $this->actingAs($this->host)->postJson("/lives/{$live->id}/end")->assertOk();

        $this->webhook(['event' => 'egress_ended', 'egressInfo' => $this->egressEnded('EGRESS_COMPLETE', 5)])->assertNoContent();
        $this->assertSame('too_short', $live->fresh()->recording_status);
        $this->assertSame(0, Testimony::count());

        $live->update(['recording_status' => 'processing']);
        $this->webhook(['event' => 'egress_ended', 'egressInfo' => ['egressId' => 'EG_test', 'status' => 'EGRESS_FAILED', 'error' => 'bucket inaccessible']])->assertNoContent();
        $this->assertSame('failed', $live->fresh()->recording_status);
        $this->assertSame('bucket inaccessible', $live->fresh()->recording_error);
    }

    public function test_cleanup_recovers_recordings_when_the_webhook_is_missing(): void
    {
        $live = $this->goLive();
        $this->actingAs($this->host)->postJson("/lives/{$live->id}/end")->assertOk();

        $this->listEgress = ['egress_id' => 'EG_test', 'status' => 3, 'file_results' => [['duration' => '60000000000', 'size' => '2048']]];
        $this->artisan('lives:cleanup')->assertSuccessful();

        $this->assertSame('ready', $live->fresh()->recording_status);
        $this->assertSame(60, Testimony::first()->duration_sec);
    }
}

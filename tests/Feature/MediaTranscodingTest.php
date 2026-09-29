<?php

namespace Tests\Feature;

use App\Http\Resources\TestimonyResource;
use App\Jobs\TranscodeMediaJob;
use App\Models\MediaFile;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Qualités des médias : docs/fonctionnalites/qualites-media.md */
class MediaTranscodingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['media.transcoding_enabled' => true]);

        $this->user = User::create(['display_name' => 'Jean Test', 'email' => 'jean@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active']);
    }

    // ---------- Aides ----------

    /**
     * Simule ffprobe (sortie JSON donnée) et ffmpeg (écrit le fichier de sortie, dernier argument),
     * ou ffmpeg en échec si $ffmpegFails.
     */
    private function fakeFfmpeg(array $probe, bool $ffmpegFails = false): void
    {
        Process::fake(['*' => function (PendingProcess $process) use ($probe, $ffmpegFails) {
            $command = (array) $process->command;

            if (str_contains((string) $command[0], 'ffprobe')) {
                return Process::result(json_encode($probe));
            }

            if ($ffmpegFails) {
                return Process::result('', 'Erreur simulée', 1);
            }

            file_put_contents(end($command), 'contenu converti');

            return Process::result();
        }]);
    }

    private function videoProbe(int $width, int $height, array $tags = []): array
    {
        return [
            'streams' => [
                ['codec_type' => 'video', 'width' => $width, 'height' => $height, 'tags' => $tags],
                ['codec_type' => 'audio', 'bit_rate' => '128000'],
            ],
            'format' => ['duration' => '42.4', 'bit_rate' => '3000000'],
        ];
    }

    private function audioProbe(int $bitrate): array
    {
        return [
            'streams' => [['codec_type' => 'audio', 'bit_rate' => (string) $bitrate]],
            'format'  => ['duration' => '95.6', 'bit_rate' => (string) $bitrate],
        ];
    }

    private function makeMedia(string $type, string $path): MediaFile
    {
        Storage::disk('public')->put($path, 'original');

        return MediaFile::create([
            'user_id' => $this->user->id, 'disk' => 'public', 'path' => $path,
            'url' => asset('storage/' . $path), 'mime_type' => $type === 'video' ? 'video/mp4' : 'audio/mpeg',
            'type' => $type, 'size_bytes' => 8,
        ]);
    }

    private function makeTestimony(string $type, ?string $mediaUrl): Testimony
    {
        return Testimony::create([
            'user_id' => $this->user->id, 'title' => 'Titre', 'type' => $type, 'category_slug' => 'guerison',
            'media_url' => $mediaUrl, 'visibility' => 'public', 'status' => 'approved',
        ]);
    }

    private function resourceOf(Testimony $testimony): array
    {
        return (new TestimonyResource($testimony->fresh()->load('user')))->toArray(Request::create('/'));
    }

    // ---------- Envoi ----------

    public function test_audio_and_video_uploads_queue_the_transcoding_job(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->user, 'sanctum')->post('/api/v1/media/upload', [
            'file' => UploadedFile::fake()->create('clip.mp4', 200, 'video/mp4'),
        ]);

        $response->assertCreated();
        $media = MediaFile::findOrFail($response->json('data.id'));
        $this->assertSame(MediaFile::STATUS_PENDING, $media->processing_status);
        Queue::assertPushed(TranscodeMediaJob::class, fn ($job) => $job->mediaFileId === $media->id);

        $this->actingAs($this->user, 'sanctum')->post('/api/v1/media/upload', [
            'file' => UploadedFile::fake()->create('voix.mp3', 100, 'audio/mpeg'),
        ])->assertCreated();
        Queue::assertPushed(TranscodeMediaJob::class, 2);
    }

    public function test_images_and_disabled_transcoding_do_not_queue_anything(): void
    {
        Queue::fake();

        $this->actingAs($this->user, 'sanctum')->post('/api/v1/media/upload', [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertCreated();

        config(['media.transcoding_enabled' => false]);
        $this->actingAs($this->user, 'sanctum')->post('/api/v1/media/upload', [
            'file' => UploadedFile::fake()->create('clip.mp4', 200, 'video/mp4'),
        ])->assertCreated();

        Queue::assertNothingPushed();
        $this->assertSame(MediaFile::STATUS_NONE, MediaFile::where('type', 'video')->first()->processing_status);
    }

    // ---------- Conversion ----------

    public function test_video_renditions_are_saved_on_media_file_testimony_and_api(): void
    {
        $this->fakeFfmpeg($this->videoProbe(1280, 720));
        $media     = $this->makeMedia('video', 'media/videos/clip.mov');
        $testimony = $this->makeTestimony('video', $media->url);

        (new TranscodeMediaJob($media->id))->handle();

        $media->refresh();
        $this->assertSame(MediaFile::STATUS_DONE, $media->processing_status);
        $this->assertSame(42, $media->duration_sec);
        $this->assertSame([1280, 720], [$media->width, $media->height]);
        $this->assertSame(['240p', '360p', '480p', '720p'], array_column($media->renditions, 'quality'));
        Storage::disk('public')->assertExists(['media/videos/clip_240p.mp4', 'media/videos/clip_720p.mp4']);
        Storage::disk('public')->assertExists('media/videos/clip.mov'); // original conservé

        Process::assertRan(fn ($process) => in_array('scale=-2:360', $process->command, true)
            && in_array('800k', $process->command, true)
            && in_array('+faststart', $process->command, true));

        $testimony->refresh();
        $this->assertCount(4, $testimony->renditions);
        $this->assertSame(42, $testimony->duration_sec);

        $data = $this->resourceOf($testimony);
        $this->assertSame($media->url, $data['mediaUrl']);
        $this->assertSame([
            'quality' => '240p', 'height' => 240, 'bitrate' => 400,
            'url' => asset('storage/media/videos/clip_240p.mp4'),
        ], $data['renditions'][0]);
        $this->assertSame([400, 800, 1200, 2500], array_column($data['renditions'], 'bitrate'));
        $this->assertStringStartsWith('http', $data['renditions'][3]['url']);
    }

    public function test_video_ladder_stops_at_source_size_and_keeps_the_lowest(): void
    {
        $this->fakeFfmpeg($this->videoProbe(640, 360));
        $media = $this->makeMedia('video', 'media/videos/petit.mp4');
        (new TranscodeMediaJob($media->id))->handle();
        $this->assertSame(['240p', '360p'], array_column($media->fresh()->renditions, 'quality'));

        $this->fakeFfmpeg($this->videoProbe(176, 144));
        $tiny = $this->makeMedia('video', 'media/videos/minuscule.mp4');
        (new TranscodeMediaJob($tiny->id))->handle();
        $this->assertSame(['240p'], array_column($tiny->fresh()->renditions, 'quality'));
    }

    public function test_rotated_portrait_video_is_scaled_on_its_short_side(): void
    {
        // Vidéo de téléphone : 1920x1080 codée, rotation 90° → affichée en 1080x1920.
        $this->fakeFfmpeg($this->videoProbe(1920, 1080, ['rotate' => '90']));
        $media = $this->makeMedia('video', 'media/videos/portrait.mp4');

        (new TranscodeMediaJob($media->id))->handle();

        $media->refresh();
        $this->assertSame([1080, 1920], [$media->width, $media->height]);
        $this->assertCount(4, $media->renditions);
        Process::assertRan(fn ($process) => in_array('scale=360:-2', $process->command, true));
    }

    public function test_audio_renditions_skip_bitrates_not_lower_than_the_source(): void
    {
        $this->fakeFfmpeg($this->audioProbe(96000));
        $media     = $this->makeMedia('audio', 'media/audios/voix.mp3');
        $testimony = $this->makeTestimony('audio', $media->url);

        (new TranscodeMediaJob($media->id))->handle();

        $media->refresh();
        $this->assertSame(['32k', '64k'], array_column($media->renditions, 'quality'));
        Storage::disk('public')->assertExists('media/audios/voix_64k.m4a');
        Process::assertRan(fn ($process) => in_array('-vn', $process->command, true) && in_array('64k', $process->command, true));

        $data = $this->resourceOf($testimony);
        $this->assertSame([
            ['quality' => '32k', 'bitrate' => 32, 'url' => asset('storage/media/audios/voix_32k.m4a')],
            ['quality' => '64k', 'bitrate' => 64, 'url' => asset('storage/media/audios/voix_64k.m4a')],
        ], $data['renditions']);
        $this->assertSame(96, $data['duration']);
    }

    public function test_ffmpeg_failure_marks_the_file_failed_and_keeps_the_original(): void
    {
        $this->fakeFfmpeg($this->videoProbe(1280, 720), ffmpegFails: true);
        $media     = $this->makeMedia('video', 'media/videos/casse.mp4');
        $testimony = $this->makeTestimony('video', $media->url);

        (new TranscodeMediaJob($media->id))->handle();

        $this->assertSame(MediaFile::STATUS_FAILED, $media->fresh()->processing_status);
        $this->assertNull($testimony->fresh()->renditions);
        $this->assertSame(['media/videos/casse.mp4'], Storage::disk('public')->files('media/videos')); // aucun fichier partiel

        $data = $this->resourceOf($testimony);
        $this->assertSame([], $data['renditions']);
        $this->assertSame($media->url, $data['mediaUrl']);
    }

    public function test_ffprobe_failure_marks_the_file_failed(): void
    {
        Process::fake(['*' => Process::result('', 'introuvable', 1)]);
        $media = $this->makeMedia('audio', 'media/audios/illisible.mp3');

        (new TranscodeMediaJob($media->id))->handle();

        $this->assertSame(MediaFile::STATUS_FAILED, $media->fresh()->processing_status);
        $this->assertNull($media->fresh()->renditions);
    }

    // ---------- Création du témoignage ----------

    public function test_new_testimony_reuses_renditions_of_an_already_processed_file(): void
    {
        $this->fakeFfmpeg($this->videoProbe(854, 480));
        $media = $this->makeMedia('video', 'media/videos/pret.mp4');
        (new TranscodeMediaJob($media->id))->handle();

        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/testimonies', [
            'title' => 'Ma guérison', 'type' => 'video', 'category' => 'guerison', 'media_url' => $media->url,
        ]);

        $response->assertCreated();
        $this->assertSame(['240p', '360p', '480p'], array_column($response->json('data.renditions'), 'quality'));
        $this->assertSame(42, $response->json('data.duration'));
    }

    public function test_text_testimony_has_empty_renditions(): void
    {
        $testimony = $this->makeTestimony('text', null);

        $this->assertSame([], $this->resourceOf($testimony)['renditions']);
    }

    // ---------- Commande de rattrapage ----------

    public function test_command_registers_testimony_files_and_queues_missing_ones(): void
    {
        Queue::fake();

        // Fichier envoyé avant la fonctionnalité (sans ligne media_files), APP_URL modifié depuis.
        Storage::disk('public')->put('media/ancien.mp4', 'original');
        $old = $this->makeTestimony('video', 'https://ancien-domaine.org/storage/media/ancien.mp4');

        $done = $this->makeMedia('video', 'media/videos/deja.mp4');
        $done->update(['processing_status' => MediaFile::STATUS_DONE, 'renditions' => []]);

        Artisan::call('media:transcode', ['--missing' => true]);

        $media = MediaFile::where('path', 'media/ancien.mp4')->firstOrFail();
        $this->assertSame('video', $media->type);
        $this->assertSame($old->media_url, $media->url);
        Queue::assertPushed(TranscodeMediaJob::class, 1);
        Queue::assertPushed(TranscodeMediaJob::class, fn ($job) => $job->mediaFileId === $media->id);
    }

    public function test_command_sync_updates_testimony_found_by_storage_path(): void
    {
        $this->fakeFfmpeg($this->audioProbe(192000));
        Storage::disk('public')->put('media/ancien.mp3', 'original');
        $testimony = $this->makeTestimony('audio', 'https://ancien-domaine.org/storage/media/ancien.mp3');

        Artisan::call('media:transcode', ['--sync' => true]);

        $this->assertSame(['32k', '64k', '128k'], array_column($testimony->fresh()->renditions, 'quality'));
    }

    // ---------- Disque non local (enregistrements de directs sur S3) ----------

    private function useRecordingsDisk(): string
    {
        Storage::fake(MediaFile::RECORDINGS_DISK);
        config(['livekit.recording.public_url' => 'https://videos.exemple.org/']);

        $tmp = storage_path('framework/testing/transcode-' . uniqid());
        config(['media.temp_directory' => $tmp]);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($tmp));

        return $tmp;
    }

    private function makeRecording(string $path): MediaFile
    {
        Storage::disk(MediaFile::RECORDINGS_DISK)->put($path, 'original');

        return MediaFile::forRecording($path, $this->user->id, null, 0, 8);
    }

    public function test_recording_on_remote_disk_is_downloaded_converted_and_uploaded_next_to_the_original(): void
    {
        $tmp = $this->useRecordingsDisk();
        $this->fakeFfmpeg($this->videoProbe(1280, 720));
        $media     = $this->makeRecording('lives/2026/09/abc.mp4');
        $testimony = $this->makeTestimony('video', 'https://videos.exemple.org/lives/2026/09/abc.mp4');
        $this->assertSame($testimony->media_url, $media->url);

        (new TranscodeMediaJob($media->id))->handle();

        $media->refresh();
        $this->assertSame(MediaFile::STATUS_DONE, $media->processing_status);
        $this->assertSame(['240p', '360p', '480p', '720p'], array_column($media->renditions, 'quality'));
        $this->assertSame([MediaFile::RECORDINGS_DISK], array_values(array_unique(array_column($media->renditions, 'disk'))));

        $disk = Storage::disk(MediaFile::RECORDINGS_DISK);
        $disk->assertExists(['lives/2026/09/abc.mp4', 'lives/2026/09/abc_240p.mp4', 'lives/2026/09/abc_720p.mp4']);
        $this->assertSame('contenu converti', $disk->get('lives/2026/09/abc_360p.mp4'));
        $this->assertSame('original', $disk->get('lives/2026/09/abc.mp4'));
        $this->assertSame([], Storage::disk('public')->allFiles());

        // ffprobe et ffmpeg travaillent sur une copie locale, dans le dossier de travail, supprimé ensuite.
        $local = fn ($process) => collect((array) $process->command)->contains(fn ($arg) => str_starts_with((string) $arg, $tmp));
        Process::assertRan(fn ($process) => str_contains((string) $process->command[0], 'ffprobe') && $local($process));
        Process::assertRan(fn ($process) => in_array('scale=-2:360', $process->command, true) && $local($process));
        $this->assertSame([], glob($tmp . '/*'));

        $testimony->refresh();
        $this->assertCount(4, $testimony->renditions);

        $data = $this->resourceOf($testimony);
        $this->assertSame('https://videos.exemple.org/lives/2026/09/abc.mp4', $data['mediaUrl']);
        $this->assertSame([
            'quality' => '240p', 'height' => 240, 'bitrate' => 400,
            'url' => 'https://videos.exemple.org/lives/2026/09/abc_240p.mp4',
        ], $data['renditions'][0]);
        $this->assertSame(42, $data['duration']);
    }

    public function test_remote_ffmpeg_failure_uploads_nothing_and_cleans_the_work_directory(): void
    {
        $tmp = $this->useRecordingsDisk();
        $this->fakeFfmpeg($this->videoProbe(1280, 720), ffmpegFails: true);
        $media = $this->makeRecording('lives/2026/09/casse.mp4');

        (new TranscodeMediaJob($media->id))->handle();

        $this->assertSame(MediaFile::STATUS_FAILED, $media->fresh()->processing_status);
        $this->assertSame(['lives/2026/09/casse.mp4'], Storage::disk(MediaFile::RECORDINGS_DISK)->allFiles());
        $this->assertSame([], glob($tmp . '/*'));
    }

    public function test_missing_remote_original_or_unusable_disk_marks_the_file_failed(): void
    {
        $this->useRecordingsDisk();
        Process::fake();
        $absent = MediaFile::forRecording('lives/2026/09/absent.mp4', $this->user->id);
        (new TranscodeMediaJob($absent->id))->handle();
        $this->assertSame(MediaFile::STATUS_FAILED, $absent->fresh()->processing_status);

        // Ex. disque S3 sans le paquet league/flysystem-aws-s3-v3 : pas d'exception, l'original reste lisible.
        config(['filesystems.disks.casse' => ['driver' => 'inexistant']]);
        $broken = MediaFile::create([
            'user_id' => $this->user->id, 'disk' => 'casse', 'path' => 'x.mp4', 'url' => 'https://exemple.org/x.mp4',
            'mime_type' => 'video/mp4', 'type' => 'video', 'size_bytes' => 8,
        ]);
        (new TranscodeMediaJob($broken->id))->handle();
        $this->assertSame(MediaFile::STATUS_FAILED, $broken->fresh()->processing_status);
        Process::assertNothingRan();
    }

    public function test_recording_url_finds_its_media_file(): void
    {
        $this->useRecordingsDisk();
        $media = $this->makeRecording('lives/2026/09/abc.mp4');
        $media->update(['url' => 'https://ancien.exemple.org/lives/2026/09/abc.mp4']);

        $this->assertTrue($media->is(MediaFile::findForUrl('https://videos.exemple.org/lives/2026/09/abc.mp4')));
        $this->assertSame('lives/2026/09/abc.mp4', MediaFile::recordingPathFromUrl('https://videos.exemple.org/lives/2026/09/abc.mp4'));
        $this->assertNull(MediaFile::recordingPathFromUrl('https://autre.org/lives/2026/09/abc.mp4'));
        $this->assertNull(MediaFile::recordingPathFromUrl('https://videos.exemple.org/../secret.mp4'));
    }

    public function test_command_registers_existing_recording_testimonies(): void
    {
        Queue::fake();
        $this->useRecordingsDisk();
        $testimony = $this->makeTestimony('video', 'https://videos.exemple.org/lives/2026/08/ancien.mp4');

        Artisan::call('media:transcode', ['--missing' => true]);

        $media = MediaFile::where('disk', MediaFile::RECORDINGS_DISK)->firstOrFail();
        $this->assertSame('lives/2026/08/ancien.mp4', $media->path);
        $this->assertSame($testimony->media_url, $media->url);
        $this->assertSame('video', $media->type);
        Queue::assertPushed(TranscodeMediaJob::class, fn ($job) => $job->mediaFileId === $media->id);

        // Relancée : pas de doublon.
        Artisan::call('media:transcode', ['--missing' => true]);
        $this->assertSame(1, MediaFile::count());
    }
}

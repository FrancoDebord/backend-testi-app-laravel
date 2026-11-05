<?php

namespace Tests\Feature;

use App\Jobs\TranscodeMediaJob;
use App\Models\MediaFile;
use App\Models\Testimony;
use App\Models\User;
use App\Support\Mp4AudioRepair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Audios MP4 dont la première trame est la configuration AAC (illisibles dans Chrome et Edge) :
 * réparation à l'envoi, commande media:repair-audio, MP4 sans image classé en audio.
 * Voir docs/fonctionnalites/lecture-audio.md
 */
class AudioRepairTest extends TestCase
{
    use RefreshDatabase;

    /** AudioSpecificConfig : AAC LC, 44,1 kHz, mono. */
    private const CONFIG = "\x12\x08";

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->user = User::create(['display_name' => 'Jean Test', 'email' => 'jean@example.com', 'password' => 'secret123', 'role' => 'utilisateur', 'status' => 'active']);
    }

    // ---------- Fichiers MP4 de test ----------

    private static function box(string $type, string $body): string
    {
        return pack('N', strlen($body) + 8) . $type . $body;
    }

    private static function fullBox(string $type, string $body): string
    {
        return self::box($type, "\0\0\0\0" . $body);
    }

    /**
     * MP4 minimal : une piste (son, ou image si $handler = 'vide'), un bloc, échantillons $samples.
     * $moovFirst : moov avant mdat (cas des téléphones) ou après.
     */
    private static function mp4(array $samples, bool $moovFirst = true, string $handler = 'soun'): string
    {
        $ftyp = self::box('ftyp', 'mp42' . pack('N', 0) . 'isommp42');
        $mdat = self::box('mdat', implode('', $samples));
        $esds = self::fullBox('esds',
            "\x03\x19\x00\x01\x00"                                   // ES_Descriptor (25 octets), ES_ID 1
            . "\x04\x11\x40\x15\x00\x03\x00\x00\x00\x01\xF4\x00\x00\x01\xF4" // DecoderConfig (AAC, 17 octets avec le 0x05)
            . "\x05\x02" . self::CONFIG                              // DecoderSpecificInfo
            . "\x06\x01\x02");                                       // SLConfig
        $mp4a = self::box('mp4a', str_repeat("\0", 6) . pack('n', 1) . str_repeat("\0", 8)
            . pack('n', 1) . pack('n', 16) . pack('N', 0) . pack('N', 44100 << 16) . $esds);
        $count = count($samples);

        $moov = function (int $chunkOffset) use ($mp4a, $count, $samples, $handler) {
            $stbl = self::box('stbl',
                self::fullBox('stsd', pack('N', 1) . $mp4a)
                . self::fullBox('stts', pack('N', 1) . pack('N2', $count, 1024))
                . self::fullBox('stsc', pack('N', 1) . pack('N3', 1, $count, 1))
                . self::fullBox('stsz', pack('N', 0) . pack('N', $count) . pack('N*', ...array_map('strlen', $samples)))
                . self::fullBox('stco', pack('N', 1) . pack('N', $chunkOffset)));
            $mdia = self::box('mdia',
                self::fullBox('mdhd', pack('N4', 0, 0, 44100, $count * 1024) . pack('n2', 0, 0))
                . self::fullBox('hdlr', pack('N', 0) . $handler . str_repeat("\0", 12) . "\0")
                . self::box('minf', $stbl));

            return self::box('moov', self::fullBox('mvhd', str_repeat("\0", 96)) . self::box('trak', $mdia));
        };

        if ($moovFirst) {
            $size = strlen($moov(0));
            return $ftyp . $moov(strlen($ftyp) + $size + 8) . $mdat;
        }

        return $ftyp . $mdat . $moov(strlen($ftyp) + 8);
    }

    /** Échantillons : configuration AAC écrite comme son, puis trois trames. */
    private static function brokenSamples(): array
    {
        return [self::CONFIG, str_repeat("\xAA", 10), str_repeat("\xBB", 12), str_repeat("\xCC", 11)];
    }

    /** Tables lues dans le fichier : tailles, position du premier bloc, durées. */
    private static function tables(string $data): array
    {
        $find = function (string $type) use ($data) {
            $pos = strpos($data, $type);
            return substr($data, $pos + 4);
        };
        $stsz = $find('stsz');
        $count = unpack('N', substr($stsz, 8, 4))[1];
        $stco = $find('stco');
        $stts = $find('stts');
        $mdhd = $find('mdhd');

        return [
            'sizes'    => array_values(unpack('N' . $count, substr($stsz, 12, 4 * $count))),
            'offset'   => unpack('N', substr($stco, 8, 4))[1],
            'stts'     => array_values(unpack('N2', substr($stts, 8, 8))),
            'duration' => unpack('N', substr($mdhd, 16, 4))[1],
        ];
    }

    private function write(string $path, string $data): string
    {
        Storage::disk('public')->put($path, $data);

        return Storage::disk('public')->path($path);
    }

    // ---------- Réparation ----------

    public function test_repair_removes_the_config_frame_with_moov_before_data(): void
    {
        $file = $this->write('media/a.mp4', self::mp4(self::brokenSamples()));
        $this->assertTrue(Mp4AudioRepair::needsRepair($file));

        $this->assertTrue(Mp4AudioRepair::repair($file));

        $data = file_get_contents($file);
        $t = self::tables($data);
        $this->assertSame([10, 12, 11], $t['sizes']);
        $this->assertSame(str_repeat("\xAA", 10) . str_repeat("\xBB", 12) . str_repeat("\xCC", 11), substr($data, $t['offset'], 33));
        $this->assertSame([3, 1024], $t['stts']);
        $this->assertSame(3 * 1024, $t['duration']);
        $this->assertFalse(Mp4AudioRepair::needsRepair($file));
        $this->assertFalse(Mp4AudioRepair::repair($file), 'Une seconde réparation ne change rien.');
    }

    public function test_repair_with_moov_after_data(): void
    {
        $file = $this->write('media/b.m4a', self::mp4(self::brokenSamples(), moovFirst: false));

        $this->assertTrue(Mp4AudioRepair::repair($file));

        $data = file_get_contents($file);
        $t = self::tables($data);
        $this->assertSame([10, 12, 11], $t['sizes']);
        $this->assertSame(str_repeat("\xAA", 10), substr($data, $t['offset'], 10));
    }

    public function test_healthy_video_and_unrelated_files_are_left_untouched(): void
    {
        $healthy = self::mp4([str_repeat("\xAA", 10), str_repeat("\xBB", 12)]);
        $video   = self::mp4(self::brokenSamples(), handler: 'vide');
        $files = [
            $this->write('media/sain.mp4', $healthy),
            $this->write('media/video.mp4', $video),
            $this->write('media/texte.mp4', 'pas un mp4'),
        ];

        foreach ($files as $file) {
            $before = file_get_contents($file);
            $this->assertFalse(Mp4AudioRepair::repair($file));
            $this->assertSame($before, file_get_contents($file));
        }
        $this->assertTrue(Mp4AudioRepair::isAudioOnly($files[0]));
        $this->assertFalse(Mp4AudioRepair::isAudioOnly($files[1]));
        $this->assertFalse(Mp4AudioRepair::isAudioOnly($files[2]));
    }

    // ---------- Envoi ----------

    public function test_api_upload_repairs_the_file_and_files_it_as_audio(): void
    {
        Queue::fake();
        config(['media.transcoding_enabled' => true]);
        $upload = UploadedFile::fake()->createWithContent('enregistrement.m4a', self::mp4(self::brokenSamples()));

        $response = $this->actingAs($this->user, 'sanctum')->post('/api/v1/media/upload', ['file' => $upload]);

        $response->assertCreated()->assertJsonPath('data.type', 'audio');
        $media = MediaFile::findOrFail($response->json('data.id'));
        $this->assertSame('audio', $media->type);
        $this->assertStringStartsWith('media/audios/', $media->path);
        $this->assertFalse(Mp4AudioRepair::needsRepair(Storage::disk('public')->path($media->path)));
        Queue::assertPushed(TranscodeMediaJob::class);
    }

    // ---------- Commande ----------

    public function test_command_repairs_existing_files_and_versions_their_urls(): void
    {
        Queue::fake();
        config(['media.transcoding_enabled' => true]);
        $path = 'media/videos/ancien.mp4';
        $this->write($path, self::mp4(self::brokenSamples()));
        $url = asset('storage/' . $path);
        $media = MediaFile::create([
            'user_id' => $this->user->id, 'disk' => 'public', 'path' => $path, 'url' => $url,
            'mime_type' => 'video/mp4', 'type' => 'video', 'size_bytes' => 100,
            'processing_status' => MediaFile::STATUS_FAILED,
        ]);
        $testimony = Testimony::create([
            'user_id' => $this->user->id, 'title' => 'Titre', 'type' => 'audio', 'category_slug' => 'guerison',
            'media_url' => $url, 'visibility' => 'public', 'status' => 'approved',
        ]);

        Artisan::call('media:repair-audio', ['--dry-run' => true]);
        $this->assertTrue(Mp4AudioRepair::needsRepair(Storage::disk('public')->path($path)), 'Simulation : rien n\'est modifié.');
        $this->assertSame('video', $media->fresh()->type);

        Artisan::call('media:repair-audio');

        $this->assertFalse(Mp4AudioRepair::needsRepair(Storage::disk('public')->path($path)));
        $this->assertMatchesRegularExpression('#^' . preg_quote($url, '#') . '\?v=\d+$#', $testimony->fresh()->media_url);
        $this->assertSame($testimony->fresh()->media_url, $media->fresh()->url);
        $this->assertSame('audio', $media->fresh()->type);
        Queue::assertPushed(TranscodeMediaJob::class, fn ($job) => $job->mediaFileId === $media->id);

        // L'adresse versionnée sert toujours le fichier.
        $this->get(parse_url($testimony->fresh()->media_url, PHP_URL_PATH) . '?v=1')->assertOk();
    }
}

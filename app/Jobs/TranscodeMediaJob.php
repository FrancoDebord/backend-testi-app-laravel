<?php

namespace App\Jobs;

use App\Models\MediaFile;
use App\Models\Testimony;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Produit les versions allégées d'un fichier audio ou vidéo avec ffmpeg
 * (docs/fonctionnalites/qualites-media.md).
 *
 * Vidéo : fichier_360p.mp4 (H.264 + AAC), audio : fichier_64k.m4a (AAC), dans le même dossier
 * et sur le même disque que l'original. L'original n'est jamais modifié : en cas d'échec il reste lisible seul.
 *
 * Disque local (public) : ffmpeg lit et écrit directement dans le stockage.
 * Disque non local (enregistrements de directs sur S3) : l'original est copié par flux dans
 * media.temp_directory, converti sur place, chaque version est renvoyée par flux sur le disque,
 * puis le dossier de travail est supprimé.
 */
class TranscodeMediaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout;

    /** Fichier en cours d'écriture, supprimé si la tâche est interrompue. */
    private ?string $currentOutput = null;

    /** Dossier de travail local (disque non local), supprimé en fin de tâche. */
    private ?string $workDir = null;

    public function __construct(public string $mediaFileId)
    {
        $this->timeout = (int) config('media.timeout', 1800);
    }

    public function handle(): void
    {
        $media = MediaFile::find($this->mediaFileId);

        if (!$media || !$media->isTranscodable()) {
            return;
        }

        try {
            $disk = Storage::disk($media->disk);
        } catch (Throwable $e) {
            // Disque S3 sans le paquet league/flysystem-aws-s3-v3, disque inconnu…
            $this->markFailed($media, "disque « {$media->disk} » inutilisable : " . $e->getMessage());
            return;
        }

        if (!$disk->exists($media->path)) {
            $this->markFailed($media, 'fichier original introuvable');
            return;
        }

        $media->update(['processing_status' => MediaFile::STATUS_PROCESSING]);

        $remote     = config("filesystems.disks.{$media->disk}.driver") !== 'local';
        $renditions = [];

        try {
            if ($remote) {
                $this->workDir = $this->makeWorkDir();
                $source = $this->download($disk, $media->path);
                if ($source === null) {
                    $this->markFailed($media, 'téléchargement de l\'original impossible');
                    return;
                }
            } else {
                $source = $disk->path($media->path);
            }

            $probe = $this->probe($source);

            if ($probe === null) {
                $this->markFailed($media, 'analyse ffprobe impossible');
                return;
            }

            if ($media->type === 'video' && !$probe['has_video']) {
                $this->markFailed($media, 'aucune piste vidéo');
                return;
            }

            $plan = $media->type === 'video'
                ? $this->videoPlan($probe)
                : $this->audioPlan($probe);

            foreach ($plan as $item) {
                $relative = $this->outputPath($media->path, $item['suffix'], $item['extension']);
                $output   = $remote ? $this->workDir . DIRECTORY_SEPARATOR . basename($relative) : $disk->path($relative);

                $this->currentOutput = $output;
                $result = Process::timeout($this->timeout)->run(array_merge(
                    [config('media.ffmpeg_binary'), '-hide_banner', '-loglevel', 'error', '-y', '-i', $source],
                    $item['args'],
                    [$output]
                ));
                $this->currentOutput = null;

                if (!$result->successful() || !is_file($output) || filesize($output) === 0) {
                    $this->deleteFile($output);
                    Log::warning('Conversion média : version ignorée', [
                        'media_file_id' => $media->id,
                        'quality'       => $item['rendition']['quality'],
                        'error'         => mb_substr(trim($result->errorOutput()), 0, 500),
                    ]);
                    continue;
                }

                $size = filesize($output);

                if ($remote) {
                    $uploaded = $this->upload($disk, $output, $relative);
                    $this->deleteFile($output); // place libérée avant la version suivante
                    if (!$uploaded) {
                        Log::warning('Conversion média : envoi de la version impossible', [
                            'media_file_id' => $media->id,
                            'quality'       => $item['rendition']['quality'],
                            'disk'          => $media->disk,
                        ]);
                        continue;
                    }
                }

                $renditions[] = $item['rendition'] + [
                    'disk'       => $media->disk,
                    'path'       => $relative,
                    'size_bytes' => $size,
                ];
            }
        } catch (Throwable $e) {
            // Délai dépassé, ffmpeg absent, stockage injoignable… : fichiers partiels supprimés, nouvel essai éventuel.
            $this->deleteFile($this->currentOutput);
            $this->currentOutput = null;
            $this->deleteRenditionFiles($renditions);
            throw $e;
        } finally {
            $this->deleteWorkDir();
        }

        if ($plan !== [] && $renditions === []) {
            $this->markFailed($media, 'aucune version produite');
            return;
        }

        $previous = $media->renditions ?? [];

        $media->update([
            'renditions'        => $renditions,
            'duration_sec'      => $probe['duration'] > 0 ? (int) round($probe['duration']) : $media->duration_sec,
            'width'             => $probe['width'],
            'height'            => $probe['height'],
            'processing_status' => MediaFile::STATUS_DONE,
        ]);

        // Anciennes versions qui ne font plus partie de la nouvelle série (nouvelle échelle…).
        $kept = array_column($renditions, 'path');
        $this->deleteRenditionFiles(array_filter($previous, fn ($r) => !in_array($r['path'] ?? null, $kept, true)));

        $this->updateTestimonies($media);
    }

    /** Dernier échec (après tous les essais) : l'original reste lisible. */
    public function failed(?Throwable $exception): void
    {
        $this->deleteFile($this->currentOutput);
        $this->deleteWorkDir();

        if ($media = MediaFile::find($this->mediaFileId)) {
            $this->markFailed($media, $exception?->getMessage() ?? 'erreur inconnue');
        }
    }

    // ---------- Analyse ----------

    /**
     * @return array{duration: float, width: ?int, height: ?int, has_video: bool, audio_bitrate: ?int}|null
     */
    private function probe(string $source): ?array
    {
        try {
            $result = Process::timeout(120)->run([
                config('media.ffprobe_binary'), '-v', 'error', '-print_format', 'json',
                '-show_format', '-show_streams', $source,
            ]);
        } catch (Throwable $e) {
            // ffprobe absent ou non exécutable.
            Log::warning('Conversion média : ffprobe', ['source' => $source, 'error' => $e->getMessage()]);
            return null;
        }

        $data = $result->successful() ? json_decode($result->output(), true) : null;

        if (!is_array($data)) {
            Log::warning('Conversion média : ffprobe', ['source' => $source, 'error' => mb_substr(trim($result->errorOutput()), 0, 500)]);
            return null;
        }

        $streams = collect($data['streams'] ?? []);
        $video   = $streams->first(fn ($s) => ($s['codec_type'] ?? null) === 'video'
            && empty($s['disposition']['attached_pic'])); // pochette d'un MP3 : pas une vidéo
        $audio   = $streams->firstWhere('codec_type', 'audio');

        $width = $height = null;
        if ($video) {
            $width  = (int) ($video['width'] ?? 0) ?: null;
            $height = (int) ($video['height'] ?? 0) ?: null;

            // Vidéos de téléphone tournées : ffmpeg applique la rotation avant la mise à l'échelle.
            $sideData = collect($video['side_data_list'] ?? [])->first(fn ($d) => isset($d['rotation']));
            $rotation = (int) ($video['tags']['rotate'] ?? $sideData['rotation'] ?? 0);
            if (abs($rotation) % 180 === 90) {
                [$width, $height] = [$height, $width];
            }
        }

        $duration = (float) ($data['format']['duration'] ?? $video['duration'] ?? $audio['duration'] ?? 0);
        $bitrate  = (int) ($audio['bit_rate'] ?? 0) ?: (!$video ? (int) ($data['format']['bit_rate'] ?? 0) : 0);

        return [
            'duration'      => $duration,
            'width'         => $width,
            'height'        => $height,
            'has_video'     => $video !== null,
            'audio_bitrate' => $bitrate > 0 ? (int) round($bitrate / 1000) : null,
        ];
    }

    // ---------- Versions à produire ----------

    /** Hauteurs de l'échelle inférieures ou égales au petit côté de la source ; toujours au moins la plus basse. */
    private function videoPlan(array $probe): array
    {
        $ladder = config('media.video_ladder', []);
        ksort($ladder);

        $shortSide = ($probe['width'] && $probe['height']) ? min($probe['width'], $probe['height']) : null;
        $portrait  = $probe['width'] && $probe['height'] && $probe['height'] > $probe['width'];

        $heights = array_filter(array_keys($ladder), fn ($h) => $shortSide === null || $h <= $shortSide);
        if ($heights === [] && $ladder !== []) {
            $heights = [array_key_first($ladder)];
        }

        $audioBitrate = (int) config('media.video_audio_bitrate', 96);

        return array_map(function (int $h) use ($ladder, $portrait, $audioBitrate) {
            $kbps = (int) $ladder[$h];
            // Le petit côté vaut $h, quelle que soit l'orientation.
            $scale = $portrait ? "scale={$h}:-2" : "scale=-2:{$h}";

            return [
                'suffix'    => "{$h}p",
                'extension' => 'mp4',
                'rendition' => ['quality' => "{$h}p", 'height' => $h, 'bitrate' => $kbps],
                'args'      => [
                    '-vf', $scale,
                    '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '28',
                    '-maxrate', "{$kbps}k", '-bufsize', ($kbps * 2) . 'k',
                    '-pix_fmt', 'yuv420p',
                    '-c:a', 'aac', '-b:a', "{$audioBitrate}k",
                    '-movflags', '+faststart',
                ],
            ];
        }, array_values($heights));
    }

    /** Débits audio strictement inférieurs à celui de la source (tous si inconnu). */
    private function audioPlan(array $probe): array
    {
        $bitrates = array_map('intval', config('media.audio_bitrates', []));
        sort($bitrates);

        if ($probe['audio_bitrate']) {
            $bitrates = array_filter($bitrates, fn ($b) => $b < $probe['audio_bitrate']);
        }

        return array_map(fn (int $b) => [
            'suffix'    => "{$b}k",
            'extension' => 'm4a',
            'rendition' => ['quality' => "{$b}k", 'bitrate' => $b],
            'args'      => ['-vn', '-c:a', 'aac', '-b:a', "{$b}k", '-movflags', '+faststart'],
        ], array_values($bitrates));
    }

    // ---------- Enregistrement ----------

    /** media/videos/abc.mov → media/videos/abc_360p.mp4 */
    private function outputPath(string $path, string $suffix, string $extension): string
    {
        $info = pathinfo($path);
        $dir  = ($info['dirname'] ?? '.') === '.' ? '' : $info['dirname'] . '/';

        return "{$dir}{$info['filename']}_{$suffix}.{$extension}";
    }

    // ---------- Disque non local ----------

    /** Dossier de travail unique ; supprime au passage ceux laissés par une tâche tuée (délai dépassé…). */
    private function makeWorkDir(): string
    {
        $root = rtrim((string) config('media.temp_directory'), '/\\');

        if (!is_dir($root) && !@mkdir($root, 0775, true) && !is_dir($root)) {
            throw new RuntimeException("Dossier de travail impossible à créer : {$root}");
        }

        $staleBefore = time() - max(2 * $this->timeout, 3600);
        foreach (glob($root . DIRECTORY_SEPARATOR . 'tc-*', GLOB_ONLYDIR) ?: [] as $old) {
            if ((int) @filemtime($old) < $staleBefore) {
                $this->removeDirectory($old);
            }
        }

        $dir = $root . DIRECTORY_SEPARATOR . 'tc-' . Str::uuid();
        if (!@mkdir($dir, 0775, true)) {
            throw new RuntimeException("Dossier de travail impossible à créer : {$dir}");
        }

        return $dir;
    }

    /** Copie l'original par flux (sans le charger en mémoire) ; chemin local, ou null en cas d'échec. */
    private function download(Filesystem $disk, string $path): ?string
    {
        $local = $this->workDir . DIRECTORY_SEPARATOR . 'source.' . (pathinfo($path, PATHINFO_EXTENSION) ?: 'bin');

        $in = $disk->readStream($path);
        if (!is_resource($in)) {
            return null;
        }

        $out = fopen($local, 'wb');

        try {
            $copied = $out !== false && stream_copy_to_stream($in, $out) !== false;
        } finally {
            fclose($in);
            if ($out !== false) {
                fclose($out);
            }
        }

        return ($copied && is_file($local) && filesize($local) > 0) ? $local : null;
    }

    /** Envoie une version par flux à côté de l'original (visibilité par défaut du disque). */
    private function upload(Filesystem $disk, string $local, string $relative): bool
    {
        $stream = fopen($local, 'rb');
        if ($stream === false) {
            return false;
        }

        try {
            return (bool) $disk->writeStream($relative, $stream);
        } catch (Throwable $e) {
            Log::warning('Conversion média : envoi', ['path' => $relative, 'error' => $e->getMessage()]);
            return false;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function deleteWorkDir(): void
    {
        if ($this->workDir) {
            $this->removeDirectory($this->workDir);
            $this->workDir = null;
        }
    }

    private function removeDirectory(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $file = $dir . DIRECTORY_SEPARATOR . $name;
            is_dir($file) ? $this->removeDirectory($file) : @unlink($file);
        }
        @rmdir($dir);
    }

    // ---------- Témoignages ----------

    /** Recopie les versions (et la durée si inconnue) sur les témoignages qui utilisent ce fichier. */
    private function updateTestimonies(MediaFile $media): void
    {
        $query = Testimony::withTrashed()->where(function ($q) use ($media) {
            $q->where('media_url', $media->url);
            if ($media->disk === 'public') {
                $q->orWhere('media_url', 'like', '%/storage/' . addcslashes($media->path, '\%_'));
            }
            if ($media->disk === MediaFile::RECORDINGS_DISK) {
                // Même enregistrement, lu depuis une ancienne LIVEKIT_RECORDING_PUBLIC_URL.
                $q->orWhere('media_url', 'like', '%/' . addcslashes($media->path, '\%_'));
            }
        });

        $query->each(function (Testimony $testimony) use ($media) {
            $testimony->renditions = $media->renditions;
            if ((int) $testimony->duration_sec === 0 && $media->duration_sec > 0) {
                $testimony->duration_sec = $media->duration_sec;
            }
            $testimony->save();
        });
    }

    private function markFailed(MediaFile $media, string $reason): void
    {
        Log::warning('Conversion média échouée : ' . $reason, ['media_file_id' => $media->id, 'disk' => $media->disk, 'path' => $media->path]);
        $media->update(['processing_status' => MediaFile::STATUS_FAILED]);
    }

    private function deleteRenditionFiles(array $renditions): void
    {
        foreach ($renditions as $r) {
            if (!empty($r['path'])) {
                try {
                    Storage::disk($r['disk'] ?? 'public')->delete($r['path']);
                } catch (Throwable $e) {
                    Log::warning('Conversion média : suppression', ['path' => $r['path'], 'error' => $e->getMessage()]);
                }
            }
        }
    }

    private function deleteFile(?string $file): void
    {
        if ($file && is_file($file)) {
            @unlink($file);
        }
    }
}

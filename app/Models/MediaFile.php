<?php

namespace App\Models;

use App\Jobs\TranscodeMediaJob;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MediaFile extends Model
{
    use HasFactory, HasUuids;

    // États de conversion (docs/fonctionnalites/qualites-media.md).
    public const STATUS_NONE       = 'none';
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE       = 'done';
    public const STATUS_FAILED     = 'failed';

    /** Disque des enregistrements de directs (config/filesystems.php, docs/fonctionnalites/lives.md). */
    public const RECORDINGS_DISK = 'recordings';

    protected $fillable = [
        'user_id', 'disk', 'path', 'url', 'mime_type', 'type',
        'size_bytes', 'duration_sec', 'original_name',
        'renditions', 'processing_status', 'width', 'height',
    ];

    protected $attributes = [
        'processing_status' => self::STATUS_NONE,
    ];

    protected function casts(): array
    {
        return [
            'size_bytes'   => 'integer',
            'duration_sec' => 'integer',
            'renditions'   => 'array',
            'width'        => 'integer',
            'height'       => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }

    // ---------- Qualités des médias ----------

    public function isTranscodable(): bool
    {
        return in_array($this->type, ['audio', 'video'], true);
    }

    public function isProcessed(): bool
    {
        return $this->processing_status === self::STATUS_DONE;
    }

    /** Met la conversion en file d'attente (audio et vidéo uniquement, si activée). */
    public function queueTranscoding(): bool
    {
        if (!$this->isTranscodable() || !config('media.transcoding_enabled')) {
            return false;
        }

        $this->update(['processing_status' => self::STATUS_PENDING]);

        try {
            TranscodeMediaJob::dispatch($this->id);
        } catch (\Throwable $e) {
            // File « sync » : un échec de conversion ne doit jamais faire échouer l'envoi du fichier.
            report($e);
        }

        return true;
    }

    /**
     * Chemin relatif au disque « public » déduit d'une URL « …/storage/{chemin} »,
     * indépendamment du domaine (APP_URL peut avoir changé depuis l'envoi).
     */
    public static function publicPathFromUrl(?string $url): ?string
    {
        $path = (string) parse_url((string) $url, PHP_URL_PATH);
        $pos  = strpos($path, '/storage/');

        if ($pos === false) {
            return null;
        }

        $relative = rawurldecode(substr($path, $pos + strlen('/storage/')));

        return ($relative === '' || str_contains($relative, '..')) ? null : $relative;
    }

    /**
     * Chemin d'un enregistrement de direct déduit de son URL
     * « LIVEKIT_RECORDING_PUBLIC_URL/{chemin} » (null si l'URL n'a pas cette base).
     */
    public static function recordingPathFromUrl(?string $url): ?string
    {
        $base = rtrim((string) config('livekit.recording.public_url'), '/');
        $url  = (string) strtok((string) $url, '?#');

        if ($base === '' || !str_starts_with($url, $base . '/')) {
            return null;
        }

        $relative = ltrim(rawurldecode(substr($url, strlen($base) + 1)), '/');

        return ($relative === '' || str_contains($relative, '..')) ? null : $relative;
    }

    /** Fichier média correspondant à l'URL enregistrée dans testimonies.media_url. */
    public static function findForUrl(?string $url): ?self
    {
        if (!$url) {
            return null;
        }

        if ($media = static::where('url', $url)->latest()->first()) {
            return $media;
        }

        foreach (['public' => static::publicPathFromUrl($url), self::RECORDINGS_DISK => static::recordingPathFromUrl($url)] as $disk => $path) {
            if ($path && ($media = static::where('disk', $disk)->where('path', $path)->latest()->first())) {
                return $media;
            }
        }

        return null;
    }

    /**
     * Ligne media_files d'un enregistrement de direct (créée si absente),
     * pour en produire les versions comme pour un fichier envoyé.
     */
    public static function forRecording(string $path, string $userId, ?string $url = null, int $durationSec = 0, int $sizeBytes = 0): self
    {
        return static::firstOrCreate(
            ['disk' => self::RECORDINGS_DISK, 'path' => $path],
            [
                'user_id'       => $userId,
                'url'           => $url ?? static::urlFor(self::RECORDINGS_DISK, $path),
                'mime_type'     => 'video/mp4',
                'type'          => 'video',
                'size_bytes'    => max(0, $sizeBytes),
                'duration_sec'  => max(0, $durationSec),
                'original_name' => basename($path),
            ]
        );
    }

    /**
     * Versions au format de l'API mobile, triées par débit croissant :
     * vidéo {quality, height, bitrate, url}, audio {quality, bitrate, url}. [] si aucune.
     */
    public static function renditionsForApi(?array $renditions): array
    {
        return collect($renditions ?? [])
            ->filter(fn ($r) => is_array($r) && (!empty($r['path']) || !empty($r['url'])))
            ->map(function (array $r) {
                $item = ['quality' => (string) ($r['quality'] ?? '')];
                if (isset($r['height'])) {
                    $item['height'] = (int) $r['height'];
                }
                $item['bitrate'] = (int) ($r['bitrate'] ?? 0);
                $item['url']     = $r['url'] ?? static::urlFor($r['disk'] ?? 'public', $r['path']);

                return $item;
            })
            ->sortBy([['bitrate', 'asc'], ['height', 'asc']])
            ->values()
            ->all();
    }

    /**
     * URL absolue d'un fichier : même forme que MediaController::upload pour le disque public,
     * LIVEKIT_RECORDING_PUBLIC_URL + chemin pour les enregistrements de directs (LiveService::recordingUrl).
     */
    public static function urlFor(string $disk, string $path): string
    {
        return match ($disk) {
            'public'              => asset('storage/' . $path),
            self::RECORDINGS_DISK => rtrim((string) config('livekit.recording.public_url'), '/') . '/' . ltrim($path, '/'),
            default               => Storage::disk($disk)->url($path),
        };
    }
}

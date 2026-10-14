<?php

namespace App\Services;

use App\Models\Testimony;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cycle de vie des vidéos de témoignages hébergées par Bunny Stream
 * (docs/fonctionnalites/videos-bunny-stream.md) :
 * uploading (envoi TUS par l'application) → processing (encodage Bunny) → ready | failed.
 */
class BunnyStreamVideos
{
    /** Délai après lequel un envoi jamais terminé est abandonné (videos:expire-uploads). */
    public const UPLOAD_EXPIRY_HOURS = 24;

    public function __construct(private BunnyStream $bunny) {}

    /**
     * Crée la vidéo Bunny du témoignage et renvoie l'autorisation d'envoi TUS.
     *
     * @return array{video_id: string, testimony_id: string, library_id: int|string, bunny_video_id: string, signature: string, expiration: int, tus_endpoint: string}
     */
    public function initUpload(Testimony $testimony): array
    {
        $guid = $this->bunny->createVideo($testimony->title);
        $testimony->forceFill(['bunny_video_id' => $guid])->save();

        return [
            'video_id'       => $testimony->id,
            'testimony_id'   => $testimony->id,
            'library_id'     => $this->libraryId(),
            'bunny_video_id' => $guid,
            ...$this->bunny->uploadSignature($guid),
            'tus_endpoint'   => BunnyStream::TUS_ENDPOINT,
        ];
    }

    /**
     * Relit l'état de la vidéo chez Bunny et met le témoignage à jour : App\Services\BunnyVideoSync
     * (logique commune au webhook et à videos:sync-bunny-status).
     */
    public function syncFromBunny(Testimony $testimony): array
    {
        return app(BunnyVideoSync::class)->syncFromBunny($testimony);
    }

    /**
     * Objet « video » de la ressource témoignage, ou null sans vidéo (texte, audio, YouTube) :
     *   status        processing | ready | failed (envoi en cours = processing)
     *   playback_url  lecture HLS (Bunny) ; null pour une ancienne vidéo du serveur
     *   mp4_url       meilleure qualité MP4 disponible (MP4 de secours Bunny, ou fichier du serveur)
     *   thumbnail_url, duration
     * Aucune URL tant que la vidéo n'est pas lisible. Anciens noms gardés pour les versions de l'application
     * déjà publiées : hls_url (= playback_url), mp4_urls (par hauteur).
     *
     * @return array{status: string, playback_url: ?string, mp4_url: ?string, thumbnail_url: ?string, duration: ?int, hls_url: ?string, mp4_urls: object}|null
     */
    public function payload(Testimony $testimony): ?array
    {
        if ($testimony->video_status === Testimony::VIDEO_READY && $testimony->bunny_video_id) {
            $guid = $testimony->bunny_video_id;
            $mp4  = $this->mp4Urls($testimony);

            return self::shape(Testimony::VIDEO_READY, $this->bunny->hlsUrl($guid), $mp4 ? end($mp4) : null,
                $this->bunny->thumbnailUrl($guid), $testimony->duration ?? ($testimony->duration_sec ?: null), $mp4);
        }

        // Ancienne vidéo stockée sur le serveur (y compris pendant sa reprise par Bunny) : versions ffmpeg.
        if ($testimony->type?->value === 'video' && filled($testimony->media_url) && !str_ends_with((string) $testimony->media_url, '.m3u8')) {
            $mp4 = [];
            foreach (\App\Models\MediaFile::renditionsForApi($testimony->renditions) as $r) {
                if (isset($r['height'])) {
                    $mp4[(string) $r['height']] = $r['url'];
                }
            }

            return self::shape(Testimony::VIDEO_READY, null, $mp4 ? end($mp4) : $testimony->media_url,
                $testimony->cover_url, $testimony->duration_sec ?: null, $mp4);
        }

        // Vidéo Bunny en cours d'envoi, en cours de traitement ou en échec : rien à lire.
        if ($testimony->video_status !== null) {
            $status = $testimony->video_status === Testimony::VIDEO_FAILED ? Testimony::VIDEO_FAILED : Testimony::VIDEO_PROCESSING;

            return self::shape($status, null, null, null, null, []);
        }

        return null;
    }

    private static function shape(string $status, ?string $playback, ?string $mp4, ?string $thumbnail, ?int $duration, array $mp4Urls): array
    {
        return [
            'status'        => $status,
            'playback_url'  => $playback,
            'mp4_url'       => $mp4,
            'thumbnail_url' => $thumbnail,
            'duration'      => $duration,
            // Anciens noms (versions de l'application déjà publiées).
            'hls_url'       => $playback,
            'mp4_urls'      => (object) $mp4Urls,
        ];
    }

    /** @return array<string, string> MP4 par hauteur (« 240 » => URL), pour les seules qualités produites par Bunny. */
    public function mp4Urls(Testimony $testimony): array
    {
        // Sans l'option « MP4 Fallback » de la bibliothèque, Bunny ne produit que le HLS.
        if (!$testimony->bunny_video_id || !config('services.bunny_stream.mp4_fallback', true)) {
            return [];
        }

        $available = collect(explode(',', (string) $testimony->available_resolutions))
            ->map(fn ($r) => (int) rtrim(trim($r), 'p'))
            ->all();

        $urls = [];
        foreach (BunnyStream::MP4_HEIGHTS as $height) {
            if (in_array($height, $available, true)) {
                $urls[(string) $height] = $this->bunny->mp4Url($testimony->bunny_video_id, $height);
            }
        }

        return $urls;
    }

    /**
     * Envois jamais terminés depuis plus de $hours heures : témoignage en « failed », vidéo Bunny supprimée.
     *
     * @return int nombre de témoignages passés en échec
     */
    public function expireStaleUploads(int $hours = self::UPLOAD_EXPIRY_HOURS): int
    {
        $count = 0;

        Testimony::where('video_status', Testimony::VIDEO_UPLOADING)
            ->where('created_at', '<', now()->subHours($hours))
            ->orderBy('created_at')
            ->each(function (Testimony $testimony) use (&$count) {
                $guid = $testimony->bunny_video_id;
                $deleted = $guid ? self::deleteQuietly($guid) : true;

                $testimony->forceFill([
                    'video_status'   => Testimony::VIDEO_FAILED,
                    // Vidéo supprimée chez Bunny : plus rien à y supprimer avec le témoignage.
                    'bunny_video_id' => $deleted ? null : $guid,
                ])->save();
                $count++;
            });

        return $count;
    }

    /** Supprime la vidéo chez Bunny ; un échec est journalisé, jamais propagé. */
    public static function deleteQuietly(string $guid): bool
    {
        try {
            app(BunnyStream::class)->deleteVideo($guid);
            return true;
        } catch (Throwable $e) {
            Log::warning('Bunny Stream : suppression de la vidéo impossible.', ['guid' => $guid, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function libraryId(): int|string
    {
        $id = $this->bunny->libraryId();

        return ctype_digit($id) ? (int) $id : $id;
    }
}

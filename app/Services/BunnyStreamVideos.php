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
     * Relit l'état de la vidéo chez Bunny (le contenu du webhook n'est jamais pris tel quel) et met le
     * témoignage à jour. Passage à « ready » : notification push à l'auteur (une seule fois).
     */
    public function syncFromBunny(Testimony $testimony): void
    {
        $guid = $testimony->bunny_video_id;
        if (!$guid) {
            return;
        }

        $video       = $this->bunny->getVideo($guid);
        $status      = (int) ($video['status'] ?? -1);
        $resolutions = trim((string) ($video['availableResolutions'] ?? ''));
        $length      = (int) ($video['length'] ?? 0);

        $target = match (true) {
            $status === BunnyStream::STATUS_FINISHED,
            $status === BunnyStream::STATUS_TRANSCODING && $resolutions !== '' => Testimony::VIDEO_READY,
            in_array($status, [BunnyStream::STATUS_ERROR, BunnyStream::STATUS_UPLOAD_FAILED], true) => Testimony::VIDEO_FAILED,
            in_array($status, [BunnyStream::STATUS_UPLOADED, BunnyStream::STATUS_PROCESSING, BunnyStream::STATUS_TRANSCODING], true) => Testimony::VIDEO_PROCESSING,
            default => null, // créée, envoi pas encore reçu : rien ne change
        };

        if ($target === Testimony::VIDEO_READY) {
            $this->markReady($testimony, $length, $resolutions);
            return;
        }

        // Jamais de retour en arrière depuis « ready » (webhooks tardifs, encodage d'une qualité supplémentaire).
        if ($target !== null && $testimony->video_status !== Testimony::VIDEO_READY && $testimony->video_status !== $target) {
            $testimony->forceFill(['video_status' => $target])->save();
        }
    }

    /**
     * Objet « video » de la ressource témoignage, ou null s'il n'y a pas de vidéo (texte, audio, YouTube).
     *
     * @return array{status: string, hls_url: ?string, thumbnail_url: ?string, mp4_urls: object, duration: ?int}|null
     */
    public function payload(Testimony $testimony): ?array
    {
        if ($testimony->video_status === Testimony::VIDEO_READY && $testimony->bunny_video_id) {
            $guid = $testimony->bunny_video_id;

            return [
                'status'        => Testimony::VIDEO_READY,
                'hls_url'       => $this->bunny->hlsUrl($guid),
                'thumbnail_url' => $this->bunny->thumbnailUrl($guid),
                'mp4_urls'      => (object) $this->mp4Urls($testimony),
                'duration'      => $testimony->duration ?? ($testimony->duration_sec ?: null),
            ];
        }

        // Ancienne vidéo stockée sur LWS (y compris pendant sa reprise par Bunny) : versions ffmpeg.
        if ($testimony->type?->value === 'video' && filled($testimony->media_url)) {
            $mp4 = [];
            foreach (\App\Models\MediaFile::renditionsForApi($testimony->renditions) as $r) {
                if (isset($r['height'])) {
                    $mp4[(string) $r['height']] = $r['url'];
                }
            }

            return [
                'status'        => Testimony::VIDEO_READY,
                'hls_url'       => null,
                'thumbnail_url' => $testimony->cover_url,
                'mp4_urls'      => (object) $mp4,
                'duration'      => $testimony->duration_sec ?: null,
            ];
        }

        // Vidéo Bunny en cours d'envoi, en cours de traitement ou en échec : rien à lire encore.
        if ($testimony->video_status !== null) {
            return [
                'status'        => $testimony->video_status,
                'hls_url'       => null,
                'thumbnail_url' => null,
                'mp4_urls'      => (object) [],
                'duration'      => null,
            ];
        }

        return null;
    }

    /** @return array<string, string> MP4 par hauteur (« 240 » => URL), pour les seules qualités produites par Bunny. */
    public function mp4Urls(Testimony $testimony): array
    {
        if (!$testimony->bunny_video_id) {
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

    private function markReady(Testimony $testimony, int $length, string $resolutions): void
    {
        $values = [
            'available_resolutions' => $resolutions !== '' ? $resolutions : $testimony->available_resolutions,
        ];
        if ($length > 0) {
            $values['duration']     = $length;
            $values['duration_sec'] = $length; // shorts (≤ 60 s) et affichage de la durée
        }

        // Mise à jour conditionnelle : deux webhooks simultanés ne déclenchent qu'une notification.
        $becameReady = Testimony::whereKey($testimony->id)
            ->where(fn ($q) => $q->whereNull('video_status')->orWhere('video_status', '!=', Testimony::VIDEO_READY))
            ->update(['video_status' => Testimony::VIDEO_READY, 'updated_at' => now()] + $values) > 0;

        if (!$becameReady) {
            // Déjà prête : seules les qualités (nouvelle résolution encodée) et la durée sont mises à jour.
            Testimony::whereKey($testimony->id)->update($values);
        }

        $testimony->refresh();

        // Modération non obligatoire : publié dès que la vidéo est lisible (docs/fonctionnalites/gestion-des-temoignages.md).
        if ($becameReady) {
            app(TestimonyModeration::class)->submitted($testimony);
        }

        // Ancienne vidéo reprise par Bunny (videos:migrate-to-bunny) : l'auteur n'attend rien, pas de notification.
        if ($becameReady && blank($testimony->media_url)) {
            PushNotifications::dispatch(
                $testimony->user_id,
                'Vidéo prête',
                "Votre vidéo « {$testimony->title} » est prête.",
                ['type' => 'video_ready', 'testimony_id' => $testimony->id],
            );
        }
    }
}

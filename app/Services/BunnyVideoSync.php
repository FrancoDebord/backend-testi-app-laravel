<?php

namespace App\Services;

use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Models\AppNotification;
use App\Models\Testimony;
use App\Models\User;
use App\Support\Locales;
use App\Support\NotificationText;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Synchronisation d'une vidéo de témoignage avec Bunny Stream : logique unique du webhook
 * (POST /webhooks/bunny) et de la commande videos:sync-bunny-status. Voir docs/fonctionnalites/videos-bunny-stream.md
 *
 * L'état est toujours relu par l'API Bunny (GET /library/{id}/videos/{guid}) : le webhook n'est pas signé,
 * son contenu n'est jamais cru tel quel. Statuts de l'API (différents de ceux du webhook) :
 * 0 créée · 1 envoyée · 2 traitement · 3 encodage · 4 terminée · 5 erreur · 6 échec d'envoi.
 *
 * - terminée (4), ou encodage (3) avec au moins une résolution prête : lisible. media_url = lecture HLS,
 *   cover_url = vignette (si vide), durée et résolutions ; notification « video_ready » une seule fois ;
 * - erreur (5, 6) : échec, l'auteur est prévenu une seule fois ;
 * - envoyée, traitement, encodage sans résolution : « processing » (début du traitement retenu).
 */
class BunnyVideoSync
{
    public const UPDATED   = 'updated';
    public const UNCHANGED = 'unchanged';
    public const ERROR     = 'error';

    /** Traitement plus long que ce délai : alerte des super-administrateurs (une fois). */
    public const STUCK_MINUTES = 30;

    public function __construct(private BunnyStream $bunny) {}

    /**
     * @return array{action: string, bunny_status: ?int, video_status: ?string, error?: string}
     */
    public function syncFromBunny(Testimony $testimony): array
    {
        $guid = $testimony->bunny_video_id;
        if (!$guid) {
            return ['action' => self::UNCHANGED, 'bunny_status' => null, 'video_status' => $testimony->video_status];
        }

        try {
            $video = $this->bunny->getVideo($guid);
        } catch (Throwable $e) {
            Log::error('Bunny Stream : lecture de la vidéo impossible (clé API, bibliothèque ou réseau).', [
                'guid' => $guid, 'testimony_id' => $testimony->id, 'library_id' => $this->bunny->libraryId(), 'error' => $e->getMessage(),
            ]);

            return ['action' => self::ERROR, 'bunny_status' => null, 'video_status' => $testimony->video_status, 'error' => $e->getMessage()];
        }

        $status      = (int) ($video['status'] ?? -1);
        $resolutions = trim((string) ($video['availableResolutions'] ?? ''));
        $length      = (int) round((float) ($video['length'] ?? 0));

        $changed = match (true) {
            $status === BunnyStream::STATUS_FINISHED,
            $status === BunnyStream::STATUS_TRANSCODING && $resolutions !== '' => $this->markReady($testimony, $length, $resolutions),
            in_array($status, [BunnyStream::STATUS_ERROR, BunnyStream::STATUS_UPLOAD_FAILED], true) => $this->markFailed($testimony),
            in_array($status, [BunnyStream::STATUS_UPLOADED, BunnyStream::STATUS_PROCESSING, BunnyStream::STATUS_TRANSCODING], true) => $this->markProcessing($testimony),
            default => false, // créée, envoi pas encore reçu
        };

        return ['action' => $changed ? self::UPDATED : self::UNCHANGED, 'bunny_status' => $status, 'video_status' => $testimony->fresh()?->video_status];
    }

    /**
     * Vidéos en traitement depuis plus de STUCK_MINUTES minutes : super-administrateurs (à défaut,
     * administrateurs) prévenus une seule fois par vidéo. Renvoie le nombre de vidéos signalées.
     */
    public function alertStuck(): int
    {
        $stuck = Testimony::where('video_status', Testimony::VIDEO_PROCESSING)
            ->whereNotNull('bunny_video_id')->whereNull('video_alerted_at')
            ->where(fn ($q) => $q->where('video_processing_at', '<', now()->subMinutes(self::STUCK_MINUTES))
                ->orWhere(fn ($q2) => $q2->whereNull('video_processing_at')->where('created_at', '<', now()->subMinutes(self::STUCK_MINUTES))))
            ->get();
        if ($stuck->isEmpty()) {
            return 0;
        }

        $admins = User::where('role', UserRole::Administrateur->value)->where('status', UserAccountStatus::Active->value)->get();
        $recipients = $admins->filter(fn (User $u) => $u->isSuperAdmin());
        if ($recipients->isEmpty()) {
            $recipients = $admins;
        }

        foreach ($stuck as $t) {
            // Mise à jour conditionnelle : deux passages simultanés n'alertent qu'une fois.
            if (Testimony::whereKey($t->id)->whereNull('video_alerted_at')->update(['video_alerted_at' => now()]) === 0) {
                continue;
            }
            Log::warning('Bunny Stream : vidéo en traitement depuis plus de ' . self::STUCK_MINUTES . ' minutes.', ['guid' => $t->bunny_video_id, 'testimony_id' => $t->id]);
            foreach ($recipients as $admin) {
                AppNotification::create([
                    'recipient_id'    => $admin->id,
                    'actor_id'        => null,
                    'actor_name'      => 'Bunny Stream',
                    'type'            => 'video_stuck',
                    'testimony_id'    => $t->id,
                    'testimony_title' => $t->title,
                    ...NotificationText::fields('admin.bunny.notify_stuck', [
                        'title' => $t->title, 'minutes' => self::STUCK_MINUTES, 'guid' => $t->bunny_video_id,
                    ], ['bunny_video_id' => $t->bunny_video_id]),
                    'created_at'      => now(),
                ]);
            }
        }

        return $stuck->count();
    }

    // ─── Transitions ─────────────────────────────────────────────────────────

    private function markReady(Testimony $testimony, int $length, string $resolutions): bool
    {
        $guid = $testimony->bunny_video_id;
        // Ancienne vidéo reprise par Bunny (videos:migrate-to-bunny) : son fichier d'origine était dans media_url,
        // l'auteur n'attend rien (pas de notification).
        $wasMigration = filled($testimony->media_url) && !str_contains((string) $testimony->media_url, '/playlist.m3u8');

        $values = [
            'media_url'             => $this->bunny->hlsUrl($guid),
            'available_resolutions' => $resolutions !== '' ? $resolutions : $testimony->available_resolutions,
        ];
        if ($length > 0) {
            $values['duration']     = $length;
            $values['duration_sec'] = $length; // shorts (≤ 60 s) et affichage de la durée
        }
        if (blank($testimony->cover_url)) {
            $values['cover_url'] = $this->bunny->thumbnailUrl($guid);
        }

        // Mise à jour conditionnelle : deux appels simultanés ne déclenchent qu'une notification.
        $becameReady = Testimony::withTrashed()->whereKey($testimony->id)
            ->where(fn ($q) => $q->whereNull('video_status')->orWhere('video_status', '!=', Testimony::VIDEO_READY))
            ->update(['video_status' => Testimony::VIDEO_READY, 'updated_at' => now()] + $values) > 0;

        if (!$becameReady) {
            // Déjà prête : qualités (nouvelle résolution encodée), durée et lien de lecture complétés si besoin.
            $current = Testimony::withTrashed()->whereKey($testimony->id)->first();
            $missing = collect($values)->filter(fn ($v, $k) => (string) $current?->getRawOriginal($k) !== (string) $v)->all();
            if ($missing) {
                Testimony::withTrashed()->whereKey($testimony->id)->update($missing + ['updated_at' => now()]);
            }
            $testimony->refresh();

            return $missing !== [];
        }

        $testimony->refresh();
        // Modération non obligatoire : publié dès que la vidéo est lisible (docs/fonctionnalites/gestion-des-temoignages.md).
        app(TestimonyModeration::class)->submitted($testimony);

        if (!$wasMigration) {
            [$title, $body] = $this->pushText($testimony, 'ready');
            PushNotifications::dispatch($testimony->user_id, $title, $body,
                ['type' => 'video_ready', 'testimony_id' => $testimony->id]);
        }

        return true;
    }

    private function markFailed(Testimony $testimony): bool
    {
        // Jamais de retour en arrière depuis « ready » ; notification une seule fois.
        $became = Testimony::withTrashed()->whereKey($testimony->id)
            ->where(fn ($q) => $q->whereNull('video_status')->orWhereNotIn('video_status', [Testimony::VIDEO_READY, Testimony::VIDEO_FAILED]))
            ->update(['video_status' => Testimony::VIDEO_FAILED, 'updated_at' => now()]) > 0;
        if (!$became) {
            return false;
        }
        $testimony->refresh();

        [$title, $body] = $this->pushText($testimony, 'failed');
        PushNotifications::dispatch($testimony->user_id, $title, $body,
            ['type' => 'video_failed', 'testimony_id' => $testimony->id]);

        return true;
    }

    /** Titre et texte du push « vidéo prête / non traitée » dans la langue de l'auteur. */
    private function pushText(Testimony $testimony, string $state): array
    {
        $locale = Locales::forUser(User::select(['id', 'locale'])->find($testimony->user_id));

        return Locales::using($locale, fn () => [
            __("admin.bunny.push_{$state}_title"),
            __("admin.bunny.push_{$state}_body", ['title' => $testimony->title]),
        ]);
    }

    private function markProcessing(Testimony $testimony): bool
    {
        if (in_array($testimony->video_status, [Testimony::VIDEO_READY, Testimony::VIDEO_FAILED, Testimony::VIDEO_PROCESSING], true)) {
            if ($testimony->video_status === Testimony::VIDEO_PROCESSING && $testimony->video_processing_at === null) {
                $testimony->forceFill(['video_processing_at' => now()])->saveQuietly();
            }

            return false;
        }
        $testimony->forceFill(['video_status' => Testimony::VIDEO_PROCESSING, 'video_processing_at' => $testimony->video_processing_at ?? now()])->save();

        return true;
    }
}

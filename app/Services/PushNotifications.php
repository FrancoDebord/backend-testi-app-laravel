<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Jobs\SendPushNotificationJob;
use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\User;
use App\Models\UserSetting;
use App\Support\Locales;
use Illuminate\Support\Facades\Lang;
use Throwable;

/**
 * Transforme une notification de l'application (AppNotification) en notification push
 * (docs/fonctionnalites/notifications-push.md) : titre et texte dans la langue du destinataire (users.locale), données de
 * navigation lues par l'application mobile (lib/services/fcm_service.dart), respect des
 * préférences push de l'utilisateur (user_settings.push_*).
 */
class PushNotifications
{
    /**
     * Catégorie (colonne de user_settings, activée par défaut) qui commande chaque type : la personne qui coupe
     * une catégorie ne reçoit plus ces push (la notification reste dans l'application). Tout type de
     * NotificationType a une catégorie (tests\Feature\PushConsentTest) ; video_ready / video_failed sont
     * des push seuls (BunnyVideoSync). Un type inconnu est envoyé. Voir docs/fonctionnalites/notifications-push.md
     */
    public const PREFERENCES = [
        // Commentaires, réponses, mentions
        'comment'                       => 'push_comments',
        'reply'                         => 'push_comments',
        'mention'                       => 'push_comments',
        // Réactions « j'aime » et partages (docs/fonctionnalites/jaime-commentaires.md)
        'like'                          => 'push_likes',
        'comment_liked'                 => 'push_likes',
        'share'                         => 'push_likes',
        // Prières
        'prayer'                        => 'push_prayers',
        'prayer_encouragement'          => 'push_prayers',
        'prayer_request_prayed'         => 'push_prayers',
        // Publication de mes témoignages
        'testimony_approved'            => 'push_approval',
        'testimony_rejected'            => 'push_approval',
        'pending_correction'            => 'push_approval',
        'video_ready'                   => 'push_approval',
        'video_failed'                  => 'push_approval',
        // Comptes suivis : nouveaux témoignages, directs
        'new_followed_testimony'        => 'push_new_followed',
        'live_started'                  => 'push_new_followed',
        // Nouveaux abonnés
        'follow'                        => 'push_follows',
        // Événements et sessions de prière
        'event_postponed'               => 'push_events',
        'event_cancelled'               => 'push_events',
        'event_survey'                  => 'push_events',
        'prayer_session_started'        => 'push_events',
        'prayer_session_reminder'       => 'push_events',
        // Mon compte : vérification, badge, administrateurs
        'organization_verified'         => 'push_account',
        'organization_rejected'         => 'push_account',
        'account_verified'              => 'push_account',
        'account_verification_rejected' => 'push_account',
        'account_approval_revoked'      => 'push_account',
        'account_manager_added'         => 'push_account',
        'account_manager_removed'       => 'push_account',
        // Équipe de modération
        'testimony_resubmitted'         => 'push_moderation',
        'video_stuck'                   => 'push_moderation',
        'account_deletion_requested'    => 'push_moderation',
    ];

    /** Catégories exposées dans GET/PUT users/me/settings (toutes activées par défaut). */
    public const CATEGORIES = [
        'push_comments', 'push_likes', 'push_prayers', 'push_approval', 'push_new_followed',
        'push_follows', 'push_events', 'push_account', 'push_moderation',
    ];

    /** Réglage du back-office (AppSetting) : false coupe tous les push. */
    public const GLOBAL_SETTING = 'push_notifications_enabled';

    // Titres et textes de secours : lang/{fr,en}/notifications.php (titles.*, fallback.*), dans la langue du destinataire.

    private const BODY_MAX = 200;

    /**
     * Met en file l'envoi push d'une notification (enregistrée ou non). Ne lève jamais d'exception :
     * un échec du push ne doit pas empêcher la création de la notification dans l'application.
     */
    public static function dispatchFor(AppNotification $notification): void
    {
        try {
            $recipientId = $notification->recipient_id;
            if (!$recipientId || !self::globallyEnabled() || !app(FcmClient::class)->isConfigured()) {
                return;
            }

            // Jamais de push pour sa propre action (ex. modérateur qui approuve son témoignage).
            if ($notification->actor_id && (string) $notification->actor_id === (string) $recipientId) {
                return;
            }

            if (!self::allows($recipientId, self::rawType($notification))) {
                return;
            }

            if (!DeviceToken::where('user_id', $recipientId)->exists()) {
                return;
            }

            // Titre et texte dans la langue du destinataire (docs/fonctionnalites/langues.md).
            $locale  = Locales::forUser(User::select(['id', 'locale'])->find($recipientId));
            $message = Locales::using($locale, fn () => self::message($notification));
            self::dispatch($recipientId, $message['title'], $message['body'], $message['data']);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Met en file l'envoi (PUSH_DELIVERY=queue, défaut) ou l'exécute juste après la réponse HTTP
     * (PUSH_DELIVERY=after_response : hébergement sans tâche cron pour la file, sans nouvel essai).
     *
     * @param array<string, mixed> $data
     */
    public static function dispatch(string $userId, string $title, string $body, array $data = []): void
    {
        // Réglage du back-office « Notifications push » coupé : aucun push.
        if (!self::globallyEnabled()) {
            return;
        }
        // Push seul (sans notification enregistrée, ex. video_ready) : la catégorie de son type s'applique aussi.
        if (isset($data['type']) && !isset($data['notification_id']) && !self::allows($userId, (string) $data['type'])) {
            return;
        }

        $job = new SendPushNotificationJob($userId, $title, $body, $data);

        if (config('services.fcm.delivery') === 'after_response') {
            $job->retryOnFailure = false;
            dispatch($job)->afterResponse();
            return;
        }

        dispatch($job);
    }

    /** Push autorisés par le back-office (AppSetting push_notifications_enabled, vrai par défaut). */
    public static function globallyEnabled(): bool
    {
        try {
            $value = \App\Models\AppSetting::get(self::GLOBAL_SETTING, true);
        } catch (Throwable) {
            return true; // table absente (installation en cours) : comportement d'avant
        }

        return $value === null || filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false;
    }

    /** L'utilisateur accepte-t-il les push de ce type ? Sans réglages enregistrés : oui. */
    public static function allows(string $userId, ?string $type): bool
    {
        $column = self::PREFERENCES[$type] ?? null;
        if ($column === null) {
            return true;
        }

        $value = UserSetting::whereKey($userId)->value($column);

        return $value === null ? true : (bool) $value;
    }

    /**
     * @return array{title: string, body: string, data: array<string, string>}
     */
    public static function message(AppNotification $notification): array
    {
        $type = self::rawType($notification);
        $payload = is_array($notification->payload) ? $notification->payload : [];

        $title = Lang::has('notifications.titles.' . $type) ? __('notifications.titles.' . $type) : null;
        if (!$title) {
            $title = trim((string) ($payload['title'] ?? '')) ?: __('notifications.titles.default');
        }

        $body = trim((string) ($notification->message ?? ''));
        if ($body === '') {
            $body = trim((string) ($payload['body'] ?? $payload['message'] ?? ''));
        }
        if ($body === '') {
            $body = self::fallbackBody($type, $notification);
        }

        $data = FcmClient::stringifyData([
            'type'            => $type,
            // Identifiant de l'objet principal à ouvrir (requête, session, événement, direct, verset,
            // témoignage, sinon la personne pour un abonnement) ; les clés détaillées restent fournies.
            'id'              => $payload['prayer_request_id'] ?? $payload['prayer_session_id'] ?? $payload['event_id']
                ?? $payload['live_id'] ?? $payload['daily_verse_id'] ?? $notification->testimony_id
                ?? ($type === 'follow' ? $notification->actor_id : null),
            'testimony_id'    => $notification->testimony_id,
            'comment_id'      => $notification->comment_id,
            'actor_id'        => $notification->actor_id,
            'notification_id' => $notification->id,
            'live_id'         => $payload['live_id'] ?? null,
            // Requêtes et sessions de prière (docs/fonctionnalites/sessions-de-priere.md)
            'prayer_request_id' => $payload['prayer_request_id'] ?? null,
            'prayer_session_id' => $payload['prayer_session_id'] ?? null,
            // Événements (types event_*) : ouvre la page de l'événement.
            'event_id'          => $payload['event_id'] ?? null,
            // Mention dans un commentaire du verset du jour : ouvre la page du verset.
            'daily_verse_id'    => $payload['daily_verse_id'] ?? null,
        ]);

        return [
            'title' => mb_substr($title, 0, 100),
            'body'  => mb_strlen($body) > self::BODY_MAX ? mb_substr($body, 0, self::BODY_MAX - 1) . '…' : $body,
            'data'  => array_filter($data, fn ($v) => $v !== ''),
        ];
    }

    private static function fallbackBody(?string $type, AppNotification $notification): string
    {
        $key = 'notifications.fallback.' . $type;

        return __(Lang::has($key) ? $key : 'notifications.fallback.default', [
            'actor' => $notification->actor_name ?: __('notifications.someone'),
            'title' => $notification->testimony_title ? __('notifications.quoted_title', ['title' => $notification->testimony_title]) : '',
        ]);
    }

    /** Type brut (chaîne), sans passer par le cast enum qui échoue sur une valeur inconnue. */
    private static function rawType(AppNotification $notification): ?string
    {
        $raw = $notification->getAttributes()['type'] ?? null;

        return $raw instanceof NotificationType ? $raw->value : ($raw === null ? null : (string) $raw);
    }
}

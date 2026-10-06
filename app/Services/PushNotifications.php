<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Jobs\SendPushNotificationJob;
use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\UserSetting;
use Throwable;

/**
 * Transforme une notification de l'application (AppNotification) en notification push
 * (docs/fonctionnalites/notifications-push.md) : titre et texte en français, données de
 * navigation lues par l'application mobile (lib/services/fcm_service.dart), respect des
 * préférences push de l'utilisateur (user_settings.push_*).
 */
class PushNotifications
{
    /** Préférence (colonne de user_settings) qui commande chaque type. Absent = toujours envoyé. */
    private const PREFERENCES = [
        'comment'                => 'push_comments',
        'reply'                  => 'push_comments',
        'mention'                => 'push_comments',
        'like'                   => 'push_likes',
        'prayer'                 => 'push_prayers',
        'testimony_approved'     => 'push_approval',
        'testimony_rejected'     => 'push_approval',
        'pending_correction'     => 'push_approval',
        'new_followed_testimony' => 'push_new_followed',
        'live_started'           => 'push_new_followed',
        'prayer_encouragement'   => 'push_prayers',
    ];

    private const TITLES = [
        'like'                   => 'Nouvelle réaction',
        'comment'                => 'Nouveau commentaire',
        'reply'                  => 'Nouvelle réponse',
        'mention'                => 'Vous avez été mentionné',
        'follow'                 => 'Nouvel abonné',
        'share'                  => 'Témoignage partagé',
        'testimony_approved'     => 'Témoignage approuvé',
        'testimony_rejected'     => 'Témoignage non publié',
        'new_followed_testimony' => 'Nouveau témoignage',
        'pending_correction'     => 'Correction demandée',
        'organization_verified'  => 'Organisation vérifiée',
        'organization_rejected'  => 'Vérification refusée',
        'live_started'           => 'En direct',
        'prayer_encouragement'   => 'Encouragement reçu',
        'prayer_session_started' => 'Session de prière',
        'prayer_session_reminder' => 'Session de prière bientôt',
    ];

    public const DEFAULT_TITLE = 'Témoignages';

    private const BODY_MAX = 200;

    /**
     * Met en file l'envoi push d'une notification (enregistrée ou non). Ne lève jamais d'exception :
     * un échec du push ne doit pas empêcher la création de la notification dans l'application.
     */
    public static function dispatchFor(AppNotification $notification): void
    {
        try {
            $recipientId = $notification->recipient_id;
            if (!$recipientId || !app(FcmClient::class)->isConfigured()) {
                return;
            }

            if (!self::allows($recipientId, self::rawType($notification))) {
                return;
            }

            if (!DeviceToken::where('user_id', $recipientId)->exists()) {
                return;
            }

            $message = self::message($notification);
            SendPushNotificationJob::dispatch($recipientId, $message['title'], $message['body'], $message['data']);
        } catch (Throwable $e) {
            report($e);
        }
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

        $title = self::TITLES[$type] ?? null;
        if (!$title) {
            $title = trim((string) ($payload['title'] ?? '')) ?: self::DEFAULT_TITLE;
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
            'testimony_id'    => $notification->testimony_id,
            'comment_id'      => $notification->comment_id,
            'actor_id'        => $notification->actor_id,
            'notification_id' => $notification->id,
            'live_id'         => $payload['live_id'] ?? null,
            // Requêtes et sessions de prière (docs/fonctionnalites/sessions-de-priere.md)
            'prayer_request_id' => $payload['prayer_request_id'] ?? null,
            'prayer_session_id' => $payload['prayer_session_id'] ?? null,
        ]);

        return [
            'title' => mb_substr($title, 0, 100),
            'body'  => mb_strlen($body) > self::BODY_MAX ? mb_substr($body, 0, self::BODY_MAX - 1) . '…' : $body,
            'data'  => array_filter($data, fn ($v) => $v !== ''),
        ];
    }

    private static function fallbackBody(?string $type, AppNotification $notification): string
    {
        $actor = $notification->actor_name ?: "Quelqu'un";
        $title = $notification->testimony_title ? ' « ' . $notification->testimony_title . ' »' : '';

        return match ($type) {
            'like'                   => "{$actor} a réagi à votre témoignage{$title}",
            'comment'                => "{$actor} a commenté votre témoignage{$title}",
            'reply'                  => "{$actor} a répondu à votre commentaire",
            'mention'                => "{$actor} vous a mentionné",
            'follow'                 => "{$actor} vous suit maintenant",
            'share'                  => "{$actor} a partagé votre témoignage{$title}",
            'testimony_approved'     => "Votre témoignage{$title} a été approuvé",
            'testimony_rejected'     => "Votre témoignage{$title} n'a pas été publié",
            'new_followed_testimony' => "{$actor} a partagé un nouveau témoignage{$title}",
            'pending_correction'     => "Votre témoignage{$title} doit être corrigé",
            'organization_verified'  => 'Votre organisation a été vérifiée',
            'organization_rejected'  => 'La vérification de votre organisation a été refusée',
            'live_started'           => "{$actor} est en direct",
            default                  => 'Vous avez une nouvelle notification',
        };
    }

    /** Type brut (chaîne), sans passer par le cast enum qui échoue sur une valeur inconnue. */
    private static function rawType(AppNotification $notification): ?string
    {
        $raw = $notification->getAttributes()['type'] ?? null;

        return $raw instanceof NotificationType ? $raw->value : ($raw === null ? null : (string) $raw);
    }
}

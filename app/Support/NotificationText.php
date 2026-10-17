<?php

namespace App\Support;

use App\Models\AppNotification;
use Illuminate\Support\Facades\Lang;

/**
 * Texte traduisible d'une notification de l'application (docs/fonctionnalites/langues.md).
 *
 * À la création, la clé de traduction et ses paramètres sont gardés dans payload.i18n ; la colonne
 * `message` reçoit le texte français (exports, anciennes versions). À la lecture, AppNotification::message
 * est rendu dans la langue courante : celle de la requête (liste des notifications) ou celle du
 * destinataire (push, voir PushNotifications).
 *
 *   AppNotification::create([... , ...NotificationText::fields('notifications.testimony_approved', ['title' => $t->title])]);
 *   AppNotification::create([... , ...NotificationText::fields('events.notify_cancelled', ['title' => $e->title], ['event_id' => $e->id])]);
 */
final class NotificationText
{
    public const MAX_LENGTH = 255;

    /**
     * Champs `message` et `payload` d'une notification : payload existant complété de i18n.
     *
     * @param array<string, scalar|null> $params
     * @param array<string, mixed>       $payload
     * @return array{message: string, payload: array<string, mixed>}
     */
    public static function fields(string $key, array $params = [], array $payload = []): array
    {
        $params = array_map(fn ($v) => $v === null ? '' : (string) $v, $params);

        return [
            'message' => self::clip(__($key, $params, Locales::DEFAULT)),
            'payload' => $payload + ['i18n' => ['key' => $key, 'params' => $params]],
        ];
    }

    /** Texte de la notification dans la langue courante (texte enregistré si aucune clé connue). */
    public static function render(?array $payload, ?string $stored): ?string
    {
        $i18n = is_array($payload) ? ($payload['i18n'] ?? null) : null;
        if (!is_array($i18n) || !is_string($i18n['key'] ?? null) || !Lang::has($i18n['key'])) {
            return $stored;
        }

        return self::clip(__($i18n['key'], (array) ($i18n['params'] ?? [])));
    }

    /** Texte d'une notification dans une langue donnée (push). */
    public static function in(AppNotification $notification, ?string $locale): ?string
    {
        return Locales::using($locale, fn () => $notification->message);
    }

    private static function clip(string $text): string
    {
        return mb_substr($text, 0, self::MAX_LENGTH);
    }
}

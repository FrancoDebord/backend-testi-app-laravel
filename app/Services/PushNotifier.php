<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use App\Support\Locales;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envoi immédiat d'une notification push à tous les appareils d'un utilisateur
 * (docs/fonctionnalites/notifications-push.md). Utilisé par SendPushNotificationJob, push:test,
 * POST /me/push-test et le bouton de test du back-office (sendTest).
 *
 * Ne lève jamais d'exception : un échec (FCM non joignable, jeton OAuth2 indisponible…) est
 * journalisé (un message par appareil non servi, avec l'utilisateur) et compté dans le résumé,
 * il ne fait jamais échouer la requête API.
 */
class PushNotifier
{
    public function __construct(private FcmClient $fcm) {}

    /**
     * @param array<string, mixed> $data converti en chaînes (exigence de FCM)
     * @return array{sent: int, invalid: int, retry: int, failed: int, disabled: bool, error: ?string, results: array<string, string>}
     *         results : [jeton => FcmClient::SENT|INVALID_TOKEN|RETRY|FAILED|DISABLED]
     */
    public function sendToUser(User|string $user, string $title, string $body, array $data = []): array
    {
        $userId = $user instanceof User ? $user->id : $user;
        $summary = ['sent' => 0, 'invalid' => 0, 'retry' => 0, 'failed' => 0, 'disabled' => false, 'error' => null, 'results' => []];

        try {
            if (!$this->fcm->isConfigured()) {
                Log::debug('FCM non configuré : notification push ignorée.', ['user_id' => $userId]);
                $summary['disabled'] = true;
                return $summary;
            }

            $tokens = DeviceToken::where('user_id', $userId)->pluck('token')->all();
            if ($tokens === []) {
                return $summary;
            }

            try {
                $results = $this->fcm->sendMulticast($tokens, $title, $body, $data);
            } catch (Throwable $e) {
                // Jeton OAuth2 indisponible, erreur réseau : erreur temporaire pour tous les appareils.
                Log::error('FCM : envoi impossible.', ['user_id' => $userId, 'error' => $e->getMessage()]);
                $summary['error'] = $e->getMessage();
                $results = array_fill_keys($tokens, FcmClient::RETRY);
            }

            $summary['results'] = $results;
            $this->logFailures($userId, $results, $summary['error']);
            foreach ($results as $result) {
                match ($result) {
                    FcmClient::SENT          => $summary['sent']++,
                    FcmClient::INVALID_TOKEN => $summary['invalid']++,
                    FcmClient::RETRY         => $summary['retry']++,
                    FcmClient::FAILED        => $summary['failed']++,
                    default                  => null,
                };
            }

            $served = array_keys(array_filter($results, fn ($r) => $r === FcmClient::SENT));
            if ($served !== []) {
                DeviceToken::whereIn('token', $served)->update(['last_used_at' => now()]);
            }
        } catch (Throwable $e) {
            report($e);
            $summary['error'] ??= $e->getMessage();
        }

        return $summary;
    }

    /**
     * Notification de test, envoyée tout de suite (sans file) à tous les appareils du membre, dans sa langue.
     * Résultat par appareil : sent | invalid_token (jeton périmé, supprimé) | retry | failed | disabled.
     *
     * @return array{total: int, sent: int, disabled: bool, error: ?string, devices: list<array{id: int, platform: ?string, token: string, result: string, error: ?string, last_used_at: ?string}>}
     */
    public function sendTest(User $user): array
    {
        $devices = DeviceToken::where('user_id', $user->id)->orderByDesc('updated_at')->get(['id', 'token', 'platform', 'last_used_at']);
        [$title, $body] = Locales::using(Locales::forUser($user), fn () => [__('notifications.test.title'), __('notifications.test.body')]);

        $summary = $devices->isEmpty()
            ? ['sent' => 0, 'disabled' => !$this->fcm->isConfigured(), 'error' => null, 'results' => []]
            : $this->sendToUser($user, $title, $body, ['type' => 'test', 'id' => $user->id]);

        return [
            'total'    => $devices->count(),
            'sent'     => $summary['sent'],
            'disabled' => $summary['disabled'],
            'error'    => $summary['error'],
            'devices'  => $devices->map(function (DeviceToken $device) use ($summary) {
                $result = $summary['results'][$device->token]
                    ?? ($summary['disabled'] ? FcmClient::DISABLED : FcmClient::FAILED);

                return [
                    'id'           => $device->id,
                    'platform'     => $device->platform,
                    'token'        => self::mask($device->token),
                    'result'       => $result,
                    'error'        => $result === FcmClient::SENT ? null : ($this->fcm->lastError($device->token) ?? $summary['error']),
                    'last_used_at' => $device->last_used_at?->toIso8601String(),
                ];
            })->values()->all(),
        ];
    }

    /** Début du jeton seulement (journaux, réponses) : un jeton FCM complet permet d'écrire à l'appareil. */
    public static function mask(string $token): string
    {
        return mb_substr($token, 0, 12) . '…';
    }

    /** Un message par appareil non servi, avec l'utilisateur et l'erreur FCM. */
    private function logFailures(string $userId, array $results, ?string $error): void
    {
        foreach ($results as $token => $result) {
            if ($result === FcmClient::SENT || $result === FcmClient::DISABLED) {
                continue;
            }
            Log::warning('FCM : notification non délivrée.', [
                'user_id' => $userId,
                'token'   => self::mask((string) $token),
                'result'  => $result,
                'error'   => $this->fcm->lastError((string) $token) ?? $error,
            ]);
        }
    }
}

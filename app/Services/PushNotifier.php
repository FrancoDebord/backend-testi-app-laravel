<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envoi immédiat d'une notification push à tous les appareils d'un utilisateur
 * (docs/fonctionnalites/notifications-push.md). Utilisé par SendPushNotificationJob et push:test.
 *
 * Ne lève jamais d'exception : un échec (FCM non joignable, jeton OAuth2 indisponible…) est
 * journalisé et compté dans le résumé, il ne fait jamais échouer la requête API.
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
}

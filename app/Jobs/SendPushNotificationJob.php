<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Services\FcmClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Envoie une notification push à tous les appareils d'un utilisateur
 * (docs/fonctionnalites/notifications-push.md).
 *
 * Nouvel essai (3 au total) seulement si aucun appareil n'a reçu la notification et qu'au moins
 * un envoi a échoué temporairement (429, 5xx, jeton OAuth2 indisponible) : un appareil déjà servi
 * ne reçoit jamais la même notification deux fois.
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Délais en secondes entre les essais. */
    public array $backoff = [30, 120];

    /**
     * @param array<string, mixed> $data données de navigation (type, testimony_id…), converties en chaînes
     */
    public function __construct(
        public string $userId,
        public string $title,
        public string $body,
        public array $data = [],
    ) {}

    public function handle(FcmClient $fcm): void
    {
        if (!$fcm->isConfigured()) {
            Log::debug('FCM non configuré : notification push ignorée.', ['user_id' => $this->userId]);
            return;
        }

        $tokens = DeviceToken::where('user_id', $this->userId)->pluck('token');
        $sent = 0;
        $retry = 0;

        foreach ($tokens as $token) {
            $result = $fcm->send($token, $this->title, $this->body, $this->data);
            if ($result === FcmClient::SENT) {
                $sent++;
            } elseif ($result === FcmClient::RETRY) {
                $retry++;
            }
        }

        if ($retry > 0 && $sent === 0) {
            throw new RuntimeException("FCM : envoi impossible pour l'utilisateur {$this->userId}, nouvel essai prévu.");
        }
    }
}

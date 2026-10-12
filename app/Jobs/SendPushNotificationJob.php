<?php

namespace App\Jobs;

use App\Services\PushNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Envoie une notification push à tous les appareils d'un utilisateur
 * (docs/fonctionnalites/notifications-push.md), dans la file `services.fcm.queue` (PUSH_QUEUE,
 * défaut « push ») : une conversion vidéo longue de la file « default » ne retarde pas les push.
 *
 * Nouvel essai (3 au total) seulement si aucun appareil n'a reçu la notification et qu'au moins
 * un envoi a échoué temporairement (429, 5xx, réseau, jeton OAuth2 indisponible) : un appareil
 * déjà servi ne reçoit jamais la même notification deux fois. Envoyée après la réponse HTTP
 * (PUSH_DELIVERY=after_response), la tâche ne réessaie jamais et ne lève rien.
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Délais en secondes entre les essais. */
    public array $backoff = [30, 120];

    /** Faux pour un envoi sans file (après la réponse HTTP) : pas de nouvel essai possible. */
    public bool $retryOnFailure = true;

    /**
     * @param array<string, mixed> $data données de navigation (type, id, testimony_id…), converties en chaînes
     */
    public function __construct(
        public string $userId,
        public string $title,
        public string $body,
        public array $data = [],
    ) {
        $this->onQueue(config('services.fcm.queue') ?: null);
    }

    public function handle(PushNotifier $notifier): void
    {
        $summary = $notifier->sendToUser($this->userId, $this->title, $this->body, $this->data);

        if ($this->retryOnFailure && $summary['retry'] > 0 && $summary['sent'] === 0) {
            throw new RuntimeException("FCM : envoi impossible pour l'utilisateur {$this->userId}, nouvel essai prévu.");
        }
    }
}

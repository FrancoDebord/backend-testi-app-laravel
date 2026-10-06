<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\Prophecy;
use App\Services\FcmClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Prévient les téléphones de l'auteur qu'une parole prophétique a changé (site, autre appareil),
 * par un message silencieux : l'application reprogramme aussitôt son rappel local, sans attendre
 * l'ouverture des paroles. Voir docs/fonctionnalites/paroles-prophetiques.md#rappels
 *
 * Le message est construit à l'envoi, depuis l'état enregistré : un envoi retardé porte toujours
 * le dernier réglage. Il contient le réglage du rappel, l'application n'a rien à télécharger.
 */
class SyncProphecyReminderJob implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'prophecy_sync';

    public int $tries = 3;

    /** Délais en secondes entre les essais. */
    public array $backoff = [30, 120];

    public function __construct(
        public string $userId,
        public string $prophecyId,
    ) {}

    public function handle(FcmClient $fcm): void
    {
        if (!$fcm->isConfigured()) {
            return;
        }

        $tokens = DeviceToken::where('user_id', $this->userId)->whereIn('platform', ['android', 'ios'])->pluck('token');
        if ($tokens->isEmpty()) {
            return;
        }

        $data = self::payload(Prophecy::withTrashed()->where('user_id', $this->userId)->find($this->prophecyId), $this->prophecyId);
        $sent = 0;
        $retry = 0;
        foreach ($tokens as $token) {
            $result = $fcm->sendData($token, $data);
            if ($result === FcmClient::SENT) {
                $sent++;
            } elseif ($result === FcmClient::RETRY) {
                $retry++;
            }
        }

        if ($retry > 0 && $sent === 0) {
            throw new RuntimeException("FCM : synchronisation du rappel impossible pour l'utilisateur {$this->userId}, nouvel essai prévu.");
        }
    }

    /**
     * Données du message (chaînes, lues par lib/features/prophecies/data/prophecy_reminders.dart) :
     * `deleted` = true → rappel annulé ; sinon réglage du rappel (absent = pas de rappel),
     * état et titre affiché dans la notification.
     *
     * @return array<string, string>
     */
    public static function payload(?Prophecy $prophecy, string $id): array
    {
        if (!$prophecy || $prophecy->trashed()) {
            return ['type' => self::TYPE, 'prophecy_id' => $id, 'deleted' => 'true'];
        }

        return FcmClient::stringifyData([
            'type'               => self::TYPE,
            'prophecy_id'        => $prophecy->id,
            'deleted'            => false,
            'status'             => $prophecy->status,
            'display_title'      => self::displayTitle($prophecy),
            'reminder_frequency' => $prophecy->reminder_frequency,
            'reminder_time'      => $prophecy->reminder_frequency ? $prophecy->reminder_time : null,
            'reminder_weekday'   => $prophecy->reminder_frequency === 'weekly' ? $prophecy->reminder_weekday : null,
        ]);
    }

    /** Même règle que Prophecy.displayTitle dans l'application : titre, début du texte ou « Parole du … ». */
    private static function displayTitle(Prophecy $prophecy): string
    {
        if (filled($prophecy->title)) {
            return mb_substr($prophecy->title, 0, 150);
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $prophecy->body_text));
        if ($text !== '') {
            return mb_strlen($text) > 60 ? mb_substr($text, 0, 60) . '…' : $text;
        }

        return 'Parole du ' . $prophecy->received_on?->format('j/n/Y');
    }
}

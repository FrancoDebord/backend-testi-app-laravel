<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\FcmClient;
use App\Services\PushNotifier;
use Illuminate\Console\Command;

class SendTestPush extends Command
{
    protected $signature = "push:test
        {user : Adresse e-mail ou identifiant de l'utilisateur}
        {--title=Test ✅ : Titre de la notification}
        {--body=Ceci est une notification de test. : Texte de la notification}";

    protected $description = "Envoie immédiatement (sans file d'attente) une notification push de test à tous les appareils d'un utilisateur";

    public function handle(FcmClient $fcm, PushNotifier $notifier): int
    {
        $identifier = (string) $this->argument('user');
        $user = User::where('email', $identifier)->orWhere('id', $identifier)->first();
        if (!$user) {
            $this->error("Utilisateur introuvable : {$identifier}");
            return self::FAILURE;
        }

        if (!$fcm->isConfigured()) {
            $this->error('FCM non configuré : ' . $fcm->configurationProblem());
            $this->line('Corriger .env (FIREBASE_CREDENTIALS, FCM_ENABLED), puis php artisan config:clear (ou config:cache).');
            return self::FAILURE;
        }

        $this->line('Projet Firebase : ' . $fcm->projectId());

        $devices = DeviceToken::where('user_id', $user->id)->pluck('platform', 'token');
        if ($devices->isEmpty()) {
            $this->warn("Aucun appareil enregistré pour {$user->display_name} (l'application enregistre son jeton après connexion).");
            return self::FAILURE;
        }

        $summary = $notifier->sendToUser($user, (string) $this->option('title'), (string) $this->option('body'), [
            'type' => 'test',
        ]);

        if ($summary['error']) {
            $this->error($summary['error']);
        }
        $senderMismatch = false;
        foreach ($summary['results'] as $token => $result) {
            $error = $result === FcmClient::SENT ? null : $fcm->lastError((string) $token);
            $senderMismatch = $senderMismatch || str_contains(strtolower((string) $error), 'senderid mismatch');
            $this->line(sprintf('%s  %s…  %s%s', str_pad((string) ($devices[$token] ?? '?'), 8), mb_substr((string) $token, 0, 16),
                $result, $error ? "  ({$error})" : ''));
        }
        if ($senderMismatch) {
            $this->warn("SenderId mismatch : ce jeton a été créé par l'application avec un autre projet Firebase que « {$fcm->projectId()} ». "
                . "Configurer l'application sur « {$fcm->projectId()} » (google-services.json, firebase_options.dart : "
                . "flutterfire configure --project={$fcm->projectId()}), la réinstaller, puis se reconnecter.");
        }

        $this->info("{$summary['sent']} / {$devices->count()} appareil(s) servi(s).");
        if ($summary['invalid'] > 0) {
            $this->line("{$summary['invalid']} jeton(s) périmé(s) supprimé(s).");
        }

        return $summary['sent'] > 0 ? self::SUCCESS : self::FAILURE;
    }
}

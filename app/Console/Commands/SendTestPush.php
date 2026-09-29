<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\FcmClient;
use Illuminate\Console\Command;
use Throwable;

class SendTestPush extends Command
{
    protected $signature = "push:test
        {user : Identifiant ou adresse e-mail de l'utilisateur}
        {--title=Test TestiApp : Titre de la notification}
        {--body=Ceci est une notification de test. : Texte de la notification}";

    protected $description = "Envoie immédiatement une notification push de test à tous les appareils d'un utilisateur";

    public function handle(FcmClient $fcm): int
    {
        $identifier = (string) $this->argument('user');
        $user = User::where('id', $identifier)->orWhere('email', $identifier)->first();
        if (!$user) {
            $this->error("Utilisateur introuvable : {$identifier}");
            return self::FAILURE;
        }

        if (!$fcm->isConfigured()) {
            $this->error('FCM non configuré : renseigner FIREBASE_CREDENTIALS (FCM_ENABLED différent de false), puis php artisan config:cache.');
            return self::FAILURE;
        }

        $tokens = DeviceToken::where('user_id', $user->id)->get();
        if ($tokens->isEmpty()) {
            $this->warn("Aucun appareil enregistré pour {$user->display_name} (l'application enregistre son jeton après connexion).");
            return self::FAILURE;
        }

        $sent = 0;
        foreach ($tokens as $device) {
            try {
                $result = $fcm->send($device->token, (string) $this->option('title'), (string) $this->option('body'), [
                    'type' => 'test',
                ]);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }
            $sent += $result === FcmClient::SENT ? 1 : 0;
            $this->line(sprintf('%s  %s…  %s', str_pad($device->platform, 8), mb_substr($device->token, 0, 16), $result));
        }

        $this->info("{$sent} / {$tokens->count()} appareil(s) servi(s).");

        return $sent > 0 ? self::SUCCESS : self::FAILURE;
    }
}

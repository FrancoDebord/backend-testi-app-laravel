<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Client du service vidéo des témoignages en direct (config/livekit.php)
        $this->app->singleton(\App\Services\LiveKit\LiveKitClient::class, fn () => \App\Services\LiveKit\LiveKitClient::fromConfig());

        // Envoi des notifications push FCM (config/services.php › fcm)
        $this->app->singleton(\App\Services\FcmClient::class, fn () => \App\Services\FcmClient::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Chaque notification de l'application part aussi en push (docs/fonctionnalites/notifications-push.md)
        \App\Models\AppNotification::observe(\App\Observers\AppNotificationObserver::class);

        // Dates en français (« il y a 2 heures », « lun. 28 ») : l'interface n'existe qu'en français,
        // quelle que soit APP_LOCALE (parfois « en » sur un poste de développement).
        \Illuminate\Support\Carbon::setLocale('fr');
    }
}

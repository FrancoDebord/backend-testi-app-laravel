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

        // Français par défaut quelle que soit APP_LOCALE (parfois « en » sur un poste de développement) :
        // textes, dates (« il y a 2 heures »). Chaque requête choisit ensuite sa langue (App\Http\Middleware\SetLocale),
        // chaque push celle de son destinataire. Voir docs/fonctionnalites/langues.md
        $this->app['translator']->setFallback(\App\Support\Locales::DEFAULT);
        \App\Support\Locales::apply(\App\Support\Locales::DEFAULT);
    }
}

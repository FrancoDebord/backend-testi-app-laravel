<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Témoignages en direct : clôture des directs abandonnés (docs/fonctionnalites/lives.md)
Schedule::command('lives:cleanup')->everyFiveMinutes()->withoutOverlapping();

// Sessions de prière : rappel aux inscrits 15 minutes avant (docs/fonctionnalites/sessions-de-priere.md)
Schedule::command('prayer-sessions:remind')->everyFiveMinutes()->withoutOverlapping();

// Événements : sondage « A-t-il vraiment eu lieu ? » aux participants, une fois après la fin (docs/fonctionnalites/evenements.md)
Schedule::command('events:send-surveys')->hourly()->withoutOverlapping();

// Vidéos Bunny Stream : envois jamais terminés (> 24 h) passés en échec, vidéo Bunny supprimée (docs/fonctionnalites/videos-bunny-stream.md)
Schedule::command('videos:expire-uploads')->hourly()->withoutOverlapping();
// Filet de sécurité du webhook Bunny : état relu chez Bunny, alerte des vidéos bloquées (docs/fonctionnalites/videos-bunny-stream.md)
Schedule::command('videos:sync-bunny-status')->everyTenMinutes()->withoutOverlapping();

// Témoignages (docs/fonctionnalites/gestion-des-temoignages.md) : statistiques de la veille (heure du Bénin),
// puis corbeille de plus de 30 jours vidée (suppression définitive, vidéo Bunny comprise).
Schedule::command('testimonies:aggregate-stats')->dailyAt('00:20')->timezone('Africa/Porto-Novo')->withoutOverlapping();
Schedule::command('testimonies:purge-trash')->dailyAt('03:30')->timezone('Africa/Porto-Novo')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

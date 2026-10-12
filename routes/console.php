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

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

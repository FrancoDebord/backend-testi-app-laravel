<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Témoignages en direct : clôture des directs abandonnés (docs/fonctionnalites/lives.md)
Schedule::command('lives:cleanup')->everyFiveMinutes()->withoutOverlapping();

// Sessions de prière : rappel aux inscrits 15 minutes avant (docs/fonctionnalites/sessions-de-priere.md)
Schedule::command('prayer-sessions:remind')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

<?php

/*
|--------------------------------------------------------------------------
| Temps de prière (docs/fonctionnalites/temps-de-priere.md)
|--------------------------------------------------------------------------
| « Je prie » (requête de prière ou témoignage) ouvre un temps de prière : la prière n'est
| enregistrée qu'après `focus_seconds` secondes (5 min par défaut), comptées par le serveur
| depuis l'appel …/pray/start (App\Services\PrayerFocus). 0 désactive entièrement la règle
| (période de transition : les anciennes versions de l'application prient directement).
*/

return [
    'focus_seconds' => (int) env('PRAYER_FOCUS_SECONDS', 300),
];

<?php

use App\Services\VerseDesignOfTheDay;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/**
 * Import en ligne des 424 versets du jour classés par thème (storage/dailyverse/versets_du_jour.json,
 * textes Louis Segond déjà dans le fichier) : lancé une fois par POST /_deploy, sans SSH.
 * Un verset par jour à partir du lendemain (Africa/Porto-Novo), dans l'ordre des thèmes.
 * Voir docs/fonctionnalites/verset-du-jour.md
 */
return new class extends Migration
{
    public function up(): void
    {
        $file = storage_path('dailyverse/versets_du_jour.json');
        if (app()->runningUnitTests() || !is_file($file)) {
            return;
        }

        $start = Carbon::parse(VerseDesignOfTheDay::todayDate())->addDay()->toDateString();
        $code  = Artisan::call('verse:import', ['file' => $file, '--start' => $start]);

        if ($code !== 0) {
            throw new RuntimeException("verse:import a échoué :\n" . Artisan::output());
        }
    }

    public function down(): void
    {
        // Données de contenu : jamais supprimées par un retour en arrière.
    }
};

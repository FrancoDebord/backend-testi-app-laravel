<?php

namespace App\Console\Commands;

use App\Models\DailyVerse;
use Database\Seeders\DailyVerseSeeder;
use Illuminate\Console\Command;

/**
 * Génère les versets du jour pour N mois en avant.
 * Cycle : Semaine 1 (Fidélité) → 2 (Témoignages) → 3 (Promesses) → 4 (Obéissance) → repeat.
 *
 * Usage :
 *   php artisan verse:schedule                    # 3 mois à partir d'aujourd'hui
 *   php artisan verse:schedule --start=2026-07-01 # depuis une date précise
 *   php artisan verse:schedule --months=6         # 6 mois
 */
class ScheduleVerses extends Command
{
    protected $signature = 'verse:schedule
        {--start=  : Date de début (YYYY-MM-DD), défaut : aujourd\'hui}
        {--months=3 : Nombre de mois à générer}';

    protected $description = 'Programme les versets du jour sur N mois avec rotation thématique hebdomadaire';

    public function handle(): int
    {
        $startDate = $this->option('start') ?? now()->toDateString();
        $months    = (int) $this->option('months');

        if (!strtotime($startDate)) {
            $this->error("Date invalide : {$startDate}");
            return Command::FAILURE;
        }

        $pool      = DailyVerseSeeder::versePool(); // 28 versets (4 semaines)
        $poolSize  = count($pool);
        $totalDays = $months * 30;
        $created   = 0;
        $skipped   = 0;

        $this->info("Génération de {$totalDays} jours de versets depuis {$startDate}...");
        $bar = $this->output->createProgressBar($totalDays);
        $bar->start();

        for ($i = 0; $i < $totalDays; $i++) {
            $date  = date('Y-m-d', strtotime("{$startDate} +{$i} days"));
            $entry = $pool[$i % $poolSize];

            $exists = DailyVerse::where('scheduled_date', $date)->exists();

            if (!$exists) {
                DailyVerse::create([
                    'verse_text'     => $entry['text'],
                    'reference'      => $entry['ref'],
                    'scheduled_date' => $date,
                    'theme'          => $entry['theme'],
                    'week_number'    => $entry['week'],
                    'is_active'      => true,
                ]);
                $created++;
            } else {
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ {$created} versets créés, {$skipped} dates déjà existantes ignorées.");

        return Command::SUCCESS;
    }
}

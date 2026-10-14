<?php

namespace App\Console\Commands;

use App\Services\TestimonyStats;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Agrégation quotidienne des statistiques des témoignages (testimony_daily_stats), planifiée chaque
 * nuit pour la veille et l'avant-veille (rattrapage). Peut être relancée sans doublon.
 * Purge aussi les événements bruts plus anciens que config('testimonies.events_keep_days').
 * Voir docs/fonctionnalites/gestion-des-temoignages.md
 */
class AggregateTestimonyStats extends Command
{
    protected $signature = 'testimonies:aggregate-stats
                            {--date= : Jour à calculer (AAAA-MM-JJ) ; défaut : hier et avant-hier}
                            {--days= : Recalculer les N derniers jours (hier compris)}';

    protected $description = 'Calcule les statistiques quotidiennes des témoignages';

    public function handle(TestimonyStats $stats): int
    {
        $today = CarbonImmutable::parse(TestimonyStats::today());
        $dates = match (true) {
            (bool) $this->option('date') => [(string) $this->option('date')],
            (bool) $this->option('days') => collect(range(1, max(1, (int) $this->option('days'))))->map(fn ($i) => $today->subDays($i)->toDateString())->all(),
            default                      => [$today->subDay()->toDateString(), $today->subDays(2)->toDateString()],
        };

        foreach ($dates as $date) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $this->error("Date invalide : {$date}");
                return self::FAILURE;
            }
            $this->info("{$date} : " . $stats->aggregate($date) . ' témoignage(s)');
        }

        $purged = DB::table('testimony_events')
            ->where('created_at', '<', now()->subDays((int) config('testimonies.events_keep_days', 120)))->delete();
        if ($purged) {
            $this->info("{$purged} événement(s) anciens supprimé(s).");
        }

        return self::SUCCESS;
    }
}

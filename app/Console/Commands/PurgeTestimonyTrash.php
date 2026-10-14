<?php

namespace App\Console\Commands;

use App\Models\Testimony;
use App\Services\TestimonyModeration;
use Illuminate\Console\Command;

/**
 * Vide la corbeille : témoignages supprimés depuis plus de config('testimonies.trash_days') jours,
 * supprimés définitivement (vidéo Bunny comprise), consignés dans admin_audit_logs. Planifiée chaque nuit.
 * Voir docs/fonctionnalites/gestion-des-temoignages.md
 */
class PurgeTestimonyTrash extends Command
{
    protected $signature = 'testimonies:purge-trash {--dry-run : Afficher sans supprimer}';

    protected $description = 'Supprime définitivement les témoignages restés dans la corbeille au-delà du délai';

    public function handle(TestimonyModeration $moderation): int
    {
        $limit = now()->subDays((int) config('testimonies.trash_days', 30));
        $count = 0;

        Testimony::onlyTrashed()->where('deleted_at', '<', $limit)->orderBy('deleted_at')
            ->chunkById(100, function ($items) use ($moderation, &$count) {
                foreach ($items as $t) {
                    if ($this->option('dry-run')) {
                        $this->line("{$t->id}  {$t->title}  (supprimé le {$t->deleted_at->format('d/m/Y')})");
                    } else {
                        $moderation->forceDelete($t, null);
                    }
                    $count++;
                }
            });

        $this->info(($this->option('dry-run') ? 'À supprimer : ' : 'Supprimés : ') . $count);

        return self::SUCCESS;
    }
}

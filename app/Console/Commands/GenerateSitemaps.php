<?php

namespace App\Console\Commands;

use App\Services\Sitemaps;
use Illuminate\Console\Command;

/**
 * Recalcule les sitemaps (planifié chaque nuit) : vidés puis reconstruits, pour que les versets du jour et
 * les directs du jour y entrent même sans publication. Voir docs/fonctionnalites/referencement.md
 */
class GenerateSitemaps extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Recalcule les sitemaps du site (témoignages publiés sur le web, vidéos, directs, versets)';

    public function handle(Sitemaps $sitemaps): int
    {
        Sitemaps::forget();
        foreach (['index', ...Sitemaps::NAMES] as $name) {
            $urls = substr_count($sitemaps->xml($name), '<loc>');
            $this->line(sprintf('sitemap-%s.xml : %d adresse(s)', $name, $urls));
        }
        $this->info('Sitemaps recalculés.');

        return Command::SUCCESS;
    }
}

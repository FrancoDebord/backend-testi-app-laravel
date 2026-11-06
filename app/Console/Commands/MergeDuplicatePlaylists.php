<?php

namespace App\Console\Commands;

use App\Models\TestimonyPlaylist;
use App\Services\ContentActionException;
use App\Services\TestimonyPlaylists;
use Illuminate\Console\Command;

/**
 * Fusionne les listes d'un même compte qui portent le même titre (casse et espaces ignorés) : la plus
 * ancienne est gardée, les témoignages des autres y sont ajoutés à la suite (dans l'ordre de création des
 * listes), puis les doublons sont supprimés. Ces doublons venaient de « Nouvelle liste » à la publication,
 * qui créait une liste par épisode (sans précédent ni suivant). Voir docs/fonctionnalites/listes-de-temoignages.md
 */
class MergeDuplicatePlaylists extends Command
{
    protected $signature = 'playlists:merge-duplicates
                            {--dry-run : Lister les listes à fusionner sans rien modifier}';

    protected $description = 'Fusionne les listes de même titre d\'un même compte';

    public function handle(TestimonyPlaylists $playlists): int
    {
        $dry = (bool) $this->option('dry-run');
        $merged = 0;

        $groups = TestimonyPlaylist::with(['user', 'testimonies'])->oldest()->get()
            ->groupBy(fn (TestimonyPlaylist $p) => $p->user_id . '|' . TestimonyPlaylists::titleKey($p->title))
            ->filter(fn ($group) => $group->count() > 1);

        foreach ($groups as $group) {
            /** @var TestimonyPlaylist $keep */
            $keep = $group->first();
            $this->line(sprintf('« %s » (%s) : %d listes → %s', $keep->title, $keep->user?->display_name, $group->count(), $keep->id));

            foreach ($group->slice(1) as $duplicate) {
                foreach ($duplicate->testimonies as $testimony) {
                    $this->line("    + {$testimony->title}");
                    if ($dry) {
                        continue;
                    }
                    try {
                        $playlists->add($keep, $testimony, $keep->user);
                    } catch (ContentActionException $e) {
                        $this->warn("    non ajouté ({$e->getMessage()})");
                    }
                }
                if (!$dry) {
                    $duplicate->delete();
                }
                $merged++;
            }
        }

        $this->info(($dry ? 'Listes à fusionner : ' : 'Listes fusionnées : ') . $merged);

        return self::SUCCESS;
    }
}

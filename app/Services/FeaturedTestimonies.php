<?php

namespace App\Services;

use App\Models\Testimony;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * « À la une » (GET /testimonies/featured, accueil du site), limite N (featured_limit) :
 * 1. le témoignage publié le plus récent y entre TOUJOURS (même s'il y a assez de mises à la une manuelles) ;
 * 2. puis les témoignages mis à la une manuellement (publiés, dans leurs dates), du plus récent au plus
 *    ancien, jusqu'à N au total ;
 * 3. si featured_fill_recent : complément avec les témoignages publiés suivants les plus récents jusqu'à N ;
 * 4. la liste est triée par date de publication (COALESCE(approved_at, created_at), latestPublished)
 *    décroissante, manuels et compléments mêlés.
 * featured_source = manual (mis à la une manuellement, badge « featured ») | recent (le plus récent,
 * sauf s'il est lui-même mis à la une manuellement, et le complément). Liste (identifiants) en cache
 * featured_cache_minutes ; vidée à chaque publication, dépublication, mise à la une ou suppression
 * (événements du modèle Testimony et App\Services\TestimonyModeration).
 * Voir docs/fonctionnalites/gestion-des-temoignages.md
 */
class FeaturedTestimonies
{
    private const CACHE_KEY = 'testimonies:featured:v2';

    /** @return Collection<int, Testimony> avec l'attribut featured_source */
    public function list(?int $limit = null, array $with = ['user']): Collection
    {
        $limit ??= (int) config('testimonies.featured_limit', 10);
        $ids = Cache::remember(self::CACHE_KEY . ':' . $limit . ':' . (int) config('testimonies.featured_fill_recent', true),
            now()->addMinutes((int) config('testimonies.featured_cache_minutes', 10)), fn () => $this->ids($limit));

        $models = Testimony::with($with)->published()->whereIn('id', array_keys($ids))->get()->keyBy('id');

        return collect($ids)
            ->map(function (string $source, string $id) use ($models) {
                $t = $models->get($id);
                $t?->setAttribute('featured_source', $source);

                return $t;
            })
            ->filter()->values();
    }

    public static function forget(): void
    {
        foreach (array_unique([(int) config('testimonies.featured_limit', 10), 10, 3, 5]) as $limit) {
            foreach ([0, 1] as $fill) {
                Cache::forget(self::CACHE_KEY . ':' . $limit . ':' . $fill);
            }
        }
    }

    /** @return array<string, string> [id => manual|recent], du plus récemment publié au plus ancien */
    private function ids(int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }
        $columns = ['id', 'approved_at', 'created_at'];

        // 1. Le plus récent publié.
        $newest = Testimony::published()->latestPublished()->first($columns);
        if (!$newest) {
            return [];
        }
        /** @var array<string, Testimony> $items */
        $items = [$newest->id => $newest];
        $sources = [$newest->id => 'recent'];

        // 2. Mises à la une manuelles, les plus récentes d'abord (le plus récent peut en faire partie).
        $manual = Testimony::manuallyFeatured()->latestPublished()->limit($limit)->get($columns);
        foreach ($manual as $t) {
            if (isset($items[$t->id])) {
                $sources[$t->id] = 'manual';
                continue;
            }
            if (count($items) >= $limit) {
                break;
            }
            $items[$t->id] = $t;
            $sources[$t->id] = 'manual';
        }

        // 3. Complément avec les suivants les plus récents.
        if (config('testimonies.featured_fill_recent', true) && count($items) < $limit) {
            $recent = Testimony::published()->whereNotIn('id', array_keys($items))
                ->latestPublished()->limit($limit - count($items))->get($columns);
            foreach ($recent as $t) {
                $items[$t->id] = $t;
                $sources[$t->id] = 'recent';
            }
        }

        // 4. Du plus récemment publié au plus ancien (tri stable).
        $ordered = collect($items)->sortByDesc(fn (Testimony $t) => $t->publishedAt()?->getTimestamp() ?? 0);

        return $ordered->mapWithKeys(fn (Testimony $t) => [$t->id => $sources[$t->id]])->all();
    }
}

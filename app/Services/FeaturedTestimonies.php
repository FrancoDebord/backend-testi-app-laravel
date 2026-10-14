<?php

namespace App\Services;

use App\Models\Testimony;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * « À la une » (GET /testimonies/featured, accueil du site) :
 * 1. témoignages mis à la une manuellement, publiés et dans leurs dates, triés par featured_order ;
 * 2. si featured_fill_recent : complément avec les témoignages publiés les plus récents (approuvés,
 *    publics, non supprimés) jusqu'à featured_limit. Ceux-là n'ont pas le badge « featured ».
 * Chaque témoignage porte featured_source = manual | recent. Liste (identifiants) en cache
 * featured_cache_minutes ; vidée à chaque modération, mise à la une ou suppression.
 * Voir docs/fonctionnalites/gestion-des-temoignages.md
 */
class FeaturedTestimonies
{
    private const CACHE_KEY = 'testimonies:featured:v1';

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
        foreach ([(int) config('testimonies.featured_limit', 10), 3, 5] as $limit) {
            foreach ([0, 1] as $fill) {
                Cache::forget(self::CACHE_KEY . ':' . $limit . ':' . $fill);
            }
        }
    }

    /** @return array<string, string> [id => manual|recent], dans l'ordre d'affichage */
    private function ids(int $limit): array
    {
        $manual = Testimony::manuallyFeatured()
            ->orderByRaw('CASE WHEN featured_order IS NULL THEN 1 ELSE 0 END')
            ->orderBy('featured_order')
            ->latestPublished()
            ->limit($limit)->pluck('id')->all();
        $ids = array_fill_keys($manual, 'manual');

        if (config('testimonies.featured_fill_recent', true) && count($ids) < $limit) {
            $recent = Testimony::published()->whereNotIn('id', $manual)
                ->latestPublished()->limit($limit - count($ids))->pluck('id')->all();
            $ids += array_fill_keys($recent, 'recent');
        }

        return $ids;
    }
}

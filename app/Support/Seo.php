<?php

namespace App\Support;

use App\Models\DailyVerse;
use App\Models\ThemeSemaine;
use Illuminate\Support\Str;

/**
 * Titres des pages publiques et liens entre témoignages et thèmes du verset du jour.
 * Voir docs/fonctionnalites/referencement.md
 */
final class Seo
{
    public const TITLE_MAX = 60;

    /** Robots d'une page publique indexable (aperçus d'images et de vidéos complets). */
    public const INDEX = 'index, follow, max-image-preview:large, max-video-preview:-1';

    /** « Guérie d'un cancer après la prière – Témoignage | My Testimony » (titre raccourci entre deux mots). */
    public static function title(string $main, ?string $kind = null): string
    {
        $suffix = ($kind ? " – {$kind}" : '') . ' | ' . config('seo.site_name', 'My Testimony');
        $room = self::TITLE_MAX - mb_strlen($suffix);
        $main = trim(preg_replace('/\s+/u', ' ', $main) ?? $main);
        if (mb_strlen($main) > $room) {
            // Trop long : sans le genre de page, puis coupé entre deux mots.
            $suffix = ' | ' . config('seo.site_name', 'My Testimony');
            $room = self::TITLE_MAX - mb_strlen($suffix);
            if (mb_strlen($main) > $room) {
                $main = rtrim(Str::limit($main, $room - 1, ''), " ,.;:-–") . '…';
            }
        }

        return $main . $suffix;
    }

    /** « Témoignages de guérison », « Témoignages d’emploi » (« Healing testimonies » en anglais). */
    public static function categoryTitle(\App\Models\Category $category): string
    {
        $name = $category->displayName();
        if (app()->getLocale() === 'en') {
            return __('seo.category.title', ['category' => $name]);
        }
        $lower = mb_strtolower($name);

        return preg_match('/^[aeiouyàâéèêëîïôöûüh]/u', $lower) ? "Témoignages d’{$lower}" : "Témoignages de {$lower}";
    }

    /**
     * Thème du verset du jour lié à une catégorie de témoignage (guerison → « La guérison divine »), d'après les
     * mots du slug du thème ; le plus récent déjà commencé (jamais un thème à venir). Null sinon.
     */
    public static function themeForCategory(?string $categorySlug): ?ThemeSemaine
    {
        $words = [
            'guerison'   => ['guerison'],
            'delivrance' => ['delivrance', 'liberte'],
            'salut'      => ['salut', 'conversion', 'nouvelle-naissance'],
            'provision'  => ['provision', 'pourvoit', 'abondance'],
            'famille'    => ['famille', 'foyer'],
            'mariage'    => ['mariage', 'couple'],
            'protection' => ['protection', 'refuge'],
            'emploi'     => ['travail', 'provision'],
            'etudes'     => ['sagesse', 'intelligence'],
        ][$categorySlug] ?? [];
        if (!$words) return null;

        return ThemeSemaine::query()
            ->where(fn ($q) => collect($words)->each(fn ($w) => $q->orWhere('slug', 'like', "%{$w}%")))
            ->where('date_debut', '<=', now()->toDateString())
            ->orderByDesc('date_debut')->first();
    }

    /** Verset clé d'un thème (texte du verset du jour qui porte sa référence), sinon le premier de la semaine. */
    public static function keyVerse(ThemeSemaine $theme): ?DailyVerse
    {
        return DailyVerse::where('theme_semaine_id', $theme->id)->where('reference', $theme->verset_cle)->first()
            ?? DailyVerse::where('theme_semaine_id', $theme->id)->orderBy('scheduled_date')->first();
    }
}

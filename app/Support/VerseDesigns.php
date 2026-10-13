<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Catalogue des designs du verset du jour (config/verse_designs.php) et couleurs générées
 * (config/verse_design_colors.php). Voir docs/fonctionnalites/designs-du-verset.md
 */
final class VerseDesigns
{
    /** Tous les designs, dans l'ordre du sélecteur. */
    public static function all(): Collection
    {
        return collect(config('verse_designs.designs', []))->sortBy('order')->values();
    }

    /** Designs actifs, dans l'ordre du sélecteur. */
    public static function active(): Collection
    {
        return self::all()->where('active', true)->values();
    }

    public static function activeIds(): array
    {
        return self::active()->pluck('id')->all();
    }

    public static function isActive(?string $id): bool
    {
        return $id !== null && in_array($id, self::activeIds(), true);
    }

    public static function find(string $id): ?array
    {
        return self::all()->firstWhere('id', $id);
    }

    /** Couleurs générées d'un design (encre, accent, voile, couches). */
    public static function colors(string $id): array
    {
        return config("verse_design_colors.designs.{$id}") ?? config('verse_design_colors.designs.minimal');
    }

    /** Rendu indicatif : « light » (texte foncé sur fond clair) ou « dark ». */
    public static function tone(string $id): string
    {
        return self::colors($id)['tone'] ?? (self::find($id)['tone_light_theme'] ?? 'light');
    }

    public static function fontIds(): array
    {
        return array_column(config('verse_designs.fonts', []), 'id');
    }

    public static function sizeIds(): array
    {
        return array_column(config('verse_designs.text_sizes', []), 'id');
    }

    public static function alignIds(): array
    {
        return array_column(config('verse_designs.text_aligns', []), 'id');
    }

    public static function defaults(): array
    {
        return config('verse_designs.defaults');
    }

    public static function fontFamily(string $id): string
    {
        return collect(config('verse_designs.fonts'))->firstWhere('id', $id)['family'] ?? 'Lora';
    }

    public static function sizeFactor(string $id): float
    {
        return (float) (collect(config('verse_designs.text_sizes'))->firstWhere('id', $id)['factor'] ?? 1.0);
    }

    /** Contenu de GET /verse-designs. */
    public static function catalogue(): array
    {
        return [
            'designs' => self::active()->map(fn (array $d) => [
                'id'    => $d['id'],
                'name'  => $d['name'],
                'mood'  => $d['mood'],
                'order' => $d['order'],
                'tone'  => self::tone($d['id']),
            ])->all(),
            'fonts'       => config('verse_designs.fonts'),
            'text_sizes'  => config('verse_designs.text_sizes'),
            'text_aligns' => config('verse_designs.text_aligns'),
            'text_size_range' => config('verse_designs.text_size_range'),
            'line_height' => config('verse_designs.line_height'),
            'defaults'    => [...self::defaults(), 'design_id' => null, 'design_chosen_on' => null],
        ];
    }

    // ─── Taille du texte ─────────────────────────────────────────────────────

    /** Longueur prise en compte : texte affiché, sans espaces de début ni de fin. */
    public static function displayLength(string $text): int
    {
        return mb_strlen(trim($text));
    }

    /**
     * Taille (px logiques) d'après la longueur, comme l'app :
     * max((max − (max − min) × clamp((len − 80) / 240, 0, 1)) × facteur, min).
     * $context : card | fullscreen | share.
     */
    public static function textSize(string $text, string $context, string $sizeId = 'medium'): float
    {
        $range = config("verse_designs.text_size_range.{$context}");
        $t = (self::displayLength($text) - config('verse_designs.length_from', 80)) / config('verse_designs.length_span', 240);
        $t = max(0.0, min(1.0, $t));
        $size = ($range['max'] - ($range['max'] - $range['min']) * $t) * self::sizeFactor($sizeId);

        return round(max($size, $range['min']), 2);
    }
}

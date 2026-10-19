<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Type d'événement (croisade, conférence, camp…) géré par les administrateurs, sur le site et par l'API.
 * `events.type` contient le nom court (`slug`), sans clé étrangère : le slug n'est donc jamais modifié.
 * Toutes les lectures (libellés, icônes, listes) passent par le cache `event_types`, vidé à chaque changement.
 * Voir docs/fonctionnalites/types-evenements.md
 */
class EventType extends Model
{
    public const CACHE_KEY = 'event_types';

    /** Icônes proposées : clé enregistrée (aussi utilisée par l'appli mobile) → classe Font Awesome du site. */
    public const ICONS = [
        'bullhorn'   => 'fa-bullhorn',
        'microphone' => 'fa-microphone',
        'chalkboard' => 'fa-chalkboard-user',
        'campground' => 'fa-campground',
        'route'      => 'fa-route',
        'music'      => 'fa-music',
        'dove'       => 'fa-dove',
        'calendar'   => 'fa-calendar-days',
        'church'     => 'fa-church',
        'users'      => 'fa-users',
        'hands'      => 'fa-hands-praying',
        'star'       => 'fa-star',
        'book'       => 'fa-book-bible',
        'heart'      => 'fa-heart',
        'globe'      => 'fa-earth-africa',
        'cross'      => 'fa-cross',
        'fire'       => 'fa-fire',
        'child'      => 'fa-children',
        'graduation' => 'fa-graduation-cap',
    ];

    public const DEFAULT_ICON = 'calendar';

    protected $fillable = ['slug', 'name_fr', 'name_en', 'icon', 'position', 'is_active'];

    protected function casts(): array
    {
        return [
            'position'  => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    // ─── Lecture (cache) ─────────────────────────────────────────────────────

    /** Tous les types (actifs et inactifs), dans l'ordre d'affichage. @return Collection<int, self> */
    public static function cached(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->ordered()->get());
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Types actifs, dans l'ordre. @return Collection<int, self> */
    public static function activeTypes(): Collection
    {
        return static::cached()->where('is_active', true)->values();
    }

    public static function findBySlug(?string $slug): ?self
    {
        return $slug === null || $slug === '' ? null : static::cached()->firstWhere('slug', $slug);
    }

    /** Nom court d'un type actif (filtres `?type=`), sinon null. */
    public static function activeSlug(?string $slug): ?string
    {
        $type = static::findBySlug((string) $slug);

        return $type?->is_active ? $type->slug : null;
    }

    /** Nom court d'un type existant (actif ou non) — filtres, sinon null. */
    public static function knownSlug(?string $slug): ?string
    {
        return static::findBySlug((string) $slug)?->slug;
    }

    /** Libellé localisé d'un slug ; slug inconnu → slug « humanisé » (« camp-jeunes » → « Camp jeunes »). */
    public static function labelFor(?string $slug): string
    {
        if ($type = static::findBySlug($slug)) {
            return $type->localizedName();
        }

        return $slug ? Str::ucfirst(str_replace(['-', '_'], ' ', $slug)) : '';
    }

    /** Clé d'icône d'un slug (repli : calendar). */
    public static function iconFor(?string $slug): string
    {
        return static::findBySlug($slug)?->icon ?: self::DEFAULT_ICON;
    }

    /** Classe Font Awesome d'une clé d'icône. */
    public static function faClass(?string $icon): string
    {
        return self::ICONS[$icon] ?? self::ICONS[self::DEFAULT_ICON];
    }

    /** Types actifs [slug => libellé] (formulaires, filtres) ; `$keep` : ajoute ce slug même inactif (modification). */
    public static function options(?string $keep = null): array
    {
        return static::cached()
            ->filter(fn (self $t) => $t->is_active || ($keep !== null && $t->slug === $keep))
            ->mapWithKeys(fn (self $t) => [$t->slug => $t->localizedName()])
            ->all();
    }

    // ─── Instance ────────────────────────────────────────────────────────────

    /** name_en en anglais s'il est renseigné, sinon name_fr. */
    public function localizedName(?string $locale = null): string
    {
        $locale ??= App::getLocale();

        return str_starts_with((string) $locale, 'en') && filled($this->name_en) ? $this->name_en : $this->name_fr;
    }

    public function faIcon(): string
    {
        return static::faClass($this->icon);
    }

    /** Nom court libre tiré d'un nom (« Camp de jeunes » → « camp-de-jeunes »), unique, 40 caractères au plus. */
    public static function uniqueSlugFrom(string $name): string
    {
        $base = Str::limit(Str::slug($name), 40, '') ?: 'type';
        $base = rtrim($base, '-') ?: 'type';
        $slug = $base;
        for ($i = 2; static::query()->where('slug', $slug)->exists(); $i++) {
            $suffix = '-' . $i;
            $slug   = rtrim(Str::limit($base, 40 - strlen($suffix), ''), '-') . $suffix;
        }

        return $slug;
    }

    /** Événements (tous statuts, hors supprimés) utilisant ce type. */
    public function eventsCount(): int
    {
        return Event::query()->where('type', $this->slug)->count();
    }

    /** Nombre d'événements par slug, en une requête. @return array<string, int> */
    public static function eventCounts(): array
    {
        return Event::query()->selectRaw('type, count(*) as aggregate')->groupBy('type')
            ->pluck('aggregate', 'type')->map(fn ($n) => (int) $n)->all();
    }
}

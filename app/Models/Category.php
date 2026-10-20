<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name', 'name_en', 'slug', 'icon', 'color', 'display_order', 'testimony_count', 'is_active',
    ];

    /** Noms anglais des types connus (slug => nom) : seeder et migration 2026_10_20_100001. */
    public const ENGLISH_NAMES = [
        'guerison'          => 'Healing',
        'delivrance'        => 'Deliverance',
        'conversion'        => 'Conversion',
        'salut'             => 'Salvation',
        'mariage'           => 'Marriage',
        'famille'           => 'Family',
        'finances'          => 'Finances',
        'provision'         => 'Provision',
        'miracles'          => 'Miracles',
        'protection'        => 'Divine protection',
        'protection_divine' => 'Divine protection',
        'ministere'         => 'Ministry',
        'emploi'            => 'Employment & Career',
        'etudes'            => 'Studies',
        'autre'             => 'Other',
    ];

    /**
     * Nom affiché selon la langue : name_en en anglais s'il est renseigné, sinon name (français).
     * Voir docs/fonctionnalites/categories-de-temoignages.md
     */
    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        if (str_starts_with((string) $locale, 'en') && filled($this->name_en)) {
            return (string) $this->name_en;
        }

        return (string) $this->name;
    }

    /** Accesseur $category->localized_name (même règle que displayName()). */
    protected function localizedName(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::get(fn () => $this->displayName());
    }

    protected function casts(): array
    {
        return [
            'is_active'       => 'boolean',
            'display_order'   => 'integer',
            'testimony_count' => 'integer',
        ];
    }

    public function testimonies(): HasMany
    {
        return $this->hasMany(Testimony::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('display_order');
    }

    /** Catégorie d'un nom court (« guerison »), lue une fois par requête : repli des témoignages sans category_id. */
    public static function forSlug(?string $slug): ?self
    {
        static $bySlug = null;
        if (!$slug) return null;
        $bySlug ??= static::query()->get()->keyBy('slug');

        return $bySlug->get($slug);
    }

    /**
     * Présentation dans l'interface (charte ARISE & SHINE Krea) : icône Font Awesome selon la catégorie,
     * teinte de la marque en alternance (bleu, orange, jaune). Classes écrites en entier pour Tailwind.
     * @return array{icon: string, soft: string, text: string, badge: string}
     */
    public function presentation(): array
    {
        $icon = [
            'guerison'           => 'fa-heart-pulse',
            'delivrance'         => 'fa-dove',
            'conversion'         => 'fa-cross',
            'salut'              => 'fa-hands-praying',
            'mariage'            => 'fa-ring',
            'famille'            => 'fa-people-roof',
            'finances'           => 'fa-hand-holding-heart',
            'miracles'           => 'fa-star',
            'protection_divine'  => 'fa-shield-heart',
            'ministere'          => 'fa-church',
            'emploi'             => 'fa-briefcase',
            'etudes'             => 'fa-graduation-cap',
        ][$this->slug] ?? 'fa-tag';

        $tones = [
            ['soft' => 'bg-accent-50',  'text' => 'text-accent-500',  'badge' => 'badge-orange'],
            ['soft' => 'bg-sun-50',     'text' => 'text-sun-600',     'badge' => 'badge-yellow'],
            ['soft' => 'bg-primary-50', 'text' => 'text-primary-600', 'badge' => 'badge-blue'],
        ];

        return ['icon' => $icon] + $tones[((int) $this->display_order) % 3];
    }
}

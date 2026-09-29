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
        'name', 'slug', 'icon', 'color', 'display_order', 'testimony_count', 'is_active',
    ];

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

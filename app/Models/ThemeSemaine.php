<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Thème d'une semaine du verset du jour (dimanche → samedi) : thématique, verset clé, période
 * (fête, temps liturgique). Importé par versets:import. Voir docs/fonctionnalites/verset-du-jour.md
 */
class ThemeSemaine extends Model
{
    protected $table = 'themes_semaine';

    protected $fillable = ['annee', 'numero_semaine', 'thematique', 'slug', 'verset_cle', 'periode', 'date_debut', 'date_fin'];

    /** Slug de la page /themes/{slug} (docs/fonctionnalites/referencement.md), recalculé avec la thématique. */
    protected static function booted(): void
    {
        // Sitemaps recalculés (docs/fonctionnalites/referencement.md).
        static::saved(fn () => \App\Services\Sitemaps::forget());
        static::deleted(fn () => \App\Services\Sitemaps::forget());
        static::saving(function (self $theme) {
            if (!$theme->slug || $theme->isDirty('thematique')) {
                $theme->slug = \Illuminate\Support\Str::slug((string) $theme->thematique);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'annee'          => 'integer',
            'numero_semaine' => 'integer',
            'date_debut'     => 'date',
            'date_fin'       => 'date',
        ];
    }

    /** Versets de la semaine, dans l'ordre des jours. */
    public function versets(): HasMany
    {
        return $this->hasMany(DailyVerse::class, 'theme_semaine_id')->orderBy('scheduled_date');
    }
}

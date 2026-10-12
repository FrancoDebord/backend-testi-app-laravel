<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lieu grossier d'une personne (≈ 1 km), privé : jamais exposé dans les ressources publiques.
 * Voir docs/fonctionnalites/localisation.md
 */
class UserLocation extends Model
{
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id', 'use_gps', 'latitude', 'longitude', 'locality', 'city', 'region',
        'country_code', 'continent', 'source', 'home_city', 'location_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'use_gps' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'location_updated_at' => 'datetime',
        ];
    }
}

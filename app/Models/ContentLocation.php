<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Copie grossière du lieu d'un contenu (témoignage, requête ou session de prière) au moment de sa
 * création, pour classer les fils par zone. Voir docs/fonctionnalites/localisation.md
 */
class ContentLocation extends Model
{
    public const TESTIMONY      = 'testimony';
    public const PRAYER_REQUEST = 'prayer_request';
    public const PRAYER_SESSION = 'prayer_session';

    protected $fillable = [
        'subject_type', 'subject_id', 'user_id', 'latitude', 'longitude', 'locality', 'city', 'region',
        'country_code', 'continent', 'source',
    ];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float'];
    }
}

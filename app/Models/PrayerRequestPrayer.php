<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** « Je prie » d'une personne pour une requête (une fois par personne). */
class PrayerRequestPrayer extends Model
{
    protected $fillable = ['prayer_request_id', 'user_id'];

    public function prayerRequest(): BelongsTo
    {
        return $this->belongsTo(PrayerRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Inscription « Je serai là » à une session de prière. */
class PrayerSessionParticipant extends Model
{
    protected $fillable = ['prayer_session_id', 'user_id'];

    public function prayerSession(): BelongsTo
    {
        return $this->belongsTo(PrayerSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

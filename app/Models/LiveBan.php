<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Personne privée de commentaires et réactions pendant un direct. */
class LiveBan extends Model
{
    protected $fillable = ['live_session_id', 'user_id', 'banned_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

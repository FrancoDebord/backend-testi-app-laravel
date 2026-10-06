<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Réponse d'une personne à « Participer » : going | not_going. */
class EventParticipation extends Model
{
    public const GOING     = 'going';
    public const NOT_GOING = 'not_going';

    protected $fillable = ['event_id', 'user_id', 'status'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

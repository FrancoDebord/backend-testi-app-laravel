<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Commentaire d'un événement : les participants y racontent ce qu'ils ont vécu.
 * Un gestionnaire peut l'enregistrer comme témoignage officiel (`testimony_id`).
 */
class EventComment extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['event_id', 'user_id', 'body', 'testimony_id'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function testimony(): BelongsTo
    {
        return $this->belongsTo(Testimony::class);
    }
}

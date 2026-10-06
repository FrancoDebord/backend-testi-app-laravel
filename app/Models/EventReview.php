<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réponse au sondage d'après l'événement : a-t-il eu lieu (oui / non / je n'y étais pas),
 * note de 1 à 5 (si « oui »), avis facultatif. Une par personne, modifiable 30 jours.
 * Voir docs/fonctionnalites/evenements.md
 */
class EventReview extends Model
{
    public const YES    = 'yes';
    public const NO     = 'no';
    public const ABSENT = 'absent';

    public const ATTENDED = [
        self::YES    => 'Oui, il a eu lieu',
        self::NO     => "Non, il n'a pas eu lieu",
        self::ABSENT => "Je n'y étais pas",
    ];

    /** Jours pendant lesquels une réponse reste modifiable. */
    public const EDITABLE_DAYS = 30;

    protected $fillable = ['event_id', 'user_id', 'attended', 'rating', 'body'];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function editableUntil(): \Illuminate\Support\Carbon
    {
        return $this->created_at->copy()->addDays(self::EDITABLE_DAYS);
    }

    public function isEditable(): bool
    {
        return $this->created_at === null || now()->lte($this->editableUntil());
    }
}

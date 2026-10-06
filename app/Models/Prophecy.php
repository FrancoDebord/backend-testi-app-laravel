<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Parole prophétique reçue, gardée dans le carnet privé (visible de son seul auteur).
 * Quand elle s'accomplit, l'auteur publie un témoignage ; la parole peut alors être
 * montrée avec lui (`is_public`). Voir docs/fonctionnalites/paroles-prophetiques.md
 */
class Prophecy extends Model
{
    use HasUuids, SoftDeletes;

    public const WAITING   = 'waiting';
    public const FULFILLED = 'fulfilled';

    protected $fillable = [
        'user_id', 'title', 'received_on', 'body_text', 'audio_url', 'audio_duration', 'given_by',
        'due_on', 'status', 'fulfilled_on', 'testimony_id', 'is_public',
        'reminder_frequency', 'reminder_time', 'reminder_weekday', 'prayer_count', 'last_prayed_at',
    ];

    protected function casts(): array
    {
        return [
            'received_on'      => 'date',
            'due_on'           => 'date',
            'fulfilled_on'     => 'date',
            'is_public'        => 'boolean',
            'audio_duration'   => 'integer',
            'reminder_weekday' => 'integer',
            'prayer_count'     => 'integer',
            'last_prayed_at'   => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function testimony(): BelongsTo
    {
        return $this->belongsTo(Testimony::class);
    }

    public function prayers(): HasMany
    {
        return $this->hasMany(ProphecyPrayer::class)->orderByDesc('prayed_at');
    }

    public function isFulfilled(): bool
    {
        return $this->status === self::FULFILLED;
    }

    /** Version publique, montrée avec le témoignage d'accomplissement. */
    public function publicPayload(): array
    {
        return [
            'title'        => $this->title,
            'receivedOn'   => $this->received_on?->toDateString(),
            'givenBy'      => $this->given_by,
            'bodyText'     => $this->body_text,
            'audioUrl'     => $this->audio_url,
            'audioDuration' => $this->audio_duration,
            'dueOn'        => $this->due_on?->toDateString(),
            'fulfilledOn'  => $this->fulfilled_on?->toDateString(),
        ];
    }
}

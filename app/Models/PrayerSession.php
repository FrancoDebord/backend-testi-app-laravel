<?php

namespace App\Models;

use App\Enums\LiveStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Session de prière programmée. À l'heure, son hôte ouvre la salle : un direct (LiveSession)
 * rattaché par live_sessions.prayer_session_id, avec spectateurs, commentaires et intervenants.
 * Voir docs/fonctionnalites/sessions-de-priere.md
 */
class PrayerSession extends Model
{
    use HasUuids, SoftDeletes;

    public const PUBLIC    = 'public';
    public const FOLLOWERS = 'followers';
    public const VISIBILITIES = [self::PUBLIC, self::FOLLOWERS];

    public const SCHEDULED = 'scheduled';
    public const CANCELLED = 'cancelled';

    /** La salle peut être ouverte jusqu'à 30 minutes avant l'heure annoncée. */
    public const OPEN_BEFORE_MINUTES = 30;

    /** Rappel aux inscrits, avant l'heure annoncée. */
    public const REMINDER_MINUTES = 15;

    public const MAX_TOPICS = 10;

    public const VISIBILITY_LABELS = [
        self::PUBLIC    => 'Tout le monde',
        self::FOLLOWERS => 'Mes abonnés',
    ];

    public const PHASE_LABELS = [
        'upcoming'  => 'À venir',
        'live'      => 'En cours',
        'ended'     => 'Terminée',
        'cancelled' => 'Annulée',
    ];

    protected $fillable = [
        'host_id', 'event_id', 'title', 'description', 'topics', 'starts_at', 'duration_minutes', 'ends_at',
        'visibility', 'status', 'participant_count', 'reminder_sent_at', 'opened_at',
    ];

    protected function casts(): array
    {
        return [
            'topics'            => 'array',
            'starts_at'         => 'datetime',
            'ends_at'           => 'datetime',
            'duration_minutes'  => 'integer',
            'participant_count' => 'integer',
            'reminder_sent_at'  => 'datetime',
            'opened_at'         => 'datetime',
        ];
    }

    // ---------- Relations ----------

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(PrayerSessionParticipant::class);
    }

    /** Salles ouvertes (directs) ; une seule active à la fois. */
    public function lives(): HasMany
    {
        return $this->hasMany(LiveSession::class);
    }

    // ---------- État ----------

    public function endsAt(): Carbon
    {
        return $this->ends_at ?? $this->starts_at->copy()->addMinutes($this->duration_minutes);
    }

    /** Salle active (en préparation ou à l'antenne), si elle existe. */
    public function activeLive(): ?LiveSession
    {
        if ($this->relationLoaded('lives')) {
            return $this->lives->first(fn (LiveSession $l) => $l->isActive());
        }

        return $this->lives()->active()->latest()->first();
    }

    /** upcoming | live | ended | cancelled */
    public function phase(): string
    {
        if ($this->status === self::CANCELLED) return 'cancelled';
        if ($this->activeLive()?->status === LiveStatus::Live) return 'live';
        if (now()->greaterThan($this->endsAt())) return 'ended';

        return 'upcoming';
    }

    public function phaseLabel(): string
    {
        return self::PHASE_LABELS[$this->phase()];
    }

    public function isHost(?User $user): bool
    {
        return $user !== null && $user->id === $this->host_id;
    }

    /** Public : tout le monde · abonnés : l'hôte, ses abonnés et la modération. */
    public function isVisibleTo(?User $user): bool
    {
        if ($this->visibility === self::PUBLIC || $this->isHost($user) || $user?->canModerate()) return true;

        return $user !== null && $user->isFollowing($this->host_id);
    }

    public function canBeEditedBy(?User $user): bool
    {
        return $this->isHost($user) && $this->status !== self::CANCELLED && $this->phase() !== 'ended';
    }

    /** Hôte et modération (la modération peut aussi couper la salle : POST /lives/{id}/end). */
    public function canBeDeletedBy(?User $user): bool
    {
        return $this->isHost($user) || (bool) $user?->canModerate();
    }

    /** Fenêtre d'ouverture de la salle : 30 min avant l'heure annoncée jusqu'à la fin prévue. */
    public function isInOpeningWindow(): bool
    {
        return now()->greaterThanOrEqualTo($this->starts_at->copy()->subMinutes(self::OPEN_BEFORE_MINUTES))
            && now()->lessThanOrEqualTo($this->endsAt());
    }

    public function canBeStartedBy(?User $user): bool
    {
        return $this->isHost($user) && $this->status !== self::CANCELLED
            && ($this->activeLive() !== null || $this->isInOpeningWindow());
    }

    public function isRegistered(?User $user): bool
    {
        if ($user === null) return false;
        if (array_key_exists('is_registered', $this->attributes)) return (bool) $this->attributes['is_registered'];

        return $this->participants()->where('user_id', $user->id)->exists();
    }

    // ---------- Portées ----------

    public function scopeVisibleTo(Builder $q, ?User $user): Builder
    {
        if ($user?->canModerate()) return $q;

        return $q->where(function (Builder $w) use ($user) {
            $w->where('visibility', self::PUBLIC);
            if ($user) {
                $w->orWhere('host_id', $user->id)
                  ->orWhereIn('host_id', Follow::where('follower_id', $user->id)->select('following_id'));
            }
        });
    }

    public function scopeWithRegistrationOf(Builder $q, ?User $user): Builder
    {
        if (!$user) return $q;

        return $q->withExists(['participants as is_registered' => fn ($p) => $p->where('user_id', $user->id)]);
    }
}

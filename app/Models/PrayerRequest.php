<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Requête de prière : publiée directement (sans relecture), visible de tous, des abonnés de
 * l'auteur ou de lui seul ; peut être anonyme. Voir docs/fonctionnalites/requetes-de-priere.md
 */
class PrayerRequest extends Model
{
    use HasUuids, SoftDeletes;

    public const PUBLIC    = 'public';
    public const FOLLOWERS = 'followers';
    public const PRIVATE   = 'private';
    public const VISIBILITIES = [self::PUBLIC, self::FOLLOWERS, self::PRIVATE];

    public const OPEN     = 'open';
    public const ANSWERED = 'answered';

    /** Signalements distincts qui retirent la requête en attendant la modération. */
    public const AUTO_HIDE_REPORTS = 3;

    public const REPORT_REASONS = [
        'inappropriate_content' => 'Contenu inapproprié',
        'hate_speech'           => 'Propos haineux',
        'spam'                  => 'Publicité ou spam',
        'other'                 => 'Autre raison',
    ];

    public const VISIBILITY_LABELS = [
        self::PUBLIC    => 'Tout le monde',
        self::FOLLOWERS => 'Mes abonnés',
        self::PRIVATE   => 'Moi seul',
    ];

    protected $fillable = [
        'user_id', 'event_id', 'body', 'visibility', 'is_anonymous', 'status', 'answered_at', 'answer_note',
        'testimony_id', 'prayer_count', 'message_count', 'report_count', 'hidden_at', 'hidden_by', 'hidden_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous'  => 'boolean',
            'answered_at'   => 'datetime',
            'hidden_at'     => 'datetime',
            'prayer_count'  => 'integer',
            'message_count' => 'integer',
            'report_count'  => 'integer',
        ];
    }

    // ---------- Relations ----------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function testimony(): BelongsTo
    {
        return $this->belongsTo(Testimony::class);
    }

    public function prayers(): HasMany
    {
        return $this->hasMany(PrayerRequestPrayer::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PrayerRequestMessage::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PrayerRequestReport::class);
    }

    // ---------- Droits ----------

    public function isAuthor(?User $user): bool
    {
        return $user !== null && $user->id === $this->user_id;
    }

    /**
     * Public : tout le monde · abonnés : l'auteur, ses abonnés et la modération · privé : l'auteur seul.
     * Retirée par la modération : l'auteur et la modération seulement.
     */
    public function isVisibleTo(?User $user): bool
    {
        if ($this->isAuthor($user)) return true;
        if ($this->visibility === self::PRIVATE) return false;
        if ($user?->canModerate()) return true;
        if ($this->isHidden()) return false;
        if ($this->visibility === self::PUBLIC) return true;

        return $user !== null && $user->isFollowing($this->user_id);
    }

    /** Auteur affiché : masqué pour les autres si la requête est anonyme (jamais pour l'auteur ni la modération). */
    public function revealsAuthorTo(?User $user): bool
    {
        return !$this->is_anonymous || $this->isAuthor($user) || (bool) $user?->canModerate();
    }

    public function canBeDeletedBy(?User $user): bool
    {
        return $this->isAuthor($user) || (bool) $user?->canModerate();
    }

    /** Encouragements possibles : requête visible, non retirée, et pas privée (sauf pour l'auteur). */
    public function acceptsMessagesFrom(?User $user): bool
    {
        return $user !== null && !$this->isHidden() && $this->isVisibleTo($user)
            && ($this->visibility !== self::PRIVATE || $this->isAuthor($user));
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    public function isAnswered(): bool
    {
        return $this->status === self::ANSWERED;
    }

    public function hasPrayed(?User $user): bool
    {
        if ($user === null) return false;
        if (array_key_exists('has_prayed', $this->attributes)) return (bool) $this->attributes['has_prayed'];

        return $this->prayers()->where('user_id', $user->id)->exists();
    }

    // ---------- Portées ----------

    /** Requêtes visibles de $user dans les listes (hors modération) : même règle que isVisibleTo(). */
    public function scopeVisibleTo(Builder $q, ?User $user): Builder
    {
        return $q->where(function (Builder $w) use ($user) {
            if ($user) {
                $w->where('user_id', $user->id);
            }
            $w->orWhere(function (Builder $o) use ($user) {
                $o->whereNull('hidden_at')->where(function (Builder $v) use ($user) {
                    $v->where('visibility', self::PUBLIC);
                    if ($user) {
                        $v->orWhere(fn (Builder $f) => $f->where('visibility', self::FOLLOWERS)
                            ->whereIn('user_id', Follow::where('follower_id', $user->id)->select('following_id')));
                    }
                });
            });
        });
    }

    /** Ajoute `has_prayed` pour éviter une requête par carte. */
    public function scopeWithPrayedBy(Builder $q, ?User $user): Builder
    {
        if (!$user) return $q;

        return $q->withExists(['prayers as has_prayed' => fn ($p) => $p->where('user_id', $user->id)]);
    }
}

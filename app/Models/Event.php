<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\LiveStatus;
use App\Enums\TestimonyStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Événement chrétien (croisade, conférence, séminaire, camp, tournée…).
 * Créé par une organisation vérifiée ou un administrateur. Voir docs/fonctionnalites/evenements.md
 */
class Event extends Model
{
    use HasUuids, SoftDeletes;

    /** Images de couverture (carrousel) au plus. */
    public const MAX_IMAGES = 6;

    /** Invités principaux au plus. */
    public const MAX_GUESTS = 10;

    /** Co-gestionnaires d'un événement au plus (en plus de l'organisateur). */
    public const MAX_MANAGERS = 2;

    /** Gestionnaires d'une organisation au plus. */
    public const MAX_ORGANIZATION_MANAGERS = 2;

    protected $fillable = [
        'organizer_id', 'title', 'description', 'type', 'status', 'starts_at', 'ends_at',
        'location', 'city', 'country', 'guests', 'comments_enabled',
        'address', 'latitude', 'longitude',
        'going_count', 'not_going_count', 'comment_count',
    ];

    protected function casts(): array
    {
        return [
            'type'             => EventType::class,
            'status'           => EventStatus::class,
            'starts_at'        => 'datetime',
            'ends_at'          => 'datetime',
            'guests'           => 'array',
            'comments_enabled' => 'boolean',
            'going_count'      => 'integer',
            'not_going_count'  => 'integer',
            'comment_count'    => 'integer',
            'latitude'         => 'float',
            'longitude'        => 'float',
        ];
    }

    // ---------- Relations ----------

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /** Co-gestionnaires (2 au plus) désignés par l'organisateur. */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_managers')->withTimestamps();
    }

    public function images(): HasMany
    {
        return $this->hasMany(EventImage::class)->orderBy('position')->orderBy('created_at');
    }

    public function participations(): HasMany
    {
        return $this->hasMany(EventParticipation::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(EventComment::class);
    }

    public function testimonies(): HasMany
    {
        return $this->hasMany(Testimony::class);
    }

    public function lives(): HasMany
    {
        return $this->hasMany(LiveSession::class);
    }

    // ---------- Droits ----------

    /**
     * Peut créer des événements : administrateur, organisation vérifiée, ou gestionnaire
     * d'une organisation vérifiée (l'événement est alors créé au nom de l'organisation).
     */
    public static function canBeCreatedBy(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $user->isVerified()
            || $user->verifiedOrganizationsManaged()->exists());
    }

    /**
     * Responsable de l'événement : administrateur, organisateur, ou gestionnaire de
     * l'organisation organisatrice. Seul à pouvoir supprimer l'événement et désigner
     * les co-gestionnaires.
     */
    public function canBeAdministeredBy(?User $user): bool
    {
        if ($user === null) return false;
        if ($user->isAdmin() || $user->id === $this->organizer_id) return true;

        return $user->managedOrganizations()->whereKey($this->organizer_id)->exists();
    }

    /** Gestionnaire de la page : responsable (ci-dessus) ou co-gestionnaire de l'événement. */
    public function canBeManagedBy(?User $user): bool
    {
        if ($user === null) return false;
        if ($this->canBeAdministeredBy($user)) return true;

        return $this->relationLoaded('managers')
            ? $this->managers->contains('id', $user->id)
            : $this->managers()->whereKey($user->id)->exists();
    }

    /** Brouillon : visible des seuls gestionnaires. */
    public function isVisibleTo(?User $user): bool
    {
        return $this->status !== EventStatus::Draft || $this->canBeManagedBy($user);
    }

    // ---------- État ----------

    public function isCancelled(): bool
    {
        return $this->status === EventStatus::Cancelled;
    }

    /** upcoming | ongoing | past */
    public function phase(): string
    {
        $now = now();
        if ($this->ends_at && $this->ends_at->lt($now)) return 'past';
        if ($this->starts_at && $this->starts_at->lte($now)) return 'ongoing';

        return 'upcoming';
    }

    public function phaseLabel(): string
    {
        if ($this->isCancelled()) return 'Annulé';

        return match ($this->phase()) {
            'past'    => 'Terminé',
            'ongoing' => 'En cours',
            default   => 'À venir',
        };
    }

    /** Participer reste possible jusqu'à la fin de l'événement (sauf annulation). */
    public function acceptsParticipation(): bool
    {
        return $this->status === EventStatus::Published && $this->phase() !== 'past';
    }

    /** Un repère GPS a-t-il été placé sur la carte ? */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** Adresse lisible pour l'itinéraire : lieu, adresse, ville, pays. */
    public function fullAddress(): string
    {
        return collect([$this->location, $this->address, $this->city, $this->country])
            ->filter(fn ($v) => filled($v))->implode(', ');
    }

    /**
     * Lien d'itinéraire Google Maps (ouvre l'application sur mobile, le site sinon) : vers le repère
     * GPS s'il existe, sinon vers l'adresse ; null si l'événement n'a aucun lieu.
     */
    public function directionsUrl(): ?string
    {
        if ($this->hasCoordinates()) {
            $destination = self::formatCoordinate($this->latitude) . ',' . self::formatCoordinate($this->longitude);
        } elseif (filled($address = $this->fullAddress())) {
            $destination = rawurlencode($address);
        } else {
            return null;
        }

        return 'https://www.google.com/maps/dir/?api=1&destination=' . $destination;
    }

    /** Coordonnée sans notation scientifique ni zéros inutiles (6.3702928). */
    public static function formatCoordinate(float $value): string
    {
        return rtrim(rtrim(number_format($value, 7, '.', ''), '0'), '.');
    }

    public function coverUrl(): ?string
    {
        return $this->relationLoaded('images') ? $this->images->first()?->url : $this->images()->value('url');
    }

    /** Direct de l'événement en cours (ou en préparation). */
    public function activeLive(): ?LiveSession
    {
        return $this->lives()->whereIn('status', [LiveStatus::Live, LiveStatus::Preparing])->latest()->first();
    }

    public function participationOf(?User $user): ?string
    {
        if (!$user) return null;

        return $this->participations()->where('user_id', $user->id)->value('status');
    }

    /** Témoignages officiels publiés (et en attente pour les gestionnaires). */
    public function testimoniesVisibleTo(?User $user)
    {
        return $this->testimonies()
            ->where('visibility', 'public')
            ->when(!$this->canBeManagedBy($user),
                fn ($q) => $q->where('status', TestimonyStatus::Approved->value),
                fn ($q) => $q->whereIn('status', [TestimonyStatus::Approved->value, TestimonyStatus::Pending->value]));
    }

    // ---------- Requêtes ----------

    public function scopePublished(Builder $q): Builder
    {
        return $q->whereIn('status', [EventStatus::Published->value, EventStatus::Cancelled->value]);
    }

    /** Événements que la personne gère (organisateur, organisation gérée, co-gestionnaire). */
    public function scopeManagedBy(Builder $q, User $user): Builder
    {
        $orgIds = $user->managedOrganizations()->pluck('users.id')->all();

        return $q->where(fn ($w) => $w->where('organizer_id', $user->id)
            ->orWhereIn('organizer_id', $orgIds)
            ->orWhereHas('managers', fn ($m) => $m->whereKey($user->id)));
    }

    /** Événements organisés par les comptes que la personne suit (« Mon fil »). */
    public function scopeFollowedBy(Builder $q, User $user): Builder
    {
        return $q->whereIn('organizer_id', $user->following()->select('users.id'));
    }

    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->where('ends_at', '>=', now())->orderBy('starts_at');
    }

    public function scopePast(Builder $q): Builder
    {
        return $q->where('ends_at', '<', now())->orderByDesc('starts_at');
    }
}

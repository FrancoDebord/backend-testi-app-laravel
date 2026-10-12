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
        // Report / annulation, modération, sondage (docs/fonctionnalites/evenements.md)
        'original_starts_at', 'original_ends_at', 'date_tbd', 'postponed_at', 'status_reason', 'status_changed_at',
        'report_count', 'hidden_at', 'hidden_by', 'hidden_reason',
        'survey_sent_at', 'survey_yes_count', 'survey_no_count', 'survey_absent_count',
        'rating_count', 'rating_sum', 'unconfirmed_at', 'unconfirmed_reviewed_at',
    ];

    /** Signalements non traités à partir desquels l'événement est masqué en attendant la modération. */
    public const AUTO_HIDE_REPORTS = 3;

    /** Réponses « Oui » / « Non » au sondage à partir desquelles un verdict est rendu. */
    public const SURVEY_MIN_ANSWERS = 3;

    /** Événements confirmés (sans aucun non confirmé) pour le badge « Organisateur fiable ». */
    public const RELIABLE_MIN_CONFIRMED = 3;

    /** Invitations au sondage : événements terminés depuis moins de N jours. */
    public const SURVEY_INVITE_DAYS = 7;

    public const REPORT_REASONS = [
        'not_christian'         => 'Pas un événement chrétien',
        'fake'                  => "Événement faux / n'existe pas",
        'inappropriate_content' => 'Contenu inapproprié',
        'scam'                  => "Arnaque / demande d'argent",
        'other'                 => 'Autre',
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
            'original_starts_at' => 'datetime',
            'original_ends_at'   => 'datetime',
            'date_tbd'           => 'boolean',
            'postponed_at'       => 'datetime',
            'status_changed_at'  => 'datetime',
            'report_count'       => 'integer',
            'hidden_at'          => 'datetime',
            'survey_sent_at'     => 'datetime',
            'survey_yes_count'   => 'integer',
            'survey_no_count'    => 'integer',
            'survey_absent_count' => 'integer',
            'rating_count'       => 'integer',
            'rating_sum'         => 'integer',
            'unconfirmed_at'     => 'datetime',
            'unconfirmed_reviewed_at' => 'datetime',
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

    public function reports(): HasMany
    {
        return $this->hasMany(EventReport::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(EventReview::class);
    }

    // ---------- Droits ----------

    /**
     * Peut créer des événements : administrateur, organisation vérifiée, ou gestionnaire
     * d'une organisation vérifiée (l'événement est alors créé au nom de l'organisation).
     */
    public static function canBeCreatedBy(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $user->isVerifiedOrganization()
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

    /**
     * Peut supprimer l'événement : son responsable (organisateur, ses gestionnaires) ou un
     * administrateur. Jamais un modérateur : il masque l'événement (hide) sans le supprimer.
     */
    public function canBeDeletedBy(?User $user): bool
    {
        return $this->canBeAdministeredBy($user);
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

    /** Brouillon : visible des seuls gestionnaires. Masqué : gestionnaires et modération. */
    public function isVisibleTo(?User $user): bool
    {
        if ($this->status === EventStatus::Draft) return $this->canBeManagedBy($user);
        if ($this->isHidden()) return ($user?->canModerate() ?? false) || $this->canBeManagedBy($user);

        return true;
    }

    // ---------- État ----------

    public function isCancelled(): bool
    {
        return $this->status === EventStatus::Cancelled;
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    /** Reporté (date à confirmer, ou nouvelle date fixée : ancienne date conservée). */
    public function isPostponed(): bool
    {
        return $this->status === EventStatus::Postponed || ($this->postponed_at !== null && !$this->isCancelled());
    }

    /** Reporté sans nouvelle date. */
    public function isDateTbd(): bool
    {
        return $this->status === EventStatus::Postponed && (bool) $this->date_tbd;
    }

    /** Majorité de « Non » au sondage d'après l'événement. */
    public function isUnconfirmed(): bool
    {
        return $this->unconfirmed_at !== null;
    }

    /** Publié ou reporté : visible du public et ouvert aux réponses. */
    public function isOpen(): bool
    {
        return in_array($this->status, [EventStatus::Published, EventStatus::Postponed], true);
    }

    /** upcoming | ongoing | past (un événement reporté sans date reste « à venir »). */
    public function phase(): string
    {
        if ($this->isDateTbd()) return 'upcoming';
        $now = now();
        if ($this->ends_at && $this->ends_at->lt($now)) return 'past';
        if ($this->starts_at && $this->starts_at->lte($now)) return 'ongoing';

        return 'upcoming';
    }

    public function phaseLabel(): string
    {
        if ($this->isCancelled()) return 'Annulé';
        if ($this->isDateTbd()) return 'Reporté — date à confirmer';

        return match ($this->phase()) {
            'past'    => 'Terminé',
            'ongoing' => 'En cours',
            default   => 'À venir',
        };
    }

    /** Participer reste possible jusqu'à la fin de l'événement (sauf annulation), même reporté. */
    public function acceptsParticipation(): bool
    {
        return $this->isOpen() && !$this->isHidden() && $this->phase() !== 'past';
    }

    // ---------- Sondage d'après l'événement ----------

    /** Le sondage est ouvert : événement publié, terminé, non masqué. */
    public function acceptsReviews(): bool
    {
        return $this->status === EventStatus::Published && !$this->isHidden() && $this->phase() === 'past';
    }

    /** % des réponses « Oui » parmi « Oui » + « Non » (null sans réponse). */
    public function confirmedPercent(): ?int
    {
        $answered = $this->survey_yes_count + $this->survey_no_count;

        return $answered > 0 ? (int) round($this->survey_yes_count * 100 / $answered) : null;
    }

    public function ratingAverage(): ?float
    {
        return $this->rating_count > 0 ? round($this->rating_sum / $this->rating_count, 1) : null;
    }

    public function surveyTotal(): int
    {
        return $this->survey_yes_count + $this->survey_no_count + $this->survey_absent_count;
    }

    /** Confirmé : au moins 3 « Oui » / « Non » et une majorité de « Oui ». */
    public function isConfirmed(): bool
    {
        return $this->survey_yes_count + $this->survey_no_count >= self::SURVEY_MIN_ANSWERS
            && $this->survey_yes_count > $this->survey_no_count;
    }

    /**
     * Fiabilité d'un organisateur (modération) : événements confirmés, non confirmés, signalés.
     * `reliable` : au moins 3 confirmés et aucun non confirmé (badge public « Organisateur fiable »).
     *
     * @return array{confirmed: int, unconfirmed: int, reported: int, total: int, reliable: bool}
     */
    public static function reliabilityOf(?string $organizerId): array
    {
        $min = self::SURVEY_MIN_ANSWERS;
        $row = $organizerId ? self::query()->where('organizer_id', $organizerId)->toBase()->selectRaw(
            "COUNT(*) as total,
             SUM(CASE WHEN survey_yes_count + survey_no_count >= {$min} AND survey_yes_count > survey_no_count THEN 1 ELSE 0 END) as confirmed,
             SUM(CASE WHEN unconfirmed_at IS NOT NULL THEN 1 ELSE 0 END) as unconfirmed,
             SUM(CASE WHEN report_count > 0 THEN 1 ELSE 0 END) as reported"
        )->first() : null;

        $out = [
            'confirmed'   => (int) ($row->confirmed ?? 0),
            'unconfirmed' => (int) ($row->unconfirmed ?? 0),
            'reported'    => (int) ($row->reported ?? 0),
            'total'       => (int) ($row->total ?? 0),
        ];
        $out['reliable'] = $out['confirmed'] >= self::RELIABLE_MIN_CONFIRMED && $out['unconfirmed'] === 0;

        return $out;
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

    /** Visibles du public : publiés, reportés, annulés ; pas les événements masqués par la modération. */
    public function scopePublished(Builder $q): Builder
    {
        return $q->whereIn('status', [EventStatus::Published->value, EventStatus::Postponed->value, EventStatus::Cancelled->value])
            ->whereNull('hidden_at');
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

    /** À venir et en cours ; les reportés sans date viennent en dernier. */
    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->where('ends_at', '>=', now())
                ->orWhere(fn ($t) => $t->where('status', EventStatus::Postponed->value)->where('date_tbd', true)))
            ->orderBy('date_tbd')->orderBy('starts_at');
    }

    /** En cours : commencé et pas encore terminé. */
    public function scopeOngoing(Builder $q): Builder
    {
        return $q->where('starts_at', '<=', now())->where('ends_at', '>=', now())
            ->whereNot(fn ($t) => $t->where('status', EventStatus::Postponed->value)->where('date_tbd', true))
            ->orderBy('ends_at');
    }

    public function scopePast(Builder $q): Builder
    {
        return $q->where('ends_at', '<', now())
            ->whereNot(fn ($t) => $t->where('status', EventStatus::Postponed->value)->where('date_tbd', true))
            ->orderByDesc('starts_at');
    }
}

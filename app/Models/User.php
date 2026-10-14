<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Enums\UserAccountStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids, SoftDeletes;

    protected static function booted(): void
    {
        // Pièces justificatives de vérification : supprimées avec le compte (données sensibles).
        static::deleted(fn (User $user) => \App\Services\PersonalVerification::purgeDocuments($user));
    }

    protected $fillable = [
        'display_name', 'email', 'phone', 'phone_country', 'phone_verified_at', 'firebase_uid', 'google_id', 'password',
        'avatar_url', 'cover_url', 'country', 'bio', 'role', 'status', 'is_active',
        'email_verified_at', 'testimony_count', 'like_count', 'prayer_count',
        'follower_count', 'following_count',
        // Comptes organisation (docs/fonctionnalites/comptes-organisation.md).
        // verification_status / verified_* ne sont modifiés que par le serveur.
        'account_type', 'organization_name', 'organization_type', 'organization_city',
        'organization_website', 'verification_status', 'verified_at', 'verified_by',
        'verification_note',
    ];

    protected $hidden = ['password', 'remember_token', 'google_id'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'role'              => UserRole::class,
            'status'            => UserAccountStatus::class,
            'testimony_count'   => 'integer',
            'like_count'        => 'integer',
            'prayer_count'      => 'integer',
            'follower_count'    => 'integer',
            'following_count'   => 'integer',
            'account_type'        => AccountType::class,
            'organization_type'   => OrganizationType::class,
            'verification_status' => VerificationStatus::class,
            'verified_at'         => 'datetime',
            // Jamais dans $fillable : attribué seulement par php artisan users:super-admin.
            'is_super_admin'      => 'boolean',
        ];
    }

    // ---------- Relations ----------

    public function testimonies(): HasMany
    {
        return $this->hasMany(Testimony::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class, 'recipient_id');
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(Draft::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    /** Design et réglages du texte du verset du jour (docs/fonctionnalites/designs-du-verset.md). */
    public function verseStylePreference(): HasOne
    {
        return $this->hasOne(VerseStylePreference::class);
    }

    /** Appareils qui reçoivent les notifications push (docs/fonctionnalites/notifications-push.md). */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function socialProviders(): HasMany
    {
        return $this->hasMany(SocialAuthProvider::class);
    }

    public function savedTestimonies(): BelongsToMany
    {
        return $this->belongsToMany(Testimony::class, 'saved_testimonies', 'user_id', 'testimony_id')
                    ->withPivot('saved_at');
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')
                    ->withPivot('created_at');
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')
                    ->withPivot('created_at');
    }

    // ---------- Gestionnaires d'une organisation (docs/fonctionnalites/evenements.md) ----------

    /** Organisation : personnes qui gèrent ses événements en son nom (2 au plus). */
    public function organizationManagers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_managers', 'organization_id', 'user_id')
                    ->withTimestamps();
    }

    /** Organisations dont cette personne est gestionnaire. */
    public function managedOrganizations(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_managers', 'user_id', 'organization_id')
                    ->withTimestamps();
    }

    /** Gère cette organisation : c'est elle, ou l'une de ses gestionnaires. */
    public function canActForOrganization(?User $organization): bool
    {
        if (!$organization) return false;
        if ($organization->id === $this->id) return true;

        return $this->managedOrganizations()->whereKey($organization->id)->exists();
    }

    /** Organisations vérifiées au nom desquelles cette personne peut créer des événements. */
    public function verifiedOrganizationsManaged()
    {
        return $this->managedOrganizations()
            ->where('account_type', AccountType::Organization->value)
            ->where('verification_status', VerificationStatus::Verified->value);
    }

    // ---------- Paroles prophétiques (docs/fonctionnalites/paroles-prophetiques.md) ----------

    public function prophecies(): HasMany
    {
        return $this->hasMany(Prophecy::class);
    }

    // ---------- Helpers ----------

    public function canPublish(): bool
    {
        return $this->role->canPublish();
    }

    public function canModerate(): bool
    {
        return $this->role->canModerate();
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    /**
     * Super-administrateur : administrateur avec is_super_admin (suppression définitive des témoignages).
     * Attribué uniquement par php artisan users:super-admin {email} (docs/fonctionnalites/gestion-des-temoignages.md).
     */
    public function isSuperAdmin(): bool
    {
        return $this->isAdmin() && (bool) $this->is_super_admin;
    }

    public function isOrganization(): bool
    {
        return $this->account_type === AccountType::Organization;
    }

    /**
     * Compte vérifié par un administrateur (badge) : organisation ou personne.
     * Voir docs/fonctionnalites/verification-des-comptes.md
     */
    public function isVerified(): bool
    {
        return $this->verification_status === VerificationStatus::Verified;
    }

    // ---------- Badge « Approuvé » (docs/fonctionnalites/badge-approuve.md) ----------

    public const APPROVAL_SOURCES = ['verification_form', 'organization_review', 'admin_manual'];

    /** Badge « Approuvé » : même état que le badge de vérification (verification_status = verified). */
    public function isApproved(): bool
    {
        return $this->isVerified();
    }

    /** approved_personal | approved_organization | null. */
    public function approvalBadge(): ?string
    {
        if (!$this->isApproved()) {
            return null;
        }

        return $this->isOrganization() ? 'approved_organization' : 'approved_personal';
    }

    /** Date d'attribution du badge « Approuvé » (verified_at, ISO 8601) ; null sans badge. */
    public function approvedAt(): ?string
    {
        return $this->isApproved() ? $this->verified_at?->toIso8601String() : null;
    }

    /**
     * Champs exposés par chaque ressource utilisateur ou auteur de l'API (contrat de l'app) :
     * is_approved, account_type (individual | organization), approval_badge, approved_at.
     */
    public static function approvalFieldsFor(?self $user): array
    {
        return [
            'is_approved'    => (bool) $user?->isApproved(),
            'account_type'   => $user ? ($user->account_type?->value ?? AccountType::Individual->value) : null,
            'approval_badge' => $user?->approvalBadge(),
            'approved_at'    => $user?->approvedAt(),
        ];
    }

    /**
     * Organisation vérifiée : seule à donner des droits (créer des événements, diffuser).
     * Une personne vérifiée n'obtient que le badge.
     */
    public function isVerifiedOrganization(): bool
    {
        return $this->isOrganization() && $this->isVerified();
    }

    /** Demandes de vérification d'un compte personnel (la plus récente d'abord). */
    public function verificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class)->latest();
    }

    // ---------- Téléphone (docs/fonctionnalites/telephone.md) ----------

    /** Numéro confirmé par SMS (connexion par téléphone). Seul un numéro vérifié permet de se connecter. */
    public function hasVerifiedPhone(): bool
    {
        return $this->phone !== null && $this->phone_verified_at !== null;
    }

    /**
     * Enregistre un numéro de contact. Un numéro différent n'est plus vérifié (il n'a pas été confirmé par SMS).
     * @param string|null $e164 numéro international (+229…) ou null pour le retirer
     */
    public function setContactPhone(?string $e164, ?string $country): void
    {
        if ($e164 === $this->phone) {
            if ($country) {
                $this->phone_country = $country;
            }
            return;
        }
        $this->phone             = $e164;
        $this->phone_country     = $e164 ? $country : null;
        $this->phone_verified_at = null;
    }

    /**
     * Ajoute « is_followed » (la personne connectée suit-elle ce compte ?) en une seule requête.
     * Lu par UserResource (« is_following »). Voir docs/fonctionnalites/abonnements.md
     */
    /**
     * Nombre réel de témoignages publiés (`published_testimony_count`), lu en priorité par
     * UserResource et les cartes de la Communauté à la place du compteur `testimony_count`.
     */
    public function scopeWithPublishedTestimonyCount($query)
    {
        return $query->withCount(['testimonies as published_testimony_count' => fn ($t) => $t->published()]);
    }

    /** Témoignages à afficher : nombre réel si chargé, sinon le compteur enregistré. */
    public function publishedTestimonyCount(): int
    {
        return (int) ($this->published_testimony_count ?? $this->testimony_count ?? 0);
    }

    public function scopeWithFollowState($query, ?User $viewer)
    {
        if (!$viewer) {
            return $query;
        }

        return $query->withExists(['followers as is_followed' => fn ($q) => $q->where('follows.follower_id', $viewer->id)]);
    }

    public function scopeOrganizations($query)
    {
        return $query->where('account_type', AccountType::Organization->value);
    }

    public function scopePendingVerification($query)
    {
        return $query->where('account_type', AccountType::Organization->value)
                     ->where('verification_status', VerificationStatus::Pending->value);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getInitialsAttribute(): string
    {
        // mb_* : « Église Évangélique » → « ÉÉ » (substr couperait l'octet UTF-8).
        $parts = preg_split('/\s+/u', trim((string) $this->display_name), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === []) {
            return '?';
        }
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));
    }

    public function isFollowing(string $userId): bool
    {
        return $this->following()->where('following_id', $userId)->exists();
    }
}

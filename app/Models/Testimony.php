<?php

namespace App\Models;

use App\Enums\TestimonyType;
use App\Enums\TestimonyStatus;
use App\Enums\TestimonyVisibility;
use App\Enums\TestimonyCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Support\RichText;
use Illuminate\Support\HtmlString;

class Testimony extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id', 'category_id', 'event_id', 'title', 'type', 'category_slug',
        'body_text', 'media_url', 'youtube_id', 'proofs_public', 'renditions', 'cover_url', 'duration_sec',
        'bible_verse', 'bible_ref', 'tags', 'visibility', 'status',
        'is_featured', 'views_count', 'like_count', 'prayer_count',
        'comment_count', 'share_count', 'bookmark_count',
        'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'type'        => TestimonyType::class,
            'status'      => TestimonyStatus::class,
            'visibility'  => TestimonyVisibility::class,
            'tags'        => 'array',
            'renditions'  => 'array', // docs/fonctionnalites/qualites-media.md
            'is_featured' => 'boolean',
            'proofs_public' => 'boolean',
            'approved_at' => 'datetime',
            'duration_sec'    => 'integer',
            'views_count'     => 'integer',
            'like_count'      => 'integer',
            'prayer_count'    => 'integer',
            'comment_count'   => 'integer',
            'share_count'     => 'integer',
            'bookmark_count'  => 'integer',
        ];
    }

    // ---------- Lien de partage ----------

    protected static function booted(): void
    {
        // L'identifiant est fixé dès la création pour enregistrer l'URL en une seule requête.
        static::creating(function (Testimony $testimony) {
            if (!$testimony->getKey()) {
                $testimony->setAttribute($testimony->getKeyName(), $testimony->newUniqueId());
            }
            $testimony->share_url = static::shareUrlFor($testimony->getKey());
        });
    }

    /**
     * URL publique d'un témoignage : SHARE_URL (config app.share_url, par défaut le site public),
     * jamais APP_URL ni le domaine de la requête (un serveur local produisait « http://localhost/… »).
     */
    public static function shareUrlFor(string $id): string
    {
        return rtrim((string) config('app.share_url'), '/') . route('testimonies.show', $id, false);
    }

    /**
     * Lien de partage, toujours recalculé : une valeur enregistrée avec une ancienne adresse
     * (localhost, IP locale) n'est jamais renvoyée. La colonne reste renseignée pour les exports.
     */
    public function getShareUrlAttribute(): ?string
    {
        return $this->getKey() ? static::shareUrlFor($this->getKey()) : null;
    }

    // ---------- Relations ----------

    /** Parole prophétique dont ce témoignage raconte l'accomplissement. */
    public function prophecy(): HasOne
    {
        return $this->hasOne(Prophecy::class);
    }

    /** Événement auquel le témoignage est rattaché (docs/fonctionnalites/evenements.md). */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Preuves (2 au plus, privées). Voir docs/fonctionnalites/preuves.md */
    public function proofs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TestimonyProof::class)->orderBy('position');
    }

    /** Vidéo hébergée sur YouTube (publication par lien, administrateurs). Voir docs/fonctionnalites/videos-youtube.md */
    public function isYouTube(): bool
    {
        return filled($this->youtube_id);
    }

    public function youtubeEmbedUrl(): ?string
    {
        return $this->isYouTube() ? \App\Support\YouTube::embedUrl($this->youtube_id) : null;
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ModerationLog::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(TestimonyReport::class);
    }

    /**
     * Fichier média du témoignage (même adresse), pour l'état de conversion des qualités :
     * none, pending, processing, done, failed. Voir docs/fonctionnalites/qualites-media.md
     */
    public function mediaFile(): HasOne
    {
        return $this->hasOne(MediaFile::class, 'url', 'media_url');
    }

    /** État des versions allégées (null si inconnu ou si la relation n'est pas chargée). */
    public function renditionsStatus(): ?string
    {
        if (!empty($this->renditions)) {
            return MediaFile::STATUS_DONE;
        }

        return $this->relationLoaded('mediaFile') ? $this->mediaFile?->processing_status : null;
    }

    /** Direct dont ce témoignage est l'enregistrement (rediffusion), s'il y en a un. */
    public function liveSession(): HasOne
    {
        return $this->hasOne(LiveSession::class);
    }

    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_testimonies', 'testimony_id', 'user_id')
                    ->withPivot('saved_at');
    }

    // ---------- Scopes ----------

    public function scopePublished($query)
    {
        return $query->where('status', TestimonyStatus::Approved)->where('visibility', TestimonyVisibility::Public);
    }

    public function scopePending($query)
    {
        // Le carnet privé ne passe jamais en modération.
        return $query->where('status', TestimonyStatus::Pending)->withoutJournal();
    }

    // ---------- Carnet privé (docs/fonctionnalites/carnet-prive.md) ----------

    /** Témoignages du carnet privé : visibles uniquement par leur auteur. */
    public function scopeJournal($query)
    {
        return $query->where('visibility', TestimonyVisibility::Private);
    }

    /** Exclut le carnet privé (listes d'administration et de modération). */
    public function scopeWithoutJournal($query)
    {
        return $query->where('visibility', '!=', TestimonyVisibility::Private);
    }

    public function isInJournal(): bool
    {
        return $this->visibility === TestimonyVisibility::Private;
    }

    /**
     * « À la une » : témoignages publiés (approuvés ET publics), mis en avant manuellement
     * (is_featured) OU publiés depuis moins de FEATURED_RECENT_DAYS jours.
     */
    public function scopeFeatured($query)
    {
        $since = now()->subDays(self::FEATURED_RECENT_DAYS);

        return $query->published()
            ->where(fn ($q) => $q->where('is_featured', true)
                ->orWhere('approved_at', '>=', $since)
                ->orWhere(fn ($q2) => $q2->whereNull('approved_at')->where('created_at', '>=', $since)));
    }

    /** Du plus récemment publié au plus ancien. */
    public function scopeLatestPublished($query)
    {
        return $query->orderByRaw('COALESCE(approved_at, created_at) DESC');
    }

    // ---------- « À la une » ----------

    /** Durée pendant laquelle un témoignage publié reste automatiquement à la une. */
    public const FEATURED_RECENT_DAYS = 7;

    /** Date de publication : validation par la modération, sinon date de création. */
    public function publishedAt(): ?\Illuminate\Support\Carbon
    {
        return $this->approved_at ?? $this->created_at;
    }

    /** Vrai si le témoignage est à la une : mise en avant manuelle ou publication récente. */
    public function isCurrentlyFeatured(): bool
    {
        if ($this->status !== TestimonyStatus::Approved) {
            return false;
        }

        return $this->is_featured
            || ($this->publishedAt()?->gte(now()->subDays(self::FEATURED_RECENT_DAYS)) ?? false);
    }

    // ---------- Page Vidéos (docs/fonctionnalites/videos.md) ----------

    /** Durée maximale d'un « short » (vidéo courte), en secondes. */
    public const SHORT_MAX_SECONDS = 60;

    /** Vidéos courtes : durée connue et inférieure ou égale à SHORT_MAX_SECONDS. */
    public function scopeShorts($query)
    {
        return $query->where('type', TestimonyType::Video)->whereBetween('duration_sec', [1, self::SHORT_MAX_SECONDS]);
    }

    /** Vidéos hors shorts (durée inconnue comprise). */
    public function scopeLongVideos($query)
    {
        return $query->where('type', TestimonyType::Video)
            ->where(fn ($q) => $q->where('duration_sec', 0)->orWhere('duration_sec', '>', self::SHORT_MAX_SECONDS));
    }

    /** Rediffusions : témoignages vidéo créés à partir de l'enregistrement d'un direct. */
    public function scopeLiveReplays($query)
    {
        return $query->where('type', TestimonyType::Video)
            ->whereIn('id', LiveSession::select('testimony_id')->whereNotNull('testimony_id'));
    }

    /** Recherche sur le titre, le texte, la catégorie et le nom de l'auteur. */
    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%' . addcslashes($term, '\%_') . '%';

        return $query->where(fn ($q) => $q->where('title', 'like', $like)
            ->orWhere('body_text', 'like', $like)
            ->orWhere('category_slug', 'like', $like)
            // category_slug est toujours renseigné (category_id peut manquer).
            ->orWhereIn('category_slug', Category::select('slug')->where('name', 'like', $like))
            ->orWhereIn('user_id', User::select('id')->where('display_name', 'like', $like)));
    }

    public function isShort(): bool
    {
        return $this->type === TestimonyType::Video
            && $this->duration_sec > 0 && $this->duration_sec <= self::SHORT_MAX_SECONDS;
    }

    /**
     * Consultation : publié et public pour tous ; réservé aux abonnés pour les abonnés ;
     * toujours visible par l'auteur et la modération (aperçu avant validation).
     */
    public function isVisibleTo(?User $user): bool
    {
        if ($user && $user->id === $this->user_id) {
            return true;
        }

        // Carnet privé : seul l'auteur y a accès, même pas la modération (docs/fonctionnalites/carnet-prive.md).
        if ($this->isInJournal()) {
            return false;
        }

        if ($user?->canModerate()) {
            return true;
        }

        if ($this->status !== TestimonyStatus::Approved) {
            return false;
        }

        return match ($this->visibility) {
            TestimonyVisibility::Public    => true,
            TestimonyVisibility::Followers => $user !== null && $user->isFollowing($this->user_id),
            default                        => false,
        };
    }

    /** « 1 245 vues », « 1 vue », « Aucune vue ». */
    public function viewsLabel(): string
    {
        return match (true) {
            $this->views_count === 0 => 'Aucune vue',
            $this->views_count === 1 => '1 vue',
            default                  => number_format($this->views_count, 0, ',', ' ') . ' vues',
        };
    }

    /** Durée de lecture estimée d'un témoignage écrit (200 mots par minute). */
    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count($this->body_plain) / 200));
    }

    /**
     * Pastille de type des cartes et lignes compactes : [icône, libellé], ou null pour une vidéo classique.
     * La rediffusion n'est détectée que si la relation liveSession a été chargée (pas de requête ici).
     */
    public function typePill(): ?array
    {
        return match (true) {
            $this->relationLoaded('liveSession') && $this->liveSession !== null => ['fa-tower-broadcast', 'Rediffusion'],
            $this->isShort()                      => ['fa-bolt', 'Short'],
            $this->type === TestimonyType::Audio  => ['fa-microphone', 'Audio'],
            $this->type === TestimonyType::Text   => ['fa-file-lines', 'Texte'],
            default                               => null,
        };
    }

    /** Durée affichée sur une miniature : « 3:25 », « 2 min de lecture », ou null si inconnue. */
    public function durationLabel(): ?string
    {
        return match (true) {
            $this->type === TestimonyType::Text => $this->readingMinutes() . ' min de lecture',
            $this->duration_sec > 0             => $this->formattedDuration(),
            default                             => null,
        };
    }

    /** Versions allégées (qualités) avec leur URL, de la plus légère à la plus lourde. Voir docs/fonctionnalites/qualites-media.md */
    public function playableRenditions(): array
    {
        return $this->type === TestimonyType::Text || !$this->media_url
            ? []
            : MediaFile::renditionsForApi($this->renditions);
    }

    public function scopeOfCategory($query, string $slug)
    {
        return $query->where('category_slug', $slug);
    }

    public function scopeAfter($query, ?string $after)
    {
        if ($after) {
            $query->where('updated_at', '>', $after);
        }
        return $query;
    }

    // ---------- Helpers ----------

    public function formattedDuration(): string
    {
        // 12:34, ou 1:02:05 à partir d'une heure.
        $total = (int) $this->duration_sec;
        $hours = intdiv($total, 3600);
        $mins  = intdiv($total % 3600, 60);
        $secs  = $total % 60;
        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $mins, $secs)
            : sprintf('%d:%02d', $mins, $secs);
    }

    public function isLikedBy(?string $userId): bool
    {
        if (!$userId) return false;
        return $this->reactions()->where('user_id', $userId)->where('type', 'like')->exists();
    }

    public function isPrayedBy(?string $userId): bool
    {
        if (!$userId) return false;
        return $this->reactions()->where('user_id', $userId)->where('type', 'pray')->exists();
    }

    public function isSavedBy(?string $userId): bool
    {
        if (!$userId) return false;
        return $this->savedByUsers()->where('user_id', $userId)->exists();
    }

    // ─── Mise en forme du texte (**gras**, *italique*, émojis) ───────────────

    public function getBodyHtmlAttribute(): HtmlString
    {
        return RichText::toHtml($this->body_text);
    }

    public function getBodyPlainAttribute(): string
    {
        return RichText::plain($this->body_text);
    }
}

<?php

namespace App\Models;

use App\Enums\LiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Témoignage en direct. Voir docs/fonctionnalites/lives.md
 */
class LiveSession extends Model
{
    use HasUuids;

    public const REACTIONS = ['like', 'pray', 'amen', 'worship', 'fire'];

    protected $fillable = [
        'host_id', 'event_id', 'title', 'description', 'category_slug', 'room_name', 'status',
        'prayer_session_id', // salle d'une session de prière (docs/fonctionnalites/sessions-de-priere.md)
        'comments_enabled', 'speakers_enabled', 'started_at', 'ended_at', 'ended_by', 'end_reason', 'peak_viewers',
        'pinned_comment_id',
        'record', 'recording_status', 'egress_id', 'recording_path', 'recording_duration', 'recording_error', 'testimony_id',
        // Caméra IP / encodeur (docs/fonctionnalites/lives-camera-ip.md)
        'source', 'ingress_id', 'ingress_url', 'ingress_stream_key', 'camera_url',
        // Direct YouTube (source « youtube », sans LiveKit) : docs/fonctionnalites/lives.md
        'youtube_video_id',
    ];

    /** Sources acceptées (validation des formulaires et de l'API). */
    public const SOURCES = ['browser', 'rtmp', 'url', 'youtube'];

    /** Sources vidéo d'un direct. */
    /** Sources vidéo [browser|rtmp|url => libellé] (lang/{fr,en}/models.php › live_sources). */
    public static function sources(): array
    {
        return __('models.live_sources');
    }

    protected $hidden = ['ingress_stream_key', 'camera_url'];

    protected function casts(): array
    {
        return [
            'status'           => LiveStatus::class,
            'ingress_stream_key' => 'encrypted',
            'camera_url'       => 'encrypted',
            'comments_enabled' => 'boolean',
            'speakers_enabled' => 'boolean',
            'record'           => 'boolean',
            'recording_duration' => 'integer',
            'started_at'       => 'datetime',
            'ended_at'         => 'datetime',
            'peak_viewers'     => 'integer',
            'comment_count'    => 'integer',
            'like_count'       => 'integer',
            'pray_count'       => 'integer',
            'amen_count'       => 'integer',
            'worship_count'    => 'integer',
            'fire_count'       => 'integer',
        ];
    }

    // ---------- Relations ----------

    /** Événement diffusé (docs/fonctionnalites/evenements.md). */
    protected static function booted(): void
    {
        // Sitemaps recalculés (docs/fonctionnalites/referencement.md).
        static::saved(fn () => \App\Services\Sitemaps::forget());
        static::deleted(fn () => \App\Services\Sitemaps::forget());
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** Session de prière dont ce direct est la salle (docs/fonctionnalites/sessions-de-priere.md). */
    public function prayerSession(): BelongsTo
    {
        return $this->belongsTo(PrayerSession::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(LiveComment::class);
    }

    public function bans(): HasMany
    {
        return $this->hasMany(LiveBan::class);
    }

    /** Demandes d'intervention et passages à l'antenne (docs/fonctionnalites/lives-intervenants.md). */
    public function speakers(): HasMany
    {
        return $this->hasMany(LiveSpeaker::class);
    }

    /** Commentaire épinglé en haut du direct (un seul à la fois). */
    public function pinnedComment(): BelongsTo
    {
        return $this->belongsTo(LiveComment::class, 'pinned_comment_id');
    }

    /** Payload du commentaire épinglé, ou null s'il n'y en a pas (ou plus visible). */
    public function pinnedCommentPayload(): ?array
    {
        $comment = $this->pinned_comment_id ? $this->pinnedComment()->with('user')->first() : null;

        return ($comment && !$comment->is_hidden) ? $comment->toPayload() : null;
    }

    /** Témoignage vidéo créé à partir de l'enregistrement (en attente de relecture, puis publié). */
    public function testimony(): BelongsTo
    {
        return $this->belongsTo(Testimony::class);
    }

    /** Témoignages publiés par les spectateurs sur ce direct terminé (testimonies.live_id, « Directs passés »). */
    public function linkedTestimonies(): HasMany
    {
        return $this->hasMany(Testimony::class, 'live_id');
    }

    /** Libellé de l'état de l'enregistrement, ou null si le direct n'est pas enregistré. */
    public function recordingLabel(): ?string
    {
        if (!$this->record) {
            return null;
        }

        return match ($this->recording_status) {
            'recording', 'processing', 'too_short', 'failed' => __('models.live_recording.' . $this->recording_status),
            'ready'      => __($this->testimony?->status?->value === 'approved' ? 'models.live_recording.ready' : 'models.live_recording.ready_pending'),
            default      => __('models.live_recording.default'),
        };
    }

    // ---------- Portées ----------

    public function scopeActive($query)
    {
        return $query->whereIn('status', [LiveStatus::Preparing, LiveStatus::Live]);
    }

    public function scopeOnAir($query)
    {
        return $query->where('status', LiveStatus::Live);
    }

    // ---------- État ----------

    public function isActive(): bool
    {
        return $this->status !== LiveStatus::Ended;
    }

    public function isOnAir(): bool
    {
        return $this->status === LiveStatus::Live;
    }

    public function isHost(?User $user): bool
    {
        return $user !== null && $user->id === $this->host_id;
    }

    /** Peut masquer des commentaires, épingler, bannir, gérer les intervenants : le diffuseur, les modérateurs et administrateurs. */
    public function canBeModeratedBy(?User $user): bool
    {
        return $user !== null && ($this->isHost($user) || $user->canModerate());
    }

    /**
     * Peut couper (terminer) le direct : son diffuseur ou un administrateur. Jamais un modérateur
     * pour le direct de quelqu'un d'autre.
     */
    public function canBeEndedBy(?User $user): bool
    {
        return $user !== null && ($this->isHost($user) || $user->isAdmin());
    }

    /** Témoigner sur ce direct : personne connectée, direct terminé et visible (docs/fonctionnalites/lives.md). */
    public function canBeTestifiedBy(?User $user): bool
    {
        return $user !== null && $this->status === LiveStatus::Ended && $this->isVisibleTo($user);
    }

    /** Secondes écoulées entre le début du direct et ce moment ; null si le début est inconnu. */
    public function offsetSecondsAt(?\DateTimeInterface $at): ?int
    {
        if (!$this->started_at || !$at) {
            return null;
        }

        return max(0, $at->getTimestamp() - $this->started_at->getTimestamp());
    }

    /** Durée du direct en secondes (début → fin) ; null si le début ou la fin est inconnu. */
    public function durationSeconds(): ?int
    {
        return $this->started_at && $this->ended_at ? $this->offsetSecondsAt($this->ended_at) : null;
    }

    /** « 1:02:05 » / « 12:30 » — comme formatLiveDuration / formatLiveOffset de l'application. */
    public static function formatClock(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%02d:%02d', $m, $s);
    }

    /** Nombre compact « 1,2 k » (fr) / « 1.2k » (en), comme _compact de l'application. */
    public static function compactNumber(int $n): string
    {
        $fr = app()->getLocale() === 'fr';
        $fmt = fn (float $v, int $decimals) => number_format($v, $decimals, $fr ? ',' : '.', '');
        if ($n < 1000) {
            return (string) $n;
        }
        if ($n < 1000000) {
            return $fmt($n / 1000, $n < 10000 ? 1 : 0) . ($fr ? ' k' : 'k');
        }

        return $fmt($n / 1000000, 1) . ($fr ? ' M' : 'M');
    }

    /** Somme des réactions (like, pray, amen, worship, fire). */
    public function totalReactions(): int
    {
        return array_sum($this->reactionCounts());
    }

    /** Un spectateur peut voir le direct en préparation seulement s'il en est modérateur. */
    public function isVisibleTo(?User $user): bool
    {
        if ($this->status === LiveStatus::Preparing && !$this->canBeModeratedBy($user)) {
            return false;
        }
        // Salle d'une session de prière : mêmes règles que la session (réservée aux abonnés, supprimée…).
        if ($this->prayer_session_id) {
            return $this->isHost($user) || (bool) $this->prayerSession?->isVisibleTo($user);
        }

        return true;
    }

    public function isBanned(User $user): bool
    {
        return $this->bans()->where('user_id', $user->id)->exists();
    }

    /** @return array<string, int> */
    public function reactionCounts(): array
    {
        return collect(self::REACTIONS)->mapWithKeys(fn ($r) => [$r => (int) $this->{"{$r}_count"}])->all();
    }

    /** Vidéo fournie par une caméra IP ou un encodeur (et non par l'appareil du diffuseur). */
    public function usesExternalCamera(): bool
    {
        return in_array($this->source, ['rtmp', 'url'], true);
    }

    /** Direct diffusé sur YouTube : ni salle LiveKit, ni jeton, ni enregistrement (docs/fonctionnalites/lives.md). */
    public function isYoutube(): bool
    {
        return $this->source === 'youtube';
    }

    /**
     * Vidéo YouTube du direct (et sa rediffusion une fois terminé) : identifiant, liens de lecture et miniature ;
     * null pour un direct LiveKit.
     *
     * @return array{id: string, url: string, embedUrl: string, thumbnailUrl: string}|null
     */
    public function youtube(): ?array
    {
        $id = $this->youtube_video_id;
        if (!$this->isYoutube() || !filled($id)) return null;

        return [
            'id'           => $id,
            'url'          => \App\Support\YouTube::watchUrl($id),
            'embedUrl'     => \App\Support\YouTube::embedUrl($id),
            'thumbnailUrl' => \App\Support\YouTube::thumbnailUrl($id),
        ];
    }

    /** Identité LiveKit du flux de la caméra : commence par « host- », donc affichée comme le diffuseur. */
    public function cameraIdentity(): string
    {
        return 'host-camera-' . $this->id;
    }
}

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
    ];

    /** Sources vidéo d'un direct. */
    public const SOURCES = [
        'browser' => "Caméra de cet appareil",
        'rtmp'    => 'Caméra IP ou encodeur (RTMP)',
        'url'     => 'Adresse du flux de la caméra',
    ];

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

    /** Libellé de l'état de l'enregistrement, ou null si le direct n'est pas enregistré. */
    public function recordingLabel(): ?string
    {
        if (!$this->record) {
            return null;
        }

        return match ($this->recording_status) {
            'recording'  => 'Enregistrement en cours',
            'processing' => 'Vidéo en cours de finalisation',
            'ready'      => $this->testimony?->status?->value === 'approved' ? 'Rediffusion disponible' : 'Vidéo en attente de relecture',
            'too_short'  => 'Direct trop court : pas de vidéo créée',
            'failed'     => 'Échec de l\'enregistrement',
            default      => 'Sera enregistré au passage à l\'antenne',
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

    /** Peut couper le direct, masquer des commentaires, bannir : le diffuseur, les modérateurs et administrateurs. */
    public function canBeModeratedBy(?User $user): bool
    {
        return $user !== null && ($this->isHost($user) || $user->canModerate());
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

    /** Identité LiveKit du flux de la caméra : commence par « host- », donc affichée comme le diffuseur. */
    public function cameraIdentity(): string
    {
        return 'host-camera-' . $this->id;
    }
}

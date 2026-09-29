<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Intervenant d'un direct : demande dans la file, invitation, passage à l'antenne.
 * Voir docs/fonctionnalites/lives-intervenants.md
 */
class LiveSpeaker extends Model
{
    use HasUuids;

    public const WAITING   = 'waiting';
    public const INVITED   = 'invited';
    public const ON_STAGE  = 'on_stage';
    public const DONE      = 'done';
    public const CANCELLED = 'cancelled';
    public const DECLINED  = 'declined';
    public const EXPIRED   = 'expired';

    /** Demande encore en cours (compte pour « une seule demande à la fois »). */
    public const OPEN = [self::WAITING, self::INVITED, self::ON_STAGE];

    /** Occupe la place unique à l'antenne. */
    public const STAGE = [self::INVITED, self::ON_STAGE];

    protected $fillable = [
        'live_session_id', 'user_id', 'status', 'message', 'identity', 'camera',
        'invited_at', 'started_at', 'ended_at', 'ended_reason', 'handled_by',
    ];

    protected function casts(): array
    {
        return [
            'camera'     => 'boolean',
            'invited_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at'   => 'datetime',
        ];
    }

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN);
    }

    /** Invitation restée sans réponse au-delà du délai. */
    public function isInvitationExpired(): bool
    {
        return $this->status === self::INVITED
            && $this->invited_at?->lt(now()->subSeconds((int) config('livekit.stage_invite_timeout')));
    }

    /**
     * Format commun web / mobile / messages temps réel.
     * Le sujet annoncé (message) n'est inclus que pour le diffuseur, les modérateurs et l'intéressé.
     */
    public function toPayload(bool $withMessage = false, ?int $position = null): array
    {
        return array_filter([
            'id'         => $this->id,
            'status'     => $this->status,
            'camera'     => $this->camera,
            'position'   => $position,
            'message'    => $withMessage ? $this->message : null,
            'invitedAt'  => $this->invited_at?->toIso8601String(),
            'expiresAt'  => $this->status === self::INVITED
                ? $this->invited_at?->copy()->addSeconds((int) config('livekit.stage_invite_timeout'))->toIso8601String()
                : null,
            'startedAt'  => $this->started_at?->toIso8601String(),
            'createdAt'  => $this->created_at?->toIso8601String(),
            'user'       => [
                'id'          => $this->user_id,
                'displayName' => $this->user?->display_name,
                'initials'    => $this->user?->initials,
                'avatarUrl'   => $this->user?->avatar_url,
            ],
        ], fn ($v) => $v !== null);
    }
}

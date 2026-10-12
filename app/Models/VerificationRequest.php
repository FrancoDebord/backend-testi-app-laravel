<?php

namespace App\Models;

use App\Enums\PersonalVerificationRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande de vérification d'un compte personnel. Les pièces justificatives sont
 * sur le disque privé (jamais d'URL publique). Voir docs/fonctionnalites/verification-des-comptes.md
 */
class VerificationRequest extends Model
{
    use HasUuids;

    public const PENDING  = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const REVOKED  = 'revoked';

    public const STATUS_LABELS = [
        self::PENDING  => 'En attente',
        self::APPROVED => 'Approuvée',
        self::REJECTED => 'Refusée',
        self::REVOKED  => 'Vérification retirée',
    ];

    protected $fillable = [
        'user_id', 'full_name', 'role', 'role_other', 'church', 'links', 'message',
        'documents', 'status', 'review_note', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'role'        => PersonalVerificationRole::class,
            'links'       => 'array',
            'documents'   => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::PENDING  => 'badge-pending',
            self::APPROVED => 'badge-validated',
            default        => 'badge-rejected',
        };
    }

    /** Fonction affichée : « Pasteur », ou le texte libre pour « Autre ». */
    public function roleLabel(): string
    {
        if ($this->role === PersonalVerificationRole::Other && $this->role_other) {
            return $this->role_other;
        }
        return $this->role?->label() ?? '';
    }

    public function scopePending($query)
    {
        return $query->where('status', self::PENDING);
    }
}

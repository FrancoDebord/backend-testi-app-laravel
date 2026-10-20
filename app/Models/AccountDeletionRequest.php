<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande de suppression de compte faite sans connexion (page /suppression-compte) :
 * traitée par un super-administrateur. Voir docs/fonctionnalites/confidentialite-et-suppression.md
 */
class AccountDeletionRequest extends Model
{
    use HasUuids;

    // Suppression du compte entier, ou de certaines données seulement (précisées dans le message).
    public const TYPE_ACCOUNT = 'account';
    public const TYPE_PARTIAL = 'partial';
    public const TYPES = [self::TYPE_ACCOUNT, self::TYPE_PARTIAL];

    public const STATUS_PENDING  = 'pending';
    public const STATUS_DONE     = 'done';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = ['type', 'contact', 'contact_type', 'user_id', 'message', 'ip_address', 'status', 'handled_by', 'handled_at'];

    protected $attributes = ['type' => self::TYPE_ACCOUNT, 'status' => self::STATUS_PENDING];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isPartial(): bool
    {
        return $this->type === self::TYPE_PARTIAL;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}

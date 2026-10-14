<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Journal d'audit de l'administration (App\Services\AdminAudit). Voir docs/fonctionnalites/gestion-des-temoignages.md */
class AdminAuditLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['actor_id', 'actor_name', 'action', 'subject_type', 'subject_id', 'before', 'after', 'reason', 'ip', 'created_at'];

    protected function casts(): array
    {
        return [
            'before'     => 'array',
            'after'      => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** Libellé lisible de l'action (back-office). */
    public function label(): string
    {
        return \App\Services\AdminAudit::LABELS[$this->action] ?? $this->action;
    }
}

<?php

namespace App\Models;

use App\Enums\RejectionReason;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'testimony_id', 'moderator_id', 'action', 'rejection_reason', 'moderator_note', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'rejection_reason' => RejectionReason::class,
            'created_at'       => 'datetime',
        ];
    }

    public function testimony(): BelongsTo
    {
        return $this->belongsTo(Testimony::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}

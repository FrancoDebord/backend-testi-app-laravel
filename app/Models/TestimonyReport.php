<?php

namespace App\Models;

use App\Enums\RejectionReason;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestimonyReport extends Model
{
    use HasUuids;

    protected $fillable = [
        'testimony_id', 'reporter_id', 'reason', 'details',
        'status', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reason'      => RejectionReason::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function testimony(): BelongsTo
    {
        return $this->belongsTo(Testimony::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

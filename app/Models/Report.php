<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'testimony_id',
        'reporter_id',
        'reason',
        'comment',
    ];

    public function testimony(): BelongsTo
    {
        return $this->belongsTo(Testimony::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyVerseReaction extends Model
{
    public $timestamps = false;

    protected $fillable = ['daily_verse_id', 'user_id', 'type'];

    public function verse(): BelongsTo
    {
        return $this->belongsTo(DailyVerse::class, 'daily_verse_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

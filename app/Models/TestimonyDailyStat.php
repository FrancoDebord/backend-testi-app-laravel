<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Statistiques d'un témoignage pour un jour (App\Services\TestimonyStats). */
class TestimonyDailyStat extends Model
{
    protected $fillable = ['testimony_id', 'date', 'views', 'unique_viewers', 'reactions', 'comments', 'shares', 'video_watch_seconds'];

    protected function casts(): array
    {
        return [
            'date'                => 'date:Y-m-d',
            'views'               => 'integer',
            'unique_viewers'      => 'integer',
            'reactions'           => 'integer',
            'comments'            => 'integer',
            'shares'              => 'integer',
            'video_watch_seconds' => 'integer',
        ];
    }

    public function testimony(): BelongsTo
    {
        return $this->belongsTo(Testimony::class)->withTrashed();
    }
}

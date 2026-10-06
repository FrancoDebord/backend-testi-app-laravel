<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** « J'ai prié pour cette parole » : date et note facultative (journal de prière). */
class ProphecyPrayer extends Model
{
    protected $fillable = ['prophecy_id', 'note', 'prayed_at'];

    protected function casts(): array
    {
        return ['prayed_at' => 'datetime'];
    }

    public function prophecy(): BelongsTo
    {
        return $this->belongsTo(Prophecy::class);
    }
}

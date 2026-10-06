<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Image de couverture d'un événement (carrousel, la première sert de vignette). */
class EventImage extends Model
{
    use HasUuids;

    protected $fillable = ['event_id', 'url', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

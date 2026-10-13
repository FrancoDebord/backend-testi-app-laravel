<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Design et réglages du texte du verset du jour d'un membre (docs/fonctionnalites/designs-du-verset.md). */
class VerseStylePreference extends Model
{
    protected $fillable = ['user_id', 'design_id', 'design_chosen_on', 'design_always', 'font', 'text_size', 'text_align'];

    protected function casts(): array
    {
        return [
            'design_chosen_on' => 'date:Y-m-d',
            'design_always'    => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

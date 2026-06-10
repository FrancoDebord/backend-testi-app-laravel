<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BibleBook extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'translation', 'number', 'name', 'abbreviation', 'testament', 'chapters_count',
    ];

    protected function casts(): array
    {
        return [
            'number'         => 'integer',
            'chapters_count' => 'integer',
        ];
    }

    public function verses(): HasMany
    {
        return $this->hasMany(BibleVerse::class, 'book', 'number')
                    ->where('translation', $this->translation);
    }
}

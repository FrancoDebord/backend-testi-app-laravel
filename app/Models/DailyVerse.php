<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyVerse extends Model
{
    use HasFactory;

    protected $fillable = [
        'verse_text', 'reference', 'scheduled_date', 'is_active',
        'theme', 'week_number',
        'like_count', 'prayer_count', 'amen_count', 'share_count',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'is_active'      => 'boolean',
            'week_number'    => 'integer',
            'like_count'     => 'integer',
            'prayer_count'   => 'integer',
            'amen_count'     => 'integer',
            'share_count'    => 'integer',
        ];
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(DailyVerseReaction::class);
    }

    public static function today(): ?self
    {
        return static::where('scheduled_date', now()->toDateString())
                     ->where('is_active', true)
                     ->first()
            ?? static::where('is_active', true)->inRandomOrder()->first();
    }

    public function userReactions(string $userId): array
    {
        return $this->reactions()
                    ->where('user_id', $userId)
                    ->pluck('type')
                    ->toArray();
    }

    public function themeLabel(): string
    {
        return match($this->theme) {
            'faithfulness'  => 'Fidélité de Dieu',
            'testimonies'   => 'Importance des témoignages',
            'promises'      => 'Promesses de Dieu',
            'obedience'     => 'Vie d\'obéissance',
            default         => 'Méditation du jour',
        };
    }
}

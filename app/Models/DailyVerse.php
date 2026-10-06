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

    public function comments(): HasMany
    {
        return $this->hasMany(DailyVerseComment::class);
    }

    public function prayerRequests(): HasMany
    {
        return $this->hasMany(PrayerRequest::class);
    }

    public static function today(): ?self
    {
        return static::forDate(now()->toDateString());
    }

    /**
     * Verset programmé ce jour-là ; sinon un verset actif choisi d'après la date (le même toute la
     * journée : commentaires et requêtes de prière restent rattachés au verset affiché).
     */
    public static function forDate(string $date): ?self
    {
        $scheduled = static::where('scheduled_date', $date)->where('is_active', true)->first();
        if ($scheduled) {
            return $scheduled;
        }
        $count = static::where('is_active', true)->count();
        if ($count === 0) {
            return null;
        }
        $index = intdiv(strtotime($date . ' 00:00:00 UTC') ?: 0, 86400) % $count;

        return static::where('is_active', true)->orderBy('id')->skip($index)->first();
    }

    /** Référence analysée (livre, chapitre, versets) ou null si elle n'est pas reconnue. */
    public function parsedReference(): ?array
    {
        return \App\Support\BibleReference::parse($this->reference);
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

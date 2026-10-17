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

    /** Verset du jour au calendrier Africa/Porto-Novo : change à minuit, en même temps que le design du jour. */
    public static function today(): ?self
    {
        return static::forDate(\App\Services\VerseDesignOfTheDay::todayDate());
    }

    /** Images de partage générées : supprimées quand le verset change (texte, référence, date, activation). */
    protected static function booted(): void
    {
        static::saved(function (self $verse) {
            if ($verse->wasChanged(['verse_text', 'reference', 'scheduled_date', 'is_active']) || $verse->wasRecentlyCreated) {
                \App\Services\VerseShareImage::forgetFor($verse);
            }
        });
        static::deleted(fn (self $verse) => \App\Services\VerseShareImage::forgetFor($verse));
    }

    /**
     * Verset programmé ce jour-là ; sinon un verset actif choisi d'après la date (le même toute la
     * journée : commentaires et requêtes de prière restent rattachés au verset affiché).
     */
    public static function forDate(string $date): ?self
    {
        $scheduled = static::whereDate('scheduled_date', $date)->where('is_active', true)->first();
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
        $themes = __('models.daily_verse_theme');

        return $themes[$this->theme] ?? $themes['default'];
    }
}

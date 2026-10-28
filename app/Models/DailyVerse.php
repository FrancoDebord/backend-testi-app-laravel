<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DailyVerse extends Model
{
    use HasFactory;

    protected $fillable = [
        'verse_text', 'reference', 'scheduled_date', 'is_active',
        'theme', 'week_number',
        // Thème de la semaine et version du texte importés par versets:import (docs/fonctionnalites/verset-du-jour.md)
        'theme_semaine_id', 'version',
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

    /** Thème de la semaine (verset clé, période), importé par versets:import. */
    public function themeSemaine(): BelongsTo
    {
        return $this->belongsTo(ThemeSemaine::class);
    }

    /** Jour de la semaine de la date programmée, dans la langue de la page (« Dimanche »). */
    public function dayName(): ?string
    {
        return $this->scheduled_date ? Str::ucfirst($this->scheduled_date->locale(app()->getLocale())->dayName) : null;
    }

    /** Verset programmé à cette date exacte (sans repli). */
    public function scopePourDate(Builder $query, CarbonInterface $date): Builder
    {
        return $query->whereDate('scheduled_date', $date->toDateString());
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

        // Cache des versets par date (forDate) : invalidé à chaque changement de verset.
        static::saved(fn () => Cache::forever(self::CACHE_VERSION_KEY, (string) Str::uuid()));
        static::deleted(fn () => Cache::forever(self::CACHE_VERSION_KEY, (string) Str::uuid()));
    }

    private const CACHE_VERSION_KEY = 'daily_verse:version';

    /**
     * Verset programmé ce jour-là ; sinon celui du même jour et du même mois de l'année la plus proche
     * (2028-03-28 → 2027-03-28) ; sinon un verset actif choisi d'après la date (le même toute la journée :
     * commentaires et requêtes de prière restent rattachés au verset affiché). Null : aucun verset actif.
     * Résultat gardé en cache jusqu'à minuit (Africa/Porto-Novo), invalidé quand un verset change.
     */
    public static function forDate(string $date): ?self
    {
        $version = Cache::rememberForever(self::CACHE_VERSION_KEY, fn () => (string) Str::uuid());
        $midnight = CarbonImmutable::now(\App\Services\VerseDesignOfTheDay::timezone())->addDay()->startOfDay();
        $id = Cache::remember("daily_verse:for:{$version}:{$date}", $midnight, fn () => self::resolveFor($date)?->id ?? 0);

        return $id ? static::find($id) : null;
    }

    private static function resolveFor(string $date): ?self
    {
        $scheduled = static::whereDate('scheduled_date', $date)->where('is_active', true)->first();
        if ($scheduled) {
            return $scheduled;
        }

        // Même jour, même mois, autre année : la plus proche (à égalité, la plus récente).
        $day = CarbonImmutable::parse($date);
        $sameDay = static::where('is_active', true)->whereNotNull('scheduled_date')
            ->whereMonth('scheduled_date', $day->month)->whereDay('scheduled_date', $day->day)->get()
            ->sortBy(fn (self $v) => [abs($v->scheduled_date->year - $day->year), -$v->scheduled_date->year])
            ->first();
        if ($sameDay) {
            return $sameDay;
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

    /** Code connu (faithfulness…) → libellé traduit ; libellé importé (verse:import) → tel quel. */
    public function themeLabel(): string
    {
        $themes = __('models.daily_verse_theme');

        return $themes[$this->theme] ?? ($this->theme ?: $themes['default']);
    }
}

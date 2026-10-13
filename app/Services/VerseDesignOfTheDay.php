<?php

namespace App\Services;

use App\Support\VerseDesigns;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Design du jour du verset : même algorithme que l'app (dailyVerseDesign), même id pour tous ce
 * jour-là (calendrier Africa/Porto-Novo), jamais le même deux jours de suite.
 *
 *   jours = jours entre 2024-01-01 et la date ; n = designs actifs (ordre du catalogue)
 *   pas   = premier de [5, 7, 3, 1] premier avec n
 *   index = ((jours × pas) mod n + n) mod n
 *
 * Voir docs/fonctionnalites/designs-du-verset.md
 */
class VerseDesignOfTheDay
{
    public static function timezone(): string
    {
        return config('verse_designs.timezone', 'Africa/Porto-Novo');
    }

    /** Date du jour (AAAA-MM-JJ) au fuseau du verset. */
    public static function todayDate(): string
    {
        return CarbonImmutable::now(self::timezone())->toDateString();
    }

    public static function for(CarbonInterface $date): string
    {
        return self::forDate($date->copy()->setTimezone(self::timezone())->toDateString());
    }

    /** Design d'une date du calendrier (AAAA-MM-JJ). */
    public static function forDate(string $date): string
    {
        $ids = VerseDesigns::activeIds();
        $n = count($ids);
        if ($n === 0) {
            return 'minimal';
        }

        $days = intdiv(strtotime($date . ' 00:00:00 UTC') - strtotime(config('verse_designs.day_epoch', '2024-01-01') . ' 00:00:00 UTC'), 86400);
        $step = 1;
        foreach (config('verse_designs.day_steps', [5, 7, 3, 1]) as $s) {
            if (self::gcd($s, $n) === 1) {
                $step = $s;
                break;
            }
        }

        return $ids[(($days * $step) % $n + $n) % $n];
    }

    private static function gcd(int $a, int $b): int
    {
        return $b === 0 ? $a : self::gcd($b, $a % $b);
    }
}

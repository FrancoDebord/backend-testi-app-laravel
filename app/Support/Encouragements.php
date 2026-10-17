<?php

namespace App\Support;

/**
 * Versets et invitations à témoigner (config/encouragements.php) dans la langue courante.
 * La configuration garde l'ordre, les icônes, le rythme et la référence française qui sert d'identifiant
 * (« Psaume 66:16 ») ; les textes affichés viennent de lang/{fr,en}/encouragements.php, au même rang.
 * Voir docs/fonctionnalites/encouragements.md
 */
final class Encouragements
{
    /** @return list<array{ref: string, text: string, source_ref: string}> versets d'encouragement */
    public static function verses(): array
    {
        return self::localizeVerses(config('encouragements.verses', []), 'encouragements.verses');
    }

    /** @return list<array{ref: string, text: string, source_ref: string}> versets des paroles prophétiques */
    public static function prophecyVerses(): array
    {
        return self::localizeVerses(config('encouragements.prophecy_verses', []), 'encouragements.prophecy_verses');
    }

    /** @return list<string> invitations (sans verset) */
    public static function calls(): array
    {
        $calls = array_values(config('encouragements.calls', []));
        $lines = self::lines('encouragements.calls');

        return array_map(fn ($call, $i) => is_string($lines[$i] ?? null) ? $lines[$i] : $call, $calls, array_keys($calls));
    }

    /** @return list<array{title: string, summary: ?string, icon: ?string, verses: list<array{ref: string, text: string, source_ref: string}>}> */
    public static function whyTestify(): array
    {
        $reasons = array_values(config('encouragements.why_testify', []));
        $lines   = self::lines('encouragements.why_testify');

        return array_map(function (array $reason, int $i) use ($lines) {
            $line = is_array($lines[$i] ?? null) ? $lines[$i] : [];

            return [
                'title'   => $line['title'] ?? $reason['title'],
                'summary' => $line['summary'] ?? ($reason['summary'] ?? null),
                'icon'    => $reason['icon'] ?? null,
                'verses'  => self::merge(array_values($reason['verses'] ?? []), is_array($line['verses'] ?? null) ? $line['verses'] : []),
            ];
        }, $reasons, array_keys($reasons));
    }

    private static function localizeVerses(array $verses, string $key): array
    {
        return self::merge(array_values($verses), self::lines($key));
    }

    /** Versets bibliques : jamais traduits (texte et référence de config/encouragements.php, en français). */
    private static function merge(array $verses, array $lines): array
    {
        return array_map(fn (array $verse) => [
            'ref'        => $verse['ref'],
            'text'       => $verse['text'],
            'source_ref' => $verse['ref'],
        ], $verses);
    }

    private static function lines(string $key): array
    {
        $lines = __($key);

        return is_array($lines) ? array_values($lines) : [];
    }
}

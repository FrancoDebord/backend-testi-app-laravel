<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Adresses web écrites ou collées dans un texte saisi (commentaires, requêtes, descriptions, bio…) :
 * rendues cliquables à l'affichage, le texte enregistré ne change pas.
 * Voir docs/fonctionnalites/liens-et-apercus.md
 */
final class Linkify
{
    /** `https://…`, `http://…` ou `www.…`, jusqu'à une espace ou un caractère qui ne peut pas finir une adresse. */
    public const URL = '~(?<![\w@/.\-])(?:https?://|www\.)[^\s<>"\'`]+~iu';

    /** Libellé affiché au plus (l'adresse complète reste dans le lien). */
    private const LABEL_MAX = 60;

    /** Texte échappé, adresses en liens. */
    public static function html(?string $text): HtmlString
    {
        return new HtmlString(self::escape((string) $text));
    }

    /** Échappe un morceau de texte et y transforme les adresses en liens (utilisé aussi par Mentions::html). */
    public static function escape(string $text): string
    {
        $html = '';
        $last = 0;
        foreach (self::matches($text) as [$url, $offset]) {
            $html .= e(substr($text, $last, $offset - $last)) . self::anchor($url);
            $last = $offset + strlen($url);
        }

        return $html . e(substr($text, $last));
    }

    /**
     * Adresses du texte, dans l'ordre, sans doublon (`www.` complété en `https://`).
     *
     * @return string[]
     */
    public static function urls(?string $text): array
    {
        return array_values(array_unique(array_map(fn ($m) => self::href($m[0]), self::matches((string) $text))));
    }

    /** Première adresse du texte, hors images envoyées sur ce site (texte mis en forme des témoignages). */
    public static function firstUrl(?string $text): ?string
    {
        foreach (self::urls($text) as $url) {
            if (!RichText::isOwnImage($url)) {
                return $url;
            }
        }

        return null;
    }

    /** @return array<int, array{0: string, 1: int}> adresse telle qu'écrite et position (octets) */
    private static function matches(string $text): array
    {
        preg_match_all(self::URL, $text, $found, PREG_OFFSET_CAPTURE);

        $result = [];
        foreach ($found[0] as [$url, $offset]) {
            $url = self::trimEnd($url);
            if (self::isValid(self::href($url))) {
                $result[] = [$url, $offset];
            }
        }

        return $result;
    }

    /** Retire la ponctuation de fin de phrase (« voir https://a.fr. ») et une parenthèse fermante non ouverte. */
    private static function trimEnd(string $url): string
    {
        while ($url !== '') {
            $last = substr($url, -1);
            if (str_contains('.,;:!?*_~\'"', $last)) {
                $url = substr($url, 0, -1);
            } elseif ($last === ')' && substr_count($url, ')') > substr_count($url, '(')) {
                $url = substr($url, 0, -1);
            } elseif ($last === ']' && substr_count($url, ']') > substr_count($url, '[')) {
                $url = substr($url, 0, -1);
            } else {
                break;
            }
        }

        return $url;
    }

    private static function href(string $url): string
    {
        return preg_match('~^https?://~i', $url) ? $url : 'https://' . $url;
    }

    /** Hôte avec au moins un point, sans identifiants. */
    private static function isValid(string $url): bool
    {
        $parts = parse_url($url);
        $host  = $parts['host'] ?? '';

        return str_contains($host, '.') && !isset($parts['user']);
    }

    private static function anchor(string $url): string
    {
        $href     = self::href($url);
        $internal = in_array(strtolower((string) parse_url($href, PHP_URL_HOST)), self::ownHosts(), true);

        return '<a href="' . e($href) . '"'
            . ($internal ? '' : ' target="_blank" rel="nofollow noopener noreferrer ugc"')
            . ' class="break-all text-primary-700 underline underline-offset-2 hover:text-primary-800" data-text-link>'
            . e(Str::limit($url, self::LABEL_MAX)) . '</a>';
    }

    /** @return string[] */
    private static function ownHosts(): array
    {
        return array_map('strtolower', array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            parse_url((string) config('app.share_url'), PHP_URL_HOST),
        ]));
    }
}

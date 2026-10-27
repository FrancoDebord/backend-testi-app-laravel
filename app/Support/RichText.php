<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Texte riche des témoignages (docs/fonctionnalites/texte-riche-et-apercu.md).
 *
 * Format stocké : Markdown léger, le même que l'application mobile (lib/shared/utils/rich_text_utils.dart).
 *   En ligne : **gras**  __italique__ (ou *italique*, anciens textes)  ~~barré~~  [texte](https://…)
 *              liens https://… nus  \* = astérisque littéral  mentions @[Nom](user:id)
 *   Par ligne : # Titre   > citation   - élément de liste   1. liste numérotée
 *              ![légende](URL)  image envoyée sur le serveur (seule sur sa ligne)
 * Aucun HTML n'est jamais stocké : le texte est échappé avant conversion. Le rendu est sans retour à la
 * ligne (paragraphes <p>, <br>) : les conteneurs utilisent la classe `rich-text` (resources/css/app.css).
 */
class RichText
{
    private const STAR = "\u{E000}"; // protège les \*
    private const SLOT = "\u{E001}"; // emplacement d'un lien déjà converti

    /** Dossier des images insérées dans les témoignages (disque public). */
    public const IMAGE_DIR = 'testimony-images';

    /** Images au plus dans un témoignage. */
    public const MAX_IMAGES = 10;

    public static function toHtml(?string $text): HtmlString
    {
        if ($text === null || trim($text) === '') {
            return new HtmlString('');
        }

        $html = '';
        $para = [];        // lignes du paragraphe en cours
        $quote = [];       // lignes de la citation en cours
        $list = null;      // ['ul'|'ol', [éléments]]

        $flush = function () use (&$html, &$para, &$quote, &$list) {
            if ($para) {
                $html .= '<p>' . implode('<br>', $para) . '</p>';
                $para = [];
            }
            if ($quote) {
                $html .= '<blockquote>' . implode('<br>', $quote) . '</blockquote>';
                $quote = [];
            }
            if ($list) {
                $html .= "<{$list[0]}>" . implode('', array_map(fn ($li) => "<li>{$li}</li>", $list[1])) . "</{$list[0]}>";
                $list = null;
            }
        };

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = rtrim($line);

            if ($line === '') {
                $flush();
            } elseif ($image = self::imageLine($line)) {
                $flush();
                $html .= '<figure><img src="' . e($image['url']) . '" alt="' . e($image['alt']) . '" loading="lazy">'
                    . ($image['alt'] !== '' ? '<figcaption>' . e($image['alt']) . '</figcaption>' : '') . '</figure>';
            } elseif (preg_match('/^#{1,3}\s+(.+)$/u', $line, $m)) {
                $flush();
                $html .= '<h3>' . self::inline($m[1]) . '</h3>';
            } elseif (preg_match('/^>\s?(.*)$/u', $line, $m)) {
                if ($para || $list) $flush();
                $quote[] = self::inline($m[1]);
            } elseif (preg_match('/^(?:[-•]\s+(.+)|(\d{1,3})[.)]\s+(.+))$/u', $line, $m)) {
                $tag = ($m[1] ?? '') !== '' ? 'ul' : 'ol';
                if ($para || $quote || ($list && $list[0] !== $tag)) $flush();
                $list ??= [$tag, []];
                $list[1][] = self::inline($tag === 'ul' ? $m[1] : $m[3]);
            } else {
                if ($quote || $list) $flush();
                $para[] = self::inline($line);
            }
        }
        $flush();

        return new HtmlString($html);
    }

    /** Texte sans mise en forme, une ligne par ligne (aperçus, recherche, notifications) ; images retirées. */
    public static function plain(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            if (self::imageLine(rtrim($line))) continue;
            $line = preg_replace('/^(?:#{1,3}\s+|>\s?|[-•]\s+|(\d{1,3}[.)])\s+)/u', '$1 ', $line);
            $lines[] = trim(html_entity_decode(strip_tags(self::inline($line)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }

    /** Adresses des images insérées dans le texte (contrôle du nombre d'images). */
    public static function imageUrls(?string $text): array
    {
        $urls = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
            if ($image = self::imageLine(rtrim($line))) $urls[] = $image['url'];
        }

        return $urls;
    }

    /** Image envoyée sur ce serveur (dossier testimony-images) : jamais d'image d'un autre site. */
    public static function isOwnImage(string $url): bool
    {
        $parts = parse_url($url);
        if (!in_array($parts['scheme'] ?? '', ['http', 'https'], true) || !isset($parts['host'], $parts['path'])) {
            return false;
        }
        $hosts = array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            parse_url((string) config('app.share_url'), PHP_URL_HOST),
            app()->runningInConsole() ? null : request()->getHost(),
        ]);

        return in_array(strtolower($parts['host']), array_map('strtolower', $hosts), true)
            && preg_match('#^/storage/' . self::IMAGE_DIR . '/[A-Za-z0-9._/-]+$#', $parts['path']) === 1;
    }

    /** @return array{url: string, alt: string}|null */
    private static function imageLine(string $line): ?array
    {
        if (!preg_match('/^!\[([^\]\n]*)\]\((\S+)\)$/u', trim($line), $m) || !self::isOwnImage($m[2])) {
            return null;
        }

        return ['url' => $m[2], 'alt' => trim($m[1])];
    }

    /** Mise en forme en ligne d'un morceau de texte (échappé ici). */
    private static function inline(string $text): string
    {
        $html = e(str_replace('\\*', self::STAR, $text));

        // Liens d'abord, mis de côté : leur adresse ne doit pas être mise en forme (« __ », « * »).
        $links = [];
        $keep = function (string $a) use (&$links) {
            $links[] = $a;
            return self::SLOT . (count($links) - 1) . self::SLOT;
        };
        $anchor = fn (string $url, string $label) => '<a href="' . $url . '" target="_blank" rel="nofollow noopener noreferrer ugc">' . $label . '</a>';

        $html = preg_replace_callback('/\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/u', fn ($m) => $keep($anchor($m[2], self::marks($m[1]))), $html);
        // Mentions « @ » (docs/fonctionnalites/mentions.md) : jeton `@[Nom](user:id)` → profil (nom déjà échappé).
        $html = preg_replace_callback(\App\Services\Mentions::TOKEN, fn ($m) => $keep('<a href="' . e(route('profiles.show', strtolower($m[2])))
            . '" class="font-semibold text-primary-700 hover:underline" data-mention-link="' . e(strtolower($m[2])) . '">@' . $m[1] . '</a>'), $html) ?? $html;
        // Adresses nues.
        $html = preg_replace_callback('/(?<![\w\/"=])https?:\/\/[^\s<]+[^\s<.,;:!?)\]\'"]/u', fn ($m) => $keep($anchor($m[0], $m[0])), $html);

        $html = self::marks($html);
        $html = preg_replace_callback('/' . self::SLOT . '(\d+)' . self::SLOT . '/u', fn ($m) => $links[(int) $m[1]], $html);

        return str_replace(self::STAR, '*', $html);
    }

    /** Gras, italique, barré. */
    private static function marks(string $html): string
    {
        $html = preg_replace('/\*\*\*(?=\S)([^\n]+?)(?<=\S)\*\*\*/u', '<strong><em>$1</em></strong>', $html);
        $html = preg_replace('/\*\*(?=\S)([^\n]+?)(?<=\S)\*\*/u', '<strong>$1</strong>', $html);
        $html = preg_replace('/(?<!\*)\*(?=[^\s*])([^\n]*?)(?<=[^\s*])\*(?!\*)/u', '<em>$1</em>', $html);
        $html = preg_replace('/(?<![\w_])__(?=\S)([^\n]+?)(?<=\S)__(?![\w_])/u', '<em>$1</em>', $html);

        return preg_replace('/~~(?=\S)([^\n]+?)(?<=\S)~~/u', '<s>$1</s>', $html);
    }
}

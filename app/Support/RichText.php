<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Mise en forme légère des témoignages texte.
 *
 * Format stocké (compatible application mobile) :
 *   **gras**   *italique*   ***gras italique***   \*  = astérisque littéral
 * Les émojis sont conservés tels quels (Unicode, base utf8mb4).
 * Aucun HTML n'est jamais stocké : le texte est échappé avant conversion.
 */
class RichText
{
    private const STAR = "\u{E000}"; // caractère privé servant à protéger les \*

    public static function toHtml(?string $text): HtmlString
    {
        if ($text === null || $text === '') {
            return new HtmlString('');
        }

        $html = e(str_replace('\\*', self::STAR, $text));

        $html = preg_replace('/\*\*\*(?=\S)([^\n]+?)(?<=\S)\*\*\*/u', '<strong><em>$1</em></strong>', $html);
        $html = preg_replace('/\*\*(?=\S)([^\n]+?)(?<=\S)\*\*/u', '<strong>$1</strong>', $html);
        $html = preg_replace('/(?<!\*)\*(?=[^\s*])([^\n]*?)(?<=[^\s*])\*(?!\*)/u', '<em>$1</em>', $html);

        return new HtmlString(str_replace(self::STAR, '*', $html));
    }

    /** Texte sans marques de mise en forme (aperçus, recherches, notifications). */
    public static function plain(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        // Même analyse que l'affichage, puis retrait des balises produites.
        return html_entity_decode(strip_tags((string) self::toHtml($text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

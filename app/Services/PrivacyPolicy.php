<?php

namespace App\Services;

use App\Support\Locales;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Politique de confidentialité (docs/fonctionnalites/confidentialite-et-suppression.md).
 * Source unique : resources/legal/privacy.{fr,en}.md, rendue en HTML (Markdown GitHub, tableaux compris)
 * pour le site (/confidentialite, /privacy) et l'API (GET legal/privacy). Le texte n'est copié nulle part.
 */
final class PrivacyPolicy
{
    private const MONTHS_FR = [
        'janvier' => 1, 'février' => 2, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
        'juillet' => 7, 'août' => 8, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12, 'decembre' => 12,
    ];

    /** Langue prise en charge (fr | en), français par défaut. */
    public static function locale(?string $value): string
    {
        return Locales::normalize($value) ?? Locales::DEFAULT;
    }

    public static function path(string $locale): string
    {
        return resource_path('legal/privacy.' . self::locale($locale) . '.md');
    }

    /** Titre : premier titre « # » du fichier. */
    public static function title(string $locale): string
    {
        preg_match('/^#\s+(.+)$/mu', self::markdown($locale), $m);

        return trim($m[1] ?? 'My Testimony');
    }

    /**
     * Date de dernière mise à jour lue dans le texte (« Dernière mise à jour : 20 octobre 2026 »,
     * « Last updated: October 20, 2026 »), sinon date de modification du fichier.
     */
    public static function updatedAt(string $locale): Carbon
    {
        $markdown = self::markdown($locale);

        if (preg_match('/mise à jour\s*:\s*(\d{1,2})(?:er)?\s+(\p{L}+)\s+(\d{4})/iu', $markdown, $m)
            && ($month = self::MONTHS_FR[mb_strtolower($m[2])] ?? null)) {
            return Carbon::create((int) $m[3], $month, (int) $m[1])->startOfDay();
        }
        if (preg_match('/last updated\s*:\s*([A-Za-z]+ \d{1,2},? \d{4})/i', $markdown, $m)) {
            try {
                return Carbon::parse($m[1])->startOfDay();
            } catch (\Throwable) {
                // date illisible : date du fichier
            }
        }

        return Carbon::createFromTimestamp(File::lastModified(self::path($locale)));
    }

    /** Corps en HTML, sans le titre principal (affiché à part). Le HTML écrit dans le fichier est échappé. */
    public static function html(string $locale): string
    {
        $markdown = preg_replace('/\A\s*#\s+[^\n]*\n/u', '', self::markdown($locale), 1);

        return Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    /** Adresse publique de la page dans cette langue. */
    public static function url(string $locale): string
    {
        return self::locale($locale) === 'en' ? route('legal.privacy.en') : route('legal.privacy');
    }

    /** @return array{title: string, updated_at: string, html: string, url: string} */
    public static function payload(string $locale): array
    {
        return [
            'title'      => self::title($locale),
            'updated_at' => self::updatedAt($locale)->toDateString(),
            'html'       => self::html($locale),
            'url'        => self::url($locale),
        ];
    }

    private static function markdown(string $locale): string
    {
        return File::get(self::path($locale));
    }
}

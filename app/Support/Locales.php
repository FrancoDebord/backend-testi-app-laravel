<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Langues de l'interface et de l'API : français (par défaut) et anglais.
 * API : en-tête Accept-Language ; site : choix enregistré en session, sinon langue du compte.
 * Push et e-mails : langue du compte (users.locale). Voir docs/fonctionnalites/langues.md
 */
final class Locales
{
    public const SUPPORTED = ['fr', 'en'];

    public const DEFAULT = 'fr';

    /** Clé de session du choix fait sur le site (sélecteur de langue). */
    public const SESSION_KEY = 'locale';

    /** « en-US », « EN », « fr_BJ » → « en », « fr » ; null si la langue n'est pas prise en charge. */
    public static function normalize(?string $value): ?string
    {
        $code = strtolower(substr(trim((string) $value), 0, 2));

        return in_array($code, self::SUPPORTED, true) ? $code : null;
    }

    /** Première langue prise en charge d'un en-tête Accept-Language (poids q respectés), sinon le français. */
    public static function fromAcceptLanguage(?string $header): string
    {
        $candidates = [];
        foreach (explode(',', (string) $header) as $index => $part) {
            $pieces = explode(';', trim($part));
            $q = 1.0;
            foreach (array_slice($pieces, 1) as $param) {
                if (preg_match('/^\s*q\s*=\s*([0-9.]+)\s*$/i', $param, $m)) {
                    $q = (float) $m[1];
                }
            }
            if ($q > 0 && ($code = self::normalize($pieces[0]))) {
                // Poids décroissant, puis ordre d'apparition.
                $candidates[] = [$q, -$index, $code];
            }
        }
        if ($candidates === []) {
            return self::DEFAULT;
        }
        rsort($candidates);

        return $candidates[0][2];
    }

    /** Langue d'une requête : en-tête Accept-Language pour l'API ; session, compte, puis français pour le site. */
    public static function fromRequest(Request $request): string
    {
        if ($request->is('api/*')) {
            return self::fromAcceptLanguage($request->header('Accept-Language'));
        }
        $session = $request->hasSession() ? self::normalize($request->session()->get(self::SESSION_KEY)) : null;

        return $session ?? self::forUser($request->user());
    }

    /** Langue enregistrée d'un compte (push, e-mails), français par défaut. */
    public static function forUser(?User $user): string
    {
        return self::normalize($user?->locale) ?? self::DEFAULT;
    }

    /** Applique la langue à la traduction et aux dates (« il y a 2 heures » / « 2 hours ago »). */
    public static function apply(string $locale): void
    {
        $locale = self::normalize($locale) ?? self::DEFAULT;
        app()->setLocale($locale);
        Carbon::setLocale($locale);
    }

    /**
     * Exécute $callback dans une langue puis rétablit la langue courante (push envoyés en file,
     * notifications d'un autre membre).
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function using(?string $locale, callable $callback): mixed
    {
        $previous = app()->getLocale();
        self::apply($locale ?? self::DEFAULT);
        try {
            return $callback();
        } finally {
            self::apply($previous);
        }
    }

    /** Nombre entier lisible : « 1 245 » en français, « 1,245 » en anglais. */
    public static function number(int|float|string|null $value, int $decimals = 0): string
    {
        $value = (float) ($value ?? 0); // compteur pas encore chargé (null) : 0
        return app()->getLocale() === 'en'
            ? number_format($value, $decimals, '.', ',')
            : number_format($value, $decimals, ',', ' ');
    }

    /** Libellés du sélecteur (chaque langue écrite dans sa propre langue). */
    public static function labels(): array
    {
        return ['fr' => 'Français', 'en' => 'English'];
    }
}

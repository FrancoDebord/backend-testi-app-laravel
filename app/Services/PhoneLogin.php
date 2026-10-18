<?php

namespace App\Services;

use App\Models\User;
use App\Support\Countries;
use App\Support\PhoneNumber;

/**
 * Connexion par numéro de téléphone + mot de passe (API et site) : retrouve le compte dont le téléphone
 * de contact correspond au numéro saisi. Voir docs/fonctionnalites/telephone.md
 *
 * Le numéro est saisi en deux parties (indicatif ISO « bj » + numéro national tel que tapé) ou déjà au format
 * international (« +229… », avec ou sans indicatif). Recherche d'abord le numéro E.164 exact, puis, pour les
 * anciens comptes enregistrés hors E.164 (« 97 00 00 00 », « 0022997000000 »), la valeur brute puis une
 * comparaison chiffres seuls. Un numéro ambigu (plusieurs anciens comptes) ne désigne aucun compte.
 */
final class PhoneLogin
{
    /** Nombre minimal de chiffres pour la comparaison avec les anciens numéros. */
    private const MIN_LEGACY_DIGITS = 6;

    public static function findUser(?string $country, ?string $phone): ?User
    {
        $phone   = trim((string) $phone);
        $country = strtolower(trim((string) $country));
        if ($phone === '' || preg_match('/[^0-9 .\-()+\/]/', $phone)) {
            return null;
        }

        $e164 = self::e164($country, $phone);
        if ($e164 !== null && ($user = User::where('phone', $e164)->first())) {
            return $user;
        }

        // Ancien compte : valeur brute identique…
        if ($user = User::where('phone', $phone)->first()) {
            return $user;
        }

        // … ou mêmes chiffres (numéro national seul, indicatif avec 00, espaces, tirets…).
        $candidates = array_filter(array_unique([
            self::digits($phone),
            $e164 ? substr($e164, 1) : null,
            $e164 && ($dial = Countries::dialCode($country)) && str_starts_with($e164, '+' . $dial)
                ? substr($e164, strlen($dial) + 1) : null,
        ]), fn ($d) => is_string($d) && strlen($d) >= self::MIN_LEGACY_DIGITS);
        if ($candidates === []) {
            return null;
        }

        $matches = User::whereNotNull('phone')
            ->where(fn ($q) => $q->where('phone', 'not like', '+%')
                ->orWhere('phone', 'like', '% %')->orWhere('phone', 'like', '%-%')
                ->orWhere('phone', 'like', '%.%')->orWhere('phone', 'like', '%(%'))
            ->lazyById(500)
            ->filter(fn (User $u) => in_array(self::digits((string) $u->phone), $candidates, true))
            ->take(2)
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** Numéro E.164 correspondant à la saisie, ou null. */
    private static function e164(string $country, string $phone): ?string
    {
        if ($country !== '' && ($e164 = PhoneNumber::toE164($country, $phone))) {
            return $e164;
        }
        // Déjà international sans indicatif choisi (ou indicatif différent) : « +229 97 00 00 00 ».
        if (str_starts_with($phone, '+')) {
            $digits = self::digits($phone);

            return strlen($digits) >= 7 && strlen($digits) <= 15 ? '+' . $digits : null;
        }

        return null;
    }

    /** Chiffres seuls, sans le préfixe international « 00 ». */
    private static function digits(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value);

        return str_starts_with($digits, '00') ? substr($digits, 2) : $digits;
    }
}

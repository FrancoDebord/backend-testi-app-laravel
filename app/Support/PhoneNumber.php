<?php

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Numéro de téléphone de contact (vérification des comptes), saisi en deux parties :
 * pays de l'indicatif (code ISO) + numéro national. Enregistré au format international E.164 (« +22901970000 »).
 * Voir docs/fonctionnalites/telephone.md
 */
final class PhoneNumber
{
    /**
     * Pays où le 0 initial fait partie du numéro (Bénin et Côte d'Ivoire depuis le passage à 10 chiffres,
     * Italie pour les fixes). Ailleurs, un 0 initial est le préfixe national et est retiré (06… en France → +33 6…).
     */
    private const KEEP_LEADING_ZERO = ['bj', 'ci', 'it', 'sm', 'va'];

    /** Numéro international (« +22901970000 »), ou null s'il est invalide pour ce pays. */
    public static function toE164(?string $country, ?string $input): ?string
    {
        $country = strtolower(trim((string) $country));
        $dial    = Countries::dialCode($country);
        $input   = trim((string) $input);
        if (!$dial || $input === '' || preg_match('/[^0-9 .\-()+\/]/', $input)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $input);

        // Saisi au format international (+229…, 00229…) : il doit correspondre à l'indicatif choisi.
        if (str_starts_with($input, '+') || str_starts_with($digits, '00')) {
            $digits = str_starts_with($input, '+') ? $digits : substr($digits, 2);
            if (!str_starts_with($digits, $dial)) {
                return null;
            }
            $national = substr($digits, strlen($dial));
        } else {
            $national = $digits;
            if (!in_array($country, self::KEEP_LEADING_ZERO, true) && str_starts_with($national, '0')) {
                $national = substr($national, 1);
            }
        }

        $length = strlen($dial . $national);
        if (strlen($national) < 4 || $length < 7 || $length > 15) {
            return null;
        }

        return '+' . $dial . $national;
    }

    /** Partie nationale d'un numéro enregistré, pour le réafficher dans le formulaire. */
    public static function national(?string $e164, ?string $country): string
    {
        $dial = Countries::dialCode($country);
        $e164 = (string) $e164;

        return $dial && str_starts_with($e164, "+{$dial}") ? substr($e164, strlen($dial) + 1) : ltrim($e164, '+');
    }

    /** Affichage lisible : « +229 01970000 ». */
    public static function display(?string $e164, ?string $country): ?string
    {
        if (!$e164) {
            return null;
        }
        $dial = Countries::dialCode($country);

        return $dial && str_starts_with($e164, "+{$dial}") ? "+{$dial} " . self::national($e164, $country) : $e164;
    }

    /**
     * Règles des formulaires du site : champs « phone_country » (code ISO) et « phone » (numéro national).
     * $required : obligatoire (organisation) ; $ignore : compte à exclure du contrôle d'unicité (modification).
     */
    public static function rules(bool|string $required, ?User $ignore = null): array
    {
        $requiredRule = is_string($required) ? $required : ($required ? 'required' : 'nullable');

        return [
            'phone_country' => ['nullable', 'required_with:phone', Rule::in(Countries::codes())],
            'phone'         => [$requiredRule, 'nullable', 'string', 'max:30',
                function (string $attribute, mixed $value, Closure $fail) use ($ignore) {
                    if ($value === null || trim((string) $value) === '') {
                        return;
                    }
                    $e164 = self::toE164(request()->input('phone_country'), (string) $value);
                    if ($e164 === null) {
                        $fail('Numéro de téléphone invalide pour l\'indicatif choisi.');
                        return;
                    }
                    $taken = User::where('phone', $e164)->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->exists();
                    if ($taken) {
                        $fail('Ce numéro est déjà associé à un autre compte.');
                    }
                },
            ],
        ];
    }

    public const MESSAGES = [
        'phone.required'            => 'Le numéro de téléphone est obligatoire.',
        'phone.required_if'         => 'Le numéro de téléphone est obligatoire pour une organisation.',
        'phone_country.required_with' => 'Choisissez l\'indicatif du pays.',
        'phone_country.in'          => 'Indicatif inconnu.',
    ];
}

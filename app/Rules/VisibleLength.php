<?php

namespace App\Rules;

use App\Services\Mentions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Longueur maximale mesurée sur le texte affiché : un jeton de mention `@[Nom](user:id)`
 * compte comme `@Nom` (docs/fonctionnalites/mentions.md). L'application limite la saisie
 * sur le texte visible ; mesurer le texte brut refuserait un commentaire proche de la limite.
 * Le texte brut reste borné (jetons compris) par une marge pour MAX_PER_TEXT mentions.
 */
class VisibleLength implements ValidationRule
{
    /** Longueur d'un jeton au-delà du nom affiché : `[`, `](user:` + uuid + `)`. */
    private const TOKEN_OVERHEAD = 46;

    public function __construct(private readonly int $max, private readonly ?string $message = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            return;
        }
        $rawLimit = $this->max + Mentions::MAX_PER_TEXT * self::TOKEN_OVERHEAD;
        if (mb_strlen(Mentions::plain($value)) > $this->max || mb_strlen($value) > $rawLimit) {
            $fail($this->message ?? __('comments.text_too_long', ['max' => $this->max]));
        }
    }
}

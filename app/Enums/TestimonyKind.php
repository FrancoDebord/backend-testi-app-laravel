<?php

namespace App\Enums;

/**
 * Sorte de témoignage : standard, ou témoignage de conversion (un seul par compte personnel).
 * Voir docs/fonctionnalites/temoignage-de-conversion.md
 */
enum TestimonyKind: string
{
    case Standard   = 'standard';
    case Conversion = 'conversion';

    public function label(): string
    {
        return __('enums.testimony_kind.' . $this->value);
    }
}

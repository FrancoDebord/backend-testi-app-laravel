<?php

namespace App\Enums;

/** Type de compte : personne ou organisation (docs/fonctionnalites/comptes-organisation.md). */
enum AccountType: string
{
    case Individual   = 'individual';
    case Organization = 'organization';

    public function label(): string
    {
        return __('enums.account_type.' . $this->value);
    }
}

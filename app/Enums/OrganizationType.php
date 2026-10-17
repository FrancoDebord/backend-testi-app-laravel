<?php

namespace App\Enums;

/** Nature d'une organisation (docs/fonctionnalites/comptes-organisation.md). */
enum OrganizationType: string
{
    case Church      = 'church';
    case Ministry    = 'ministry';
    case Association = 'association';
    case Ngo         = 'ngo';
    case Media       = 'media';
    case Other       = 'other';

    public function label(): string
    {
        return __('enums.organization_type.' . $this->value);
    }
}

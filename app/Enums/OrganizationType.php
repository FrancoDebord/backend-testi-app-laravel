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
        return match($this) {
            self::Church      => 'Église',
            self::Ministry    => 'Ministère',
            self::Association => 'Association',
            self::Ngo         => 'ONG',
            self::Media       => 'Média chrétien',
            self::Other       => 'Autre',
        };
    }
}

<?php

namespace App\Enums;

enum LiveStatus: string
{
    case Preparing = 'preparing';
    case Live      = 'live';
    case Ended     = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Preparing => 'En préparation',
            self::Live      => 'En direct',
            self::Ended     => 'Terminé',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Preparing => 'badge-pending',
            self::Live      => 'badge-live',
            self::Ended     => 'badge-draft',
        };
    }
}

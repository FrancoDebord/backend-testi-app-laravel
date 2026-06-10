<?php

namespace App\Enums;

enum UserAccountStatus: string
{
    case Active    = 'active';
    case Suspended = 'suspended';
    case Banned    = 'banned';

    public function label(): string
    {
        return match($this) {
            self::Active    => 'Actif',
            self::Suspended => 'Suspendu',
            self::Banned    => 'Banni',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Active    => 'bg-green-100 text-green-700',
            self::Suspended => 'bg-yellow-100 text-yellow-700',
            self::Banned    => 'bg-red-100 text-red-700',
        };
    }
}

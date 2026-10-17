<?php

namespace App\Enums;

enum UserAccountStatus: string
{
    case Active    = 'active';
    case Suspended = 'suspended';
    case Banned    = 'banned';

    public function label(): string
    {
        return __('enums.user_account_status.' . $this->value);
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Active    => 'badge-active',
            self::Suspended => 'badge-suspended',
            self::Banned    => 'badge-banned',
        };
    }
}

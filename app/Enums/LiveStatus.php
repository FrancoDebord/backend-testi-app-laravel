<?php

namespace App\Enums;

enum LiveStatus: string
{
    case Preparing = 'preparing';
    case Live      = 'live';
    case Ended     = 'ended';

    public function label(): string
    {
        return __('enums.live_status.' . $this->value);
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

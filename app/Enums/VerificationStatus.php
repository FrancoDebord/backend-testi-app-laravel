<?php

namespace App\Enums;

/** Vérification d'un compte organisation par un administrateur. */
enum VerificationStatus: string
{
    case Pending  = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('enums.verification_status.' . $this->value);
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Pending  => 'badge-pending',
            self::Verified => 'badge-validated',
            self::Rejected => 'badge-rejected',
        };
    }
}

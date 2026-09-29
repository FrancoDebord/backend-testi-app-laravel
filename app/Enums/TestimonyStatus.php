<?php

namespace App\Enums;

enum TestimonyStatus: string
{
    case Draft    = 'draft';
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::Draft    => 'Brouillon',
            self::Pending  => 'En attente',
            self::Approved => 'Approuvé',
            self::Rejected => 'Rejeté',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Draft    => 'badge-draft',
            self::Pending  => 'badge-pending',
            self::Approved => 'badge-validated',
            self::Rejected => 'badge-rejected',
        };
    }
}

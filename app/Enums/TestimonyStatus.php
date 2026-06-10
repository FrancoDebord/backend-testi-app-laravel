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
            self::Draft    => 'bg-gray-100 text-gray-600',
            self::Pending  => 'bg-yellow-100 text-yellow-700',
            self::Approved => 'bg-green-100 text-green-700',
            self::Rejected => 'bg-red-100 text-red-700',
        };
    }
}

<?php

namespace App\Enums;

/**
 * Publication d'un événement. Brouillon : visible des seuls gestionnaires.
 * Annulé : reste visible (avec la mention), plus de participation possible.
 */
enum EventStatus: string
{
    case Draft     = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Brouillon',
            self::Published => 'Publié',
            self::Cancelled => 'Annulé',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft     => 'badge-draft',
            self::Published => 'badge-validated',
            self::Cancelled => 'badge-rejected',
        };
    }
}

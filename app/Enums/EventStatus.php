<?php

namespace App\Enums;

/**
 * Publication d'un événement. Brouillon : visible des seuls gestionnaires.
 * Reporté : date à confirmer (un report avec nouvelle date repasse en « Publié »,
 * l'ancienne date restant dans `original_starts_at`).
 * Annulé : reste visible (avec la mention), plus de participation possible.
 */
enum EventStatus: string
{
    case Draft     = 'draft';
    case Published = 'published';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Brouillon',
            self::Published => 'Publié',
            self::Postponed => 'Reporté — date à confirmer',
            self::Cancelled => 'Annulé',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft     => 'badge-draft',
            self::Published => 'badge-validated',
            self::Postponed => 'badge-orange',
            self::Cancelled => 'badge-rejected',
        };
    }
}

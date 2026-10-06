<?php

namespace App\Enums;

/** Nature d'un événement chrétien (docs/fonctionnalites/evenements.md). */
enum EventType: string
{
    case Crusade    = 'crusade';
    case Conference = 'conference';
    case Seminar    = 'seminar';
    case Camp       = 'camp';
    case Tour       = 'tour';
    case Worship    = 'worship';
    case Retreat    = 'retreat';
    case Other      = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Crusade    => "Croisade d'évangélisation",
            self::Conference => 'Conférence',
            self::Seminar    => 'Séminaire',
            self::Camp       => 'Camp',
            self::Tour       => 'Tournée',
            self::Worship    => 'Concert de louange',
            self::Retreat    => 'Retraite spirituelle',
            self::Other      => 'Autre événement',
        };
    }

    /** Icône Font Awesome (site). */
    public function icon(): string
    {
        return match ($this) {
            self::Crusade    => 'fa-bullhorn',
            self::Conference => 'fa-microphone',
            self::Seminar    => 'fa-chalkboard-user',
            self::Camp       => 'fa-campground',
            self::Tour       => 'fa-route',
            self::Worship    => 'fa-music',
            self::Retreat    => 'fa-dove',
            self::Other      => 'fa-calendar-days',
        };
    }

    /** @return array<string, string> valeur → libellé */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}

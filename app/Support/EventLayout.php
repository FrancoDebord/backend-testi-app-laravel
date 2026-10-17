<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Affichage de la liste des événements (/evenements) : cartes (par défaut) ou liste compacte
 * (une ligne par événement). Variante de FeedLayout avec son propre cookie, valable sans compte.
 * Voir docs/fonctionnalites/evenements.md
 */
final class EventLayout
{
    public const COOKIE  = 'event_layout';
    public const CARDS   = 'cards';
    public const COMPACT = 'compact';

    /** Libellés des deux affichages, dans la langue courante. */
    public static function labels(): array
    {
        return [
            self::CARDS   => __('events.layout.cards'),
            self::COMPACT => __('events.layout.compact'),
        ];
    }

    /** Durée de conservation du choix, en minutes (un an). */
    public const LIFETIME = 60 * 24 * 365;

    public static function current(?Request $request = null): string
    {
        return ($request ?? request())->cookie(self::COOKIE) === self::COMPACT ? self::COMPACT : self::CARDS;
    }

    public static function isCompact(?Request $request = null): bool
    {
        return self::current($request) === self::COMPACT;
    }
}

<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Affichage des listes de témoignages : grandes cartes (par défaut) ou liste compacte dépliable.
 * Choix mémorisé dans un cookie, donc valable aussi sans compte. Voir docs/fonctionnalites/affichage-et-lecture.md
 */
final class FeedLayout
{
    public const COOKIE  = 'feed_layout';
    public const CARDS   = 'cards';
    public const COMPACT = 'compact';

    public const LABELS = [
        self::CARDS   => 'Grandes cartes',
        self::COMPACT => 'Liste compacte',
    ];

    /** Durée de conservation du choix, en minutes (un an). */
    public const LIFETIME = 60 * 24 * 365;

    public static function current(?Request $request = null): string
    {
        $value = ($request ?? request())->cookie(self::COOKIE);

        return $value === self::COMPACT ? self::COMPACT : self::CARDS;
    }

    public static function isCompact(?Request $request = null): bool
    {
        return self::current($request) === self::COMPACT;
    }
}

<?php

namespace App\Enums;

enum ReactionType: string
{
    case Like    = 'like';
    case Love    = 'love';
    case Pray    = 'pray';
    case Amen    = 'amen';
    case Worship = 'worship';
    case Fire    = 'fire';

    public function emoji(): string
    {
        return match($this) {
            self::Like    => '👍',
            self::Love    => '❤️',
            self::Pray    => '🙏',
            self::Amen    => '🙌',
            self::Worship => '🙌',
            self::Fire    => '🔥',
        };
    }

    public function label(): string
    {
        return match($this) {
            self::Like    => 'J\'aime',
            self::Love    => 'Amour',
            self::Pray    => 'Prière',
            self::Amen    => 'Amen',
            self::Worship => 'Adorer',
            self::Fire    => 'Feu',
        };
    }

    /** Compteur du témoignage incrémenté par cette réaction (prières / j'aime). */
    public function counterField(): string
    {
        return match($this) {
            self::Pray, self::Amen, self::Worship => 'prayer_count',
            default                               => 'like_count',
        };
    }
}

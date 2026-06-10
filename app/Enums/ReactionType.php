<?php

namespace App\Enums;

enum ReactionType: string
{
    case Like  = 'like';
    case Love  = 'love';
    case Pray  = 'pray';
    case Amen  = 'amen';
    case Fire  = 'fire';

    public function emoji(): string
    {
        return match($this) {
            self::Like => '👍',
            self::Love => '❤️',
            self::Pray => '🙏',
            self::Amen => '🙌',
            self::Fire => '🔥',
        };
    }

    public function label(): string
    {
        return match($this) {
            self::Like => 'J\'aime',
            self::Love => 'Amour',
            self::Pray => 'Prière',
            self::Amen => 'Amen',
            self::Fire => 'Feu',
        };
    }
}

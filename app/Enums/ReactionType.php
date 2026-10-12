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
    // Réactions de l'application (sélecteur « J'aime ») : groupe « j'aime ».
    case Glory   = 'glory';
    case Strong  = 'strong';
    case Touched = 'touched';

    public function emoji(): string
    {
        return match($this) {
            self::Like    => '👍',
            self::Love    => '❤️',
            self::Pray    => '🙏',
            self::Amen    => '🙌',
            self::Worship => '🙌',
            self::Fire    => '🔥',
            self::Glory   => '✨',
            self::Strong  => '💪',
            self::Touched => '🥹',
        };
    }

    public function label(): string
    {
        return match($this) {
            self::Like    => 'J\'aime',
            self::Love    => 'J\'adore Dieu',
            self::Pray    => 'Prière',
            self::Amen    => 'Amen',
            self::Worship => 'Adorer Dieu',
            self::Fire    => 'Feu',
            self::Glory   => 'Gloire à Dieu !',
            self::Strong  => 'Inspirant',
            self::Touched => 'Touché',
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

    /**
     * Groupe de la réaction : une personne a au plus UNE réaction par groupe et par publication
     * (« appreciation » : like, love, fire ; « prayer » : pray, amen, worship).
     */
    public function group(): string
    {
        return $this->counterField() === 'prayer_count' ? 'prayer' : 'appreciation';
    }

    /** @return list<string> valeurs du groupe ('appreciation' | 'prayer'). */
    public static function valuesOfGroup(string $group): array
    {
        return array_values(array_map(
            fn (self $t) => $t->value,
            array_filter(self::cases(), fn (self $t) => $t->group() === $group),
        ));
    }
}

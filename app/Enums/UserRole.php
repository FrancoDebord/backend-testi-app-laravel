<?php

namespace App\Enums;

enum UserRole: string
{
    case Visiteur      = 'visiteur';
    case Utilisateur   = 'utilisateur';
    case Moderateur    = 'moderateur';
    case Administrateur = 'administrateur';

    public function label(): string
    {
        return __('enums.user_role.' . $this->value);
    }

    public function canPublish(): bool
    {
        return in_array($this, [self::Utilisateur, self::Moderateur, self::Administrateur]);
    }

    public function canModerate(): bool
    {
        return in_array($this, [self::Moderateur, self::Administrateur]);
    }

    public function isAdmin(): bool
    {
        return $this === self::Administrateur;
    }
}

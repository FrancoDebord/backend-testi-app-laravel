<?php

namespace App\Enums;

enum TestimonyVisibility: string
{
    case Public    = 'public';
    case Private   = 'private';
    case Followers = 'followers';

    public function label(): string
    {
        return match($this) {
            self::Public    => 'Public',
            self::Private   => 'Privé',
            self::Followers => 'Abonnés',
        };
    }
}

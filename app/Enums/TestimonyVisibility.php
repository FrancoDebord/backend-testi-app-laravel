<?php

namespace App\Enums;

enum TestimonyVisibility: string
{
    case Public    = 'public';
    case Private   = 'private';
    case Followers = 'followers';

    public function label(): string
    {
        return __('enums.testimony_visibility.' . $this->value);
    }
}

<?php

namespace App\Services;

use RuntimeException;

/** Action d'abonnement refusée : message affichable et code HTTP. */
class FollowException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 422)
    {
        parent::__construct($message, $status);
    }

    public function status(): int
    {
        return $this->status;
    }
}

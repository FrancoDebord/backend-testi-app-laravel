<?php

namespace App\Services;

use RuntimeException;

/** Action refusée sur un événement : message affichable tel quel et code HTTP associé. */
class EventActionException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 400)
    {
        parent::__construct($message, $status);
    }

    public function status(): int
    {
        return $this->status;
    }
}

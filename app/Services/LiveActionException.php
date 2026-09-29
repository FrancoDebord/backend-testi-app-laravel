<?php

namespace App\Services;

use App\Models\LiveSession;
use RuntimeException;

/** Action refusée sur un direct : message affichable tel quel et code HTTP associé. */
class LiveActionException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 400, public readonly ?LiveSession $live = null)
    {
        parent::__construct($message, $status);
    }

    public function status(): int
    {
        return $this->status;
    }
}

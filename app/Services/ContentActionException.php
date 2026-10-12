<?php

namespace App\Services;

use RuntimeException;

/**
 * Action refusée (ContentInteractions, TestimonyCreator) : message affichable tel quel, code HTTP associé
 * et, au besoin, erreurs par champ au format de la validation.
 */
class ContentActionException extends RuntimeException
{
    /** @param array<string, string[]>|null $errors */
    public function __construct(string $message, private readonly int $status = 400, private readonly ?array $errors = null)
    {
        parent::__construct($message, $status);
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, string[]>|null */
    public function errors(): ?array
    {
        return $this->errors;
    }
}

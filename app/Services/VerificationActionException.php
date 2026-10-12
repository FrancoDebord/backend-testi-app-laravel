<?php

namespace App\Services;

/** Action de vérification de compte impossible (message affiché tel quel, code HTTP). */
class VerificationActionException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}

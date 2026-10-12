<?php

namespace App\Services;

use RuntimeException;

/** Connexion Google refusée : message pour l'utilisateur, code HTTP (401 par défaut). */
class GoogleSignInException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 401)
    {
        parent::__construct($message);
    }
}

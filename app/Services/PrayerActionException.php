<?php

namespace App\Services;

use RuntimeException;

/**
 * Action refusée sur une requête ou une session de prière, avec le code HTTP à renvoyer
 * (403 droits · 404 introuvable · 409 état · 410 terminé · 422 données · 429 trop rapide · 503 vidéo).
 */
class PrayerActionException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}

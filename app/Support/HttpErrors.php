<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Erreurs HTTP du site et de l'API (docs/fonctionnalites/pages-erreur.md) : code, titre et texte
 * traduits, et message précis transmis par abort(…, __('…')) quand il est destiné au public.
 */
class HttpErrors
{
    /** Messages techniques du framework (anglais, chemins…) : jamais montrés, remplacés par le texte traduit. */
    private const FRAMEWORK_MESSAGES = [
        'This action is unauthorized.',
        'CSRF token mismatch.',
        'Too Many Attempts.',
        'Service Unavailable',
        'Not found.',
        'Unauthenticated.',
    ];

    public static function status(Throwable $e): int
    {
        return $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
    }

    /** Titre et texte de la page ou du message pour un code : clé exacte, sinon famille 4xx / 5xx. */
    public static function copy(int $status): array
    {
        $key = trans()->has("errors.$status") ? (string) $status : ($status >= 500 ? '5xx' : '4xx');

        return [
            'title' => __("errors.$key.title"),
            'text'  => __("errors.$key.text"),
        ];
    }

    /**
     * Message précis d'un abort() de l'application (« Événement introuvable. »), sinon null.
     * Jamais pour une erreur 5xx : le message d'une exception interne peut contenir du SQL ou des chemins.
     */
    public static function publicMessage(Throwable $e): ?string
    {
        $status = self::status($e);
        if ($status >= 500 || !$e instanceof HttpExceptionInterface) {
            return null;
        }

        $previous = $e->getPrevious();
        $message = trim($e->getMessage());
        // Modèle introuvable, jeton CSRF… : message du framework. Une règle d'autorisation (Gate, policy) garde le sien.
        if ($previous !== null && !$previous instanceof AuthorizationException) {
            return null;
        }
        if ($message === ''
            || in_array($message, self::FRAMEWORK_MESSAGES, true)
            || str_starts_with($message, 'The route ')
            || str_contains($message, 'method is not supported')) {
            return null;
        }

        return $message;
    }

    /** Message d'une réponse JSON de l'API : précis s'il existe, sinon le texte du code. */
    public static function apiMessage(Throwable $e): string
    {
        $status = self::status($e);

        return self::publicMessage($e) ?? match ($status) {
            401 => __('api.unauthenticated'),
            403 => __('api.forbidden'),
            404 => __('api.not_found'),
            default => self::copy($status)['title'],
        };
    }
}

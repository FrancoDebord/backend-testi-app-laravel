<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, string $message = '', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => $message,
        ], $status);
    }

    protected function error(string $message = '', int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data'    => null,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    protected function paginated(mixed $data, mixed $meta = null, string $message = ''): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $data,
            'meta'    => $meta,
            'message' => $message,
        ]);
    }

    protected function created(mixed $data = null, string $message = 'Créé avec succès'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function unauthorized(string $message = 'Non autorisé'): JsonResponse
    {
        return $this->error($message, 401);
    }

    protected function forbidden(string $message = 'Accès refusé'): JsonResponse
    {
        return $this->error($message, 403);
    }

    protected function notFound(string $message = 'Ressource introuvable'): JsonResponse
    {
        return $this->error($message, 404);
    }

    protected function validationError(mixed $errors, string $message = 'Données invalides'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data'    => null,
            'message' => $message,
            'errors'  => $errors,
        ], 422);
    }
}

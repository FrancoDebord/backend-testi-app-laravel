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

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message ?? __('api.created'), 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function unauthorized(?string $message = null): JsonResponse
    {
        return $this->error($message ?? __('api.unauthorized'), 401);
    }

    protected function forbidden(?string $message = null): JsonResponse
    {
        return $this->error($message ?? __('api.forbidden'), 403);
    }

    protected function notFound(?string $message = null): JsonResponse
    {
        return $this->error($message ?? __('api.not_found'), 404);
    }

    protected function validationError(mixed $errors, ?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data'    => null,
            'message' => $message ?? __('api.invalid_data'),
            'errors'  => $errors,
        ], 422);
    }
}

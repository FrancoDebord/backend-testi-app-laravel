<?php

namespace App\Http\Requests\Api;

/**
 * POST /videos/upload-init : mêmes champs et mêmes règles que la création d'un témoignage
 * (docs/fonctionnalites/videos-bunny-stream.md). Le type vaut « video » s'il n'est pas envoyé.
 */
class InitVideoUploadRequest extends StoreTestimonyRequest
{
    protected function prepareForValidation(): void
    {
        if (!$this->filled('type')) {
            $this->merge(['type' => 'video']);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LinkPreviews;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/link-preview?url= : aperçu d'un lien collé dans un texte (application).
 * `data` : { url, title, description, image, siteName, host } ou null sans aperçu.
 * Voir docs/fonctionnalites/liens-et-apercus.md
 */
class LinkPreviewController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['url' => 'required|string|max:2048']);

        return $this->success(LinkPreviews::get($data['url']));
    }
}

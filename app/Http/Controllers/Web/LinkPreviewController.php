<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\LinkPreviews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /liens/apercu?url= : aperçu d'un lien (components/link-preview, resources/js/link-previews.js).
 * Ouvert sans connexion (pages publiques), limité à 30 par minute. `data` vaut null sans aperçu.
 * Voir docs/fonctionnalites/liens-et-apercus.md
 */
class LinkPreviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['url' => 'required|string|max:2048']);

        return response()->json(['data' => LinkPreviews::get($data['url'])]);
    }
}

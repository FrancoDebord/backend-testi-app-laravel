<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Mentions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Autocomplétion des mentions « @ » du site (components/mention-input) : même recherche que
 * l'API GET /api/v1/users/mention-search, avec la session du site. Jamais d'e-mail ni de téléphone.
 * Voir docs/fonctionnalites/mentions.md
 */
class MentionSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q'            => 'nullable|string|max:100',
            'limit'        => 'nullable|integer|min:1|max:20',
            'testimony_id' => 'nullable|uuid',
        ]);

        return response()->json([
            'data' => Mentions::search($request->user(), (string) ($data['q'] ?? ''), (int) ($data['limit'] ?? Mentions::SEARCH_LIMIT), $data['testimony_id'] ?? null),
        ]);
    }
}

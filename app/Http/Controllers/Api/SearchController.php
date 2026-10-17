<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SearchSuggestions;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Autocomplétion des barres de recherche (public, invités acceptés) :
 *   GET  /api/v1/search/suggest?q=&scope=all|testimonies|users|events|prayer|bible&limit=
 *   POST /api/v1/search/log { q } : recherche validée, enregistrée sans identifiant (recherches populaires).
 * Voir docs/fonctionnalites/recherche.md
 */
class SearchController extends Controller
{
    use ApiResponse;

    public function suggest(Request $request, SearchSuggestions $search): JsonResponse
    {
        $data = $request->validate([
            'q'     => 'nullable|string|max:200',
            'scope' => 'nullable|string|in:' . implode(',', SearchSuggestions::SCOPES),
            'limit' => 'nullable|integer|min:1|max:' . SearchSuggestions::MAX_LIMIT,
        ]);

        return $this->success($search->suggest(
            (string) ($data['q'] ?? ''),
            $data['scope'] ?? 'all',
            (int) ($data['limit'] ?? SearchSuggestions::DEFAULT_LIMIT),
        ))->header('Cache-Control', 'public, max-age=60')->setVary('Accept-Language', false);
    }

    public function log(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => 'required|string|max:200']);
        SearchSuggestions::record($data['q'], $request->ip());

        return $this->success(null, __('search.api.recorded'));
    }
}

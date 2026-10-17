<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\AccountSuggestions;
use App\Services\CommunityDirectory;
use App\Services\Mentions;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/community?tab=organizations|people&q=&page= : comptes à suivre (« is_following » si connecté).
 * GET /api/v1/users/suggestions : « Comptes à suivre » classés. GET /api/v1/users/mention-search : mentions « @ ».
 * Voir docs/fonctionnalites/abonnements.md et docs/fonctionnalites/mentions.md
 */
class CommunityController extends Controller
{
    use ApiResponse;

    public function index(Request $request, CommunityDirectory $directory): JsonResponse
    {
        $request->validate([
            'tab' => 'nullable|in:' . implode(',', array_keys(CommunityDirectory::tabs())),
            'q'   => 'nullable|string|max:100',
        ]);

        $accounts = $directory->search($request->query('tab', 'all'), $request->query('q'), $request->user('sanctum'));

        return $this->paginated(UserResource::collection($accounts->items()), [
            'current_page' => $accounts->currentPage(),
            'last_page'    => $accounts->lastPage(),
            'total'        => $accounts->total(),
        ]);
    }

    /**
     * GET /api/v1/users/suggestions?limit=10&type=all|organizations|people : « Comptes à suivre ».
     * Connecté ou non (invité : comptes vérifiés et les plus actifs). Voir App\Services\AccountSuggestions.
     */
    public function suggestions(Request $request, AccountSuggestions $suggestions): JsonResponse
    {
        $data = $request->validate([
            'limit' => 'nullable|integer|min:1|max:30',
            'type'  => 'nullable|in:' . implode(',', AccountSuggestions::TYPES),
        ]);

        $list = $suggestions->for($request->user('sanctum'), (int) ($data['limit'] ?? 10), $data['type'] ?? 'all');

        return $this->success($list->map(fn ($s) => AccountSuggestions::toArray($s['user'], $s['reason']))->values());
    }

    /**
     * GET /api/v1/users/mention-search?q=&limit=8&testimony_id= : comptes à mentionner après « @ » (connecté).
     * Voir docs/fonctionnalites/mentions.md
     */
    public function mentionSearch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q'            => 'nullable|string|max:100',
            'limit'        => 'nullable|integer|min:1|max:20',
            'testimony_id' => 'nullable|uuid',
        ]);

        return $this->success(Mentions::search($request->user(), (string) ($data['q'] ?? ''), (int) ($data['limit'] ?? Mentions::SEARCH_LIMIT), $data['testimony_id'] ?? null));
    }
}

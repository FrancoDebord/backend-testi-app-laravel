<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\CommunityDirectory;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/community?tab=organizations|people&q=&page= : comptes à suivre (« is_following » si connecté).
 * Voir docs/fonctionnalites/abonnements.md
 */
class CommunityController extends Controller
{
    use ApiResponse;

    public function index(Request $request, CommunityDirectory $directory): JsonResponse
    {
        $request->validate([
            'tab' => 'nullable|in:' . implode(',', array_keys(CommunityDirectory::TABS)),
            'q'   => 'nullable|string|max:100',
        ]);

        $accounts = $directory->search($request->query('tab', 'organizations'), $request->query('q'), $request->user('sanctum'));

        return $this->paginated(UserResource::collection($accounts->items()), [
            'current_page' => $accounts->currentPage(),
            'last_page'    => $accounts->lastPage(),
            'total'        => $accounts->total(),
        ]);
    }
}

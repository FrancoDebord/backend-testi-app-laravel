<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ReactionPeople;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET {publication}/{id}/reactions/people?type=all|appreciation|prayer|like|pray…&page= :
 * effectif de chaque réaction et personnes qui ont réagi. Voir docs/fonctionnalites/qui-a-reagi.md
 */
class ReactionPeopleController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, ReactionPeople $people, string $id): JsonResponse
    {
        $kind = $request->route('kind');
        $filter = $request->query('type');
        if (!is_string($filter ?? '') || !ReactionPeople::isFilter($filter)) {
            return $this->error(__('reactions.people.invalid_type'), 422);
        }

        $subject = $people->find($kind, $id, auth('sanctum')->user());
        if (!$subject) return $this->notFound();

        $page = $people->people($kind, $subject, $filter, (int) $request->query('page', 1));

        return $this->paginated([
            'summary' => $people->summary($kind, $subject),
            'people'  => collect($page->items())->map(fn (array $row) => [
                'id'          => $row['user']->id,
                'displayName' => $row['user']->display_name,
                'initials'    => $row['user']->initials,
                'avatarUrl'   => $row['user']->avatar_url,
                'isVerified'  => (bool) $row['user']->isVerified(),
                ...User::approvalFieldsFor($row['user']),
                'reactions'   => array_map(fn ($t) => $t->value, $row['types']),
                'prayerTimes' => $row['prayerTimes'], // nombre de fois que la personne a prié (docs/fonctionnalites/prier-plusieurs-fois.md)
                'reactedAt'   => $row['reactedAt'],
            ])->values(),
        ], [
            'currentPage' => $page->currentPage(),
            'lastPage'    => $page->lastPage(),
            'perPage'     => $page->perPage(),
            'total'       => $page->total(),
        ]);
    }
}

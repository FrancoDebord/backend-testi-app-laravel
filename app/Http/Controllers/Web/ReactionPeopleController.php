<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ReactionPeople;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Popup « Qui a réagi ? » : onglets par réaction avec leur effectif, puis les personnes.
 * GET /reactions/{kind}/{id}?type=&page= renvoie le contenu du popup (resources/js/app.js le charge).
 * Voir docs/fonctionnalites/qui-a-reagi.md
 */
class ReactionPeopleController extends Controller
{
    public function __invoke(Request $request, ReactionPeople $people, string $kind, string $id): View
    {
        $filter = $request->query('type');
        abort_unless(is_string($filter ?? '') && ReactionPeople::isFilter($filter), 404);
        $filter = $filter === 'all' ? null : $filter;

        $subject = $people->find($kind, $id, $request->user());
        abort_unless($subject, 404);

        // Onglet « Commentaires » : qui a commenté, et quoi.
        $isComments = $filter === 'comments';
        $page = $isComments
            ? $people->comments($kind, $subject, (int) $request->query('page', 1))
            : $people->people($kind, $subject, $filter, (int) $request->query('page', 1));
        $rows = $isComments ? 'reactions.partials.comment-rows' : 'reactions.partials.people-rows';

        // Page suivante : seulement les lignes, ajoutées à la liste déjà affichée.
        return view($page->currentPage() > 1 ? $rows : 'reactions.people', [
            'rows'    => $rows,
            // Requête de prière : ses encouragements.
            'hasComments'   => $kind !== 'prayer_session', // pas de commentaires sur une session de prière
            'commentsLabel' => __($kind === 'prayer_request' ? 'reactions.people.encouragements' : 'reactions.people.comments'),
            'summary' => $people->summary($kind, $subject),
            'page'    => $page,
            'filter'  => $filter,
            'url'     => fn (?string $type, int $p = 1) => route('reactions.people', array_filter(['kind' => $kind, 'id' => $id, 'type' => $type, 'page' => $p > 1 ? $p : null])),
        ]);
    }
}

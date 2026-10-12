<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LiveSession;
use App\Services\FeedInserts;
use App\Services\Recommendations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * « Mon fil », comme l'accueil mais personnalisé : tous les témoignages publiés, les comptes suivis
 * d'abord (leurs témoignages et partages récents), puis le classement « Pour vous »
 * (App\Services\Recommendations::personalFeed, partagé avec l'API mobile) ; directs en cours en tête ;
 * événements, sessions de prière, requêtes de prière et paroles prophétiques publiques insérés
 * dans la liste (App\Services\FeedInserts, comptes suivis d'abord).
 */
class FeedController extends Controller
{
    private const PER_PAGE = 24;

    public function __construct(
        private readonly Recommendations $recommendations,
        private readonly FeedInserts $feedInserts,
    ) {}

    public function personal(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $page = max(1, (int) $request->query('page', 1));
        [$items, $reasons, $reposts] = $this->recommendations->personalFeed($user, $page, self::PER_PAGE);
        $items->withPath(route('feed.personal'));

        // Insérés entre les témoignages (videos/partials/cards). Liste complète à chaque page :
        // l'élément affiché dépend de la position dans le fil entier.
        $inserts = $this->feedInserts->mixed($user);

        // « Afficher plus » : seules les cartes suivantes sont renvoyées (resources/js/videos.js).
        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('videos.partials.cards', [
                    'items' => $items, 'routeName' => 'testimonies.show', 'encourage' => true, 'reasons' => $reasons,
                    'reposts' => $reposts, 'inserts' => $inserts,
                ])->render(),
                'next' => $items->nextPageUrl(),
            ]);
        }

        $followsSomeone = $user->following()->exists();
        $lives = LiveSession::with('host')->onAir()->latest('started_at')->limit(6)->get()
            ->filter(fn ($l) => $l->isVisibleTo($user));

        // « Près de chez vous · Cotonou » (docs/fonctionnalites/localisation.md).
        $place = $this->recommendations->placeMeta($user);

        return view('feed.personal', compact('items', 'reasons', 'reposts', 'followsSomeone', 'inserts', 'lives', 'place'));
    }
}

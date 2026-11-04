<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\GlobalSearch;
use App\Services\SearchSuggestions;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GET /recherche ?q=&onglet=temoignages|comptes|prieres|evenements|sessions : résultats de la barre de recherche de
 * l'en-tête dans toute la base, un onglet par rubrique avec son nombre de résultats. Sans onglet choisi, la première
 * rubrique qui a des résultats s'ouvre. docs/fonctionnalites/recherche.md
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearch $search): View
    {
        $q      = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $viewer = $request->user();
        $ready  = mb_strlen($q) >= GlobalSearch::MIN_LENGTH;
        $counts = $ready ? $search->counts($q, $viewer) : array_fill_keys(GlobalSearch::TABS, 0);

        $tab = (string) $request->query('onglet');
        if (!in_array($tab, GlobalSearch::TABS, true)) {
            $tab = collect($counts)->filter()->keys()->first() ?? GlobalSearch::TABS[0];
        }

        // Recherches populaires de l'autocomplétion (anonyme), première page seulement.
        if ($ready && (int) $request->query('page', 1) <= 1) {
            SearchSuggestions::record($q, $request->ip());
        }

        return view('search.index', [
            'q'       => $q,
            'ready'   => $ready,
            'tab'     => $tab,
            'counts'  => $counts,
            'results' => $ready && $counts[$tab] > 0
                ? $search->query($tab, $q, $viewer)->paginate($tab === 'comptes' ? 24 : 12)->withQueryString()->appends('onglet', $tab)
                : null,
        ]);
    }
}

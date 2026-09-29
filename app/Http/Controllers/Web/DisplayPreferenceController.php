<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\FeedLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Choix de l'affichage des listes (grandes cartes / liste compacte), mémorisé dans un cookie.
 * Voir docs/fonctionnalites/affichage-et-lecture.md
 */
class DisplayPreferenceController extends Controller
{
    public function layout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'layout' => ['required', Rule::in(array_keys(FeedLayout::LABELS))],
        ]);

        return redirect()->back(fallback: route('home'))
            ->withCookie(cookie(FeedLayout::COOKIE, $data['layout'], FeedLayout::LIFETIME));
    }
}

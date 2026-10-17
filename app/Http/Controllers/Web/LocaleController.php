<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sélecteur de langue du site (docs/fonctionnalites/langues.md) : choix gardé en session ; pour un
 * membre connecté, aussi dans users.locale (langue de ses push et e-mails, comme PUT /me dans l'app).
 */
class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', 'string', Rule::in(Locales::SUPPORTED)]]);

        $request->session()->put(Locales::SESSION_KEY, $data['locale']);
        // La personne réellement connectée, jamais un compte administré au nom duquel elle agit.
        if ($request->user() && ($user = \App\Services\AccountDelegation::actor($request))) {
            $user->forceFill(['locale' => $data['locale']])->save();
        }
        Locales::apply($data['locale']);

        return back()->with('status', __('layout.language.saved'));
    }
}

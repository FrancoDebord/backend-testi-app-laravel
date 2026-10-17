<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountDelegation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Site : choisir le compte au nom duquel on agit (le sien ou un compte administré).
 * Voir docs/fonctionnalites/administrateurs-de-compte.md
 */
class AccountSwitchController extends Controller
{
    public function switch(Request $request, string $id): RedirectResponse
    {
        $actor = AccountDelegation::actor($request);

        if ($actor->id === $id) {
            $request->session()->forget(AccountDelegation::SESSION_KEY);

            return redirect()->route('home')->with('success', __('account.switch.back'));
        }

        $account = User::find($id);
        abort_if(!$account || !AccountDelegation::canActFor($actor, $account), 403, __('account.switch.not_manager'));
        $request->session()->put(AccountDelegation::SESSION_KEY, $account->id);

        return redirect()->route('home')->with('success', __('account.switch.acting_as', ['name' => $account->display_name]));
    }
}

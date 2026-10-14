<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountApproval;
use App\Services\VerificationActionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Fiche utilisateur (back-office) : attribuer ou retirer le badge « Approuvé » (motif obligatoire,
 * journal d'audit, notification). Voir docs/fonctionnalites/badge-approuve.md
 */
class AccountApprovalController extends Controller
{
    public function __construct(private readonly AccountApproval $approval) {}

    public function grant(Request $request, string $id): RedirectResponse
    {
        return $this->act($request, $id, fn (User $u, string $r) => $this->approval->grant($u, Auth::user(), $r), 'Badge « Approuvé » attribué.');
    }

    public function revoke(Request $request, string $id): RedirectResponse
    {
        return $this->act($request, $id, fn (User $u, string $r) => $this->approval->revoke($u, Auth::user(), $r), 'Badge « Approuvé » retiré.');
    }

    private function act(Request $request, string $id, callable $action, string $message): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']], [
            'reason.required' => 'Indiquez le motif.',
            'reason.min'      => 'Indiquez le motif.',
        ]);
        $user = User::findOrFail($id);
        Gate::authorize('manageApproval', $user);

        try {
            $action($user, $data['reason']);
        } catch (VerificationActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }
}

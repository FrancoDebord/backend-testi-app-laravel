<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use App\Services\AccountDeletion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Administration : demandes de suppression faites sans connexion. Liste visible des administrateurs ;
 * suppression du compte et classement réservés aux super-administrateurs.
 * Voir docs/fonctionnalites/confidentialite-et-suppression.md
 */
class AccountDeletionRequestController extends Controller
{
    public function index(): View
    {
        return view('admin.deletion-requests', [
            'requests' => AccountDeletionRequest::with('user', 'handler')
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->latest()
                ->paginate(30),
        ]);
    }

    public function deleteAccount(Request $request, string $id, AccountDeletion $deletion): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403, __('privacy.admin.super_admin_only'));
        $deletionRequest = AccountDeletionRequest::with('user')->findOrFail($id);
        $user = $deletionRequest->user;
        // Suppression partielle : les données sont retirées à la main, puis la demande est marquée traitée.
        abort_unless($deletionRequest->status === AccountDeletionRequest::STATUS_PENDING && $user && !$deletionRequest->isPartial(), 404);

        if (!AccountDeletion::allowed($user)) {
            return back()->with('error', __('privacy.deletion.super_admin_forbidden'));
        }

        $deletion->delete($user, $request->user());
        // La demande est passée « traitée » par AccountDeletion ; on garde qui l'a traitée.
        $deletionRequest->forceFill(['status' => AccountDeletionRequest::STATUS_DONE, 'handled_by' => $request->user()->id, 'handled_at' => now()])->save();

        return back()->with('success', __('privacy.admin.deleted'));
    }

    /** Suppression partielle faite : demande marquée « Traitée ». */
    public function markDone(Request $request, string $id): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403, __('privacy.admin.super_admin_only'));
        $deletionRequest = AccountDeletionRequest::pending()->findOrFail($id);
        $deletionRequest->forceFill(['status' => AccountDeletionRequest::STATUS_DONE, 'handled_by' => $request->user()->id, 'handled_at' => now()])->save();

        return back()->with('success', __('privacy.admin.marked_done'));
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403, __('privacy.admin.super_admin_only'));
        $deletionRequest = AccountDeletionRequest::pending()->findOrFail($id);
        $deletionRequest->forceFill(['status' => AccountDeletionRequest::STATUS_REJECTED, 'handled_by' => $request->user()->id, 'handled_at' => now()])->save();

        return back()->with('success', __('privacy.admin.rejected'));
    }
}

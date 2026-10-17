<?php

namespace App\Http\Controllers\Web;

use App\Enums\PersonalVerificationRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Services\PersonalVerification;
use App\Services\VerificationActionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Vérification des comptes personnels sur le site : demande (profil → « Faire vérifier
 * mon compte ») et file d'examen des administrateurs.
 * Voir docs/fonctionnalites/verification-des-comptes.md
 */
class AccountVerificationController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $user = Auth::user();
        if ($user->isOrganization()) {
            return redirect()->route('profile.edit')
                ->with('status', __('verification.block.organization'));
        }

        return view('profile.verification', [
            'user'   => $user,
            'state'  => PersonalVerification::state($user),
            'roles'  => PersonalVerificationRole::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($reason = PersonalVerification::blockReason($user)) {
            return back()->with('error', $reason);
        }
        $data = $request->validate(PersonalVerification::rules(), PersonalVerification::messages());

        try {
            PersonalVerification::submit($user, $data, $request->file('documents', []));
        } catch (VerificationActionException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('profile.verification')
            ->with('success', __('verification.flash.submitted'));
    }

    // ── Administration ───────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('status'), VerificationRequest::statusLabels()) || $request->query('status') === 'all'
            ? $request->query('status')
            : VerificationRequest::PENDING;

        $query = VerificationRequest::with('user');
        if ($tab !== 'all') {
            $query->where('status', $tab);
        }
        $tab === VerificationRequest::PENDING ? $query->oldest() : $query->latest('updated_at');

        $requests     = $query->paginate(20)->withQueryString();
        $pendingCount = VerificationRequest::pending()->count();

        return view('admin.verifications.index', compact('requests', 'tab', 'pendingCount'));
    }

    public function adminShow(string $id): View
    {
        $verification = VerificationRequest::with(['user', 'reviewer'])->findOrFail($id);
        $history      = VerificationRequest::where('user_id', $verification->user_id)
            ->whereKeyNot($verification->id)->latest()->limit(10)->get();

        return view('admin.verifications.show', compact('verification', 'history'));
    }

    /** Pièce justificative : servie uniquement à un administrateur connecté, jamais mise en cache. */
    public function document(string $id, int $index)
    {
        return PersonalVerification::documentResponse(VerificationRequest::findOrFail($id), $index);
    }

    public function approve(string $id): RedirectResponse
    {
        $verification = VerificationRequest::with('user')->findOrFail($id);
        try {
            PersonalVerification::approve($verification, Auth::user());
        } catch (VerificationActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('verification.flash.approved'));
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']],
            ['reason.required' => __('verification.reject_reason_required'), 'reason.min' => __('verification.reject_reason_required')]);
        $verification = VerificationRequest::with('user')->findOrFail($id);
        try {
            PersonalVerification::reject($verification, Auth::user(), $request->input('reason'));
        } catch (VerificationActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('verification.flash.rejected'));
    }

    public function revoke(Request $request, string $id): RedirectResponse
    {
        $request->validate(['revoke_reason' => ['required', 'string', 'min:3', 'max:255']],
            ['revoke_reason.required' => __('verification.approval.revoke_reason_required'), 'revoke_reason.min' => __('verification.approval.revoke_reason_required')]);
        $user = User::findOrFail($id);
        try {
            PersonalVerification::revoke($user, Auth::user(), $request->input('revoke_reason'));
        } catch (VerificationActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('verification.flash.revoked'));
    }
}

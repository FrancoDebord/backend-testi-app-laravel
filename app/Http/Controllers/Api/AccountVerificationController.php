<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Services\PersonalVerification;
use App\Services\VerificationActionException;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vérification des comptes personnels : demande (personne connectée) et examen
 * (administrateurs). Voir docs/fonctionnalites/verification-des-comptes.md
 */
class AccountVerificationController extends Controller
{
    use ApiResponse;

    /** GET /users/me/verification : état de la vérification de mon compte. */
    public function show(Request $request): JsonResponse
    {
        return $this->success(PersonalVerification::state($request->user()));
    }

    /** POST /users/me/verification (multipart) : envoyer une demande. */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($reason = PersonalVerification::blockReason($user)) {
            return $this->error($reason, 422);
        }
        $data = $request->validate(PersonalVerification::rules(), PersonalVerification::messages());

        try {
            PersonalVerification::submit($user, $data, $request->file('documents', []));
        } catch (VerificationActionException $e) {
            return $this->error($e->getMessage(), $e->status);
        }

        return $this->success(PersonalVerification::state($user->fresh()),
            __('verification.api.submitted'), 201);
    }

    // ── Administration ───────────────────────────────────────────────────────

    /** GET /admin/verification-requests?status=pending|approved|rejected|revoked|all */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', VerificationRequest::PENDING);
        $query  = VerificationRequest::with('user');
        if ($status !== 'all' && array_key_exists($status, VerificationRequest::statusLabels())) {
            $query->where('status', $status);
        }
        $page = $query->orderBy($status === VerificationRequest::PENDING ? 'created_at' : 'updated_at', $status === VerificationRequest::PENDING ? 'asc' : 'desc')
            ->paginate(min(50, max(1, (int) $request->query('per_page', 20))));

        return $this->success([
            'items'        => $page->getCollection()->map(fn ($r) => PersonalVerification::adminPayload($r))->values(),
            'pendingCount' => VerificationRequest::pending()->count(),
            'currentPage'  => $page->currentPage(),
            'lastPage'     => $page->lastPage(),
            'total'        => $page->total(),
        ]);
    }

    public function adminShow(string $id): JsonResponse
    {
        $r = VerificationRequest::with(['user', 'reviewer'])->find($id);
        if (!$r) return $this->notFound();

        return $this->success(PersonalVerification::adminPayload($r, detail: true));
    }

    /** GET /admin/verification-requests/{id}/documents/{index} : pièce justificative (privée). */
    public function document(string $id, int $index)
    {
        $r = VerificationRequest::find($id);
        if (!$r) return $this->notFound();

        return PersonalVerification::documentResponse($r, $index);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $r = VerificationRequest::with('user')->find($id);
        if (!$r) return $this->notFound();

        try {
            PersonalVerification::approve($r, $request->user());
        } catch (VerificationActionException $e) {
            return $this->error($e->getMessage(), $e->status);
        }

        return $this->success(PersonalVerification::adminPayload($r->fresh(['user', 'reviewer']), detail: true), __('verification.api.approved'));
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']],
            ['reason.required' => __('verification.reject_reason_required'), 'reason.min' => __('verification.reject_reason_required')]);
        $r = VerificationRequest::with('user')->find($id);
        if (!$r) return $this->notFound();

        try {
            PersonalVerification::reject($r, $request->user(), $request->input('reason'));
        } catch (VerificationActionException $e) {
            return $this->error($e->getMessage(), $e->status);
        }

        return $this->success(PersonalVerification::adminPayload($r->fresh(['user', 'reviewer']), detail: true), __('verification.api.rejected'));
    }

    /** POST /admin/users/{id}/revoke-verification { reason } : retirer la vérification d'une personne. */
    public function revoke(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']],
            ['reason.required' => __('verification.approval.revoke_reason_required'), 'reason.min' => __('verification.approval.revoke_reason_required')]);
        $user = User::find($id);
        if (!$user) return $this->notFound();

        try {
            PersonalVerification::revoke($user, $request->user(), $request->input('reason'));
        } catch (VerificationActionException $e) {
            return $this->error($e->getMessage(), $e->status);
        }

        return $this->success(['userId' => $user->id, 'isVerified' => false], __('verification.api.revoked'));
    }
}

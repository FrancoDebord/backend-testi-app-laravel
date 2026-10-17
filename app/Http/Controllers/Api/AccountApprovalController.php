<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AccountApproval;
use App\Services\VerificationActionException;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Badge « Approuvé » attribué ou retiré manuellement par un administrateur (motif obligatoire,
 * journal d'audit, notification). Voir docs/fonctionnalites/badge-approuve.md
 */
class AccountApprovalController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AccountApproval $approval) {}

    // POST /admin/users/{id}/approval  { reason }
    public function grant(Request $request, string $id): JsonResponse
    {
        return $this->act($request, $id, fn (User $u, string $reason) => $this->approval->grant($u, $request->user(), $reason), __('verification.flash.badge_granted'));
    }

    // DELETE /admin/users/{id}/approval  { reason }
    public function revoke(Request $request, string $id): JsonResponse
    {
        return $this->act($request, $id, fn (User $u, string $reason) => $this->approval->revoke($u, $request->user(), $reason), __('verification.flash.badge_revoked'));
    }

    private function act(Request $request, string $id, callable $action, string $message): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']], [
            'reason.required' => __('verification.reason_required'),
            'reason.min'      => __('verification.reason_required'),
        ]);
        $user = User::find($id);
        if (!$user) {
            return $this->notFound();
        }
        Gate::authorize('manageApproval', $user);

        try {
            $action($user, $data['reason']);
        } catch (VerificationActionException $e) {
            return $this->error($e->getMessage(), $e->status);
        }

        return $this->success(new UserResource($user->fresh()), $message);
    }
}

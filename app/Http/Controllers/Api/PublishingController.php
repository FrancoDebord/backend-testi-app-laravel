<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminAudit;
use App\Services\TestimonyTypePolicy;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Types de témoignages autorisés — docs/fonctionnalites/types-de-temoignages.md
 *
 *   GET /me/publishing                 → { allowed, global }            (compte qui agit, X-Act-As compris)
 *   GET /admin/users/{id}/publishing   → { global, overrides, allowed } (administrateurs)
 *   PUT /admin/users/{id}/publishing   { text|audio|video|youtube: true|false|null } → même forme
 *
 * Les interrupteurs globaux se modifient avec PUT /admin/settings (testimony_type_{type}_enabled).
 */
class PublishingController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TestimonyTypePolicy $policy) {}

    public function mine(Request $request): JsonResponse
    {
        return $this->success([
            'allowed' => $this->policy->allowed($request->user()),
            'global'  => $this->policy->global(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) return $this->notFound();

        return $this->success($this->adminPayload($user));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) return $this->notFound();

        $rules = [];
        foreach (TestimonyTypePolicy::TYPES as $type) {
            $rules[$type] = ['sometimes', 'nullable', 'boolean'];
        }
        $request->validate($rules);

        $changes = [];
        foreach (TestimonyTypePolicy::TYPES as $type) {
            if ($request->exists($type)) {
                $value = $request->input($type);
                $changes[$type] = $value === null ? null : $request->boolean($type);
            }
        }

        $before = $this->policy->overrides($user);
        $this->policy->updateOverrides($user, $changes);
        $after = $this->policy->overrides($user->refresh());
        if ($before !== $after) {
            AdminAudit::record($request->user(), 'user.testimony_types', $user, $before, $after, null, 'user');
        }

        return $this->success($this->adminPayload($user), __('publishing.api.updated'));
    }

    /** @return array{global: array<string, bool>, overrides: array<string, ?bool>, allowed: array<int, string>} */
    private function adminPayload(User $user): array
    {
        return [
            'global'    => $this->policy->global(),
            'overrides' => $this->policy->overrides($user),
            'allowed'   => $this->policy->allowed($user),
        ];
    }
}

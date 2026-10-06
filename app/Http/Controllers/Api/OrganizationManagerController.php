<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EventActionException;
use App\Services\EventService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestionnaires d'une organisation (2 au plus) : ils créent et gèrent les événements
 * en son nom. Voir docs/fonctionnalites/evenements.md
 */
class OrganizationManagerController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EventService $events) {}

    /** Organisation connectée : ses gestionnaires. */
    public function index(Request $request): JsonResponse
    {
        $org = $request->user();
        if (!$org->isOrganization()) {
            return $this->error('Seuls les comptes organisation ont des gestionnaires.', 422);
        }

        return $this->success($this->payload($org));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['user_id' => 'required|uuid'], ['user_id.required' => 'Choisissez une personne.']);
        $org = $request->user();

        try {
            $target = $this->events->addOrganizationManager($org, $org, $request->input('user_id'));
        } catch (EventActionException $e) {
            return $this->error($e->getMessage(), $e->status());
        }

        return $this->success($this->payload($org), "{$target->display_name} peut maintenant gérer vos événements.");
    }

    public function destroy(Request $request, string $userId): JsonResponse
    {
        $org = $request->user();
        try {
            $this->events->removeOrganizationManager($org, $org, $userId);
        } catch (EventActionException $e) {
            return $this->error($e->getMessage(), $e->status());
        }

        return $this->success($this->payload($org), 'Gestionnaire retiré.');
    }

    /** Personne connectée : organisations dont elle est gestionnaire. */
    public function managed(Request $request): JsonResponse
    {
        return $this->success($request->user()->managedOrganizations()->get()
            ->map(fn (User $o) => [
                'id'          => $o->id,
                'displayName' => $o->organization_name ?: $o->display_name,
                'avatarUrl'   => $o->avatar_url,
                'isVerified'  => $o->isVerified(),
            ])->values());
    }

    /** Se retirer de la gestion d'une organisation. */
    public function leave(Request $request, string $organizationId): JsonResponse
    {
        $org = User::find($organizationId);
        abort_if(!$org, 404, 'Organisation introuvable.');
        try {
            $this->events->removeOrganizationManager($org, $request->user(), $request->user()->id);
        } catch (EventActionException $e) {
            return $this->error($e->getMessage(), $e->status());
        }

        return $this->success(null, "Vous ne gérez plus les événements de cette organisation.");
    }

    private function payload(User $org): array
    {
        return [
            'max'      => \App\Models\Event::MAX_ORGANIZATION_MANAGERS,
            'managers' => $org->organizationManagers()->get()->map(fn (User $u) => [
                'id'          => $u->id,
                'displayName' => $u->display_name,
                'initials'    => $u->initials,
                'avatarUrl'   => $u->avatar_url,
                'since'       => $u->pivot?->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}

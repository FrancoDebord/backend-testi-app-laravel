<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LiveSessionResource;
use App\Http\Resources\PrayerSessionResource;
use App\Models\User;
use App\Services\LiveActionException;
use App\Services\LiveService;
use App\Services\PrayerActionException;
use App\Services\PrayerSessions;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Sessions de prière programmées ; la salle est un direct (routes /lives/{id}/…).
 * Règles communes avec le site : App\Services\PrayerSessions. Voir docs/fonctionnalites/sessions-de-priere.md
 */
class PrayerSessionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PrayerSessions $sessions,
        private readonly LiveService $lives,
    ) {}

    /** `scope` = upcoming (défaut) | past | mine | joined | event (avec `event_id`). */
    public function index(Request $request): JsonResponse
    {
        $user  = $this->user($request);
        $scope = in_array($request->query('scope'), PrayerSessions::SCOPES, true) ? $request->query('scope') : 'upcoming';
        $items = $this->sessions->list($user, $scope, $request->query('event_id'))
            ->paginate(min(max((int) $request->query('limit', 20), 1), 50));

        return $this->paginated(PrayerSessionResource::collection($items->items()), [
            ...$this->meta($items),
            'scope'     => $scope,
            'canCreate' => $this->sessions->canCreate($user),
            'videoConfigured' => $this->lives->isConfigured(),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return $this->attempt(fn () => $this->success(new PrayerSessionResource($this->sessions->find($this->user($request), $id))));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(PrayerSessions::rules(), PrayerSessions::MESSAGES);

        return $this->attempt(function () use ($request, $data) {
            $session = $this->sessions->create($request->user(), $data);

            return $this->created(new PrayerSessionResource($session), 'Session de prière programmée.');
        });
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(PrayerSessions::rules(partial: true), PrayerSessions::MESSAGES);

        return $this->attempt(function () use ($request, $id, $data) {
            $session = $this->sessions->find($request->user(), $id);

            return $this->success(new PrayerSessionResource($this->sessions->update($session, $request->user(), $data)), 'Session mise à jour.');
        });
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        return $this->attempt(function () use ($request, $id) {
            $this->sessions->delete($this->sessions->find($request->user(), $id), $request->user());

            return $this->success(null, 'Session supprimée.');
        });
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        return $this->attempt(function () use ($request, $id) {
            $session = $this->sessions->cancel($this->sessions->find($request->user(), $id), $request->user());

            return $this->success(new PrayerSessionResource($session->load(['host', 'event'])), 'Session annulée.');
        });
    }

    /** « Je serai là ». */
    public function join(Request $request, string $id): JsonResponse
    {
        return $this->attempt(function () use ($request, $id) {
            $session = $this->sessions->find($request->user(), $id);
            $this->sessions->register($session, $request->user());

            return $this->success(new PrayerSessionResource($session->refresh()->load(['host', 'event'])->setAttribute('is_registered', true)),
                'Inscription enregistrée : vous serez prévenu à l\'ouverture de la salle.');
        });
    }

    public function leave(Request $request, string $id): JsonResponse
    {
        return $this->attempt(function () use ($request, $id) {
            $session = $this->sessions->find($request->user(), $id);
            $this->sessions->unregister($session, $request->user());

            return $this->success(new PrayerSessionResource($session->refresh()->load(['host', 'event'])->setAttribute('is_registered', false)),
                'Inscription retirée.');
        });
    }

    /** Inscrits (hôte et modération). */
    public function participants(Request $request, string $id): JsonResponse
    {
        return $this->attempt(function () use ($request, $id) {
            $session = $this->sessions->find($request->user(), $id);
            if (!$session->canBeDeletedBy($request->user())) {
                throw new PrayerActionException("Seul l'hôte voit la liste des inscrits.", 403);
            }
            $items = $session->participants()->with('user')->latest()->paginate(50);

            return $this->paginated(collect($items->items())->map(fn ($p) => [
                'id'           => $p->user_id,
                'displayName'  => $p->user?->display_name ?? 'Utilisateur',
                'initials'     => $p->user?->initials,
                'avatarUrl'    => $p->user?->avatar_url,
                'registeredAt' => $p->created_at?->toIso8601String(),
            ])->values(), $this->meta($items));
        });
    }

    /**
     * L'hôte ouvre la salle (ou reprend celle déjà ouverte) : renvoie la session, le direct et les
     * accès diffuseur, comme POST /lives. L'application ouvre ensuite le studio du direct.
     */
    public function start(Request $request, string $id): JsonResponse
    {
        return $this->attempt(function () use ($request, $id) {
            $user    = $request->user();
            $session = $this->sessions->find($user, $id);
            $live    = $this->sessions->start($session, $user);

            try {
                $video = $this->lives->hostCredentials($live, $user);
            } catch (LiveActionException $e) {
                throw new PrayerActionException($e->getMessage(), $e->status());
            }

            return $this->created([
                'session' => new PrayerSessionResource($this->sessions->find($user, $id)),
                'live'    => new LiveSessionResource($live->load(['host', 'prayerSession'])),
                'video'   => $video,
            ], 'Salle ouverte : passez à l\'antenne pour commencer la prière.');
        });
    }

    // ─── Outils ──────────────────────────────────────────────────────────────

    private function meta(LengthAwarePaginator $p): array
    {
        return [
            'currentPage' => $p->currentPage(),
            'lastPage'    => $p->lastPage(),
            'total'       => $p->total(),
            'perPage'     => $p->perPage(),
        ];
    }

    private function user(Request $request): ?User
    {
        return $request->user() ?? $request->user('sanctum');
    }

    private function attempt(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (PrayerActionException $e) {
            return $this->error($e->getMessage(), $e->status());
        }
    }
}

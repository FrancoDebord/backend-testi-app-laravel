<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PrayerRequestResource;
use App\Models\PrayerRequest;
use App\Models\User;
use App\Services\PrayerActionException;
use App\Services\PrayerRequests;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Requêtes de prière, au format JSON { success, data, meta, message }.
 * Règles communes avec le site : App\Services\PrayerRequests. Voir docs/fonctionnalites/requetes-de-priere.md
 */
class PrayerRequestController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PrayerRequests $requests) {}

    /**
     * `scope` = all (défaut : tout ce que je peux voir) | feed (publiques) | following (comptes suivis,
     * sans anonymes) | mine | event (avec `event_id`) ; `status` = open | answered ; `limit` (50 max).
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $this->user($request);
        $scope = in_array($request->query('scope'), PrayerRequests::SCOPES, true) ? $request->query('scope') : 'all';
        $items = $this->requests->list($user, $scope, $request->query('event_id'), $request->query('status'))
            ->paginate(min(max((int) $request->query('limit', 20), 1), 50));

        return $this->paginated(PrayerRequestResource::collection($items->items()), [
            ...$this->meta($items),
            'scope'     => $scope,
            'canCreate' => $user !== null,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return $this->attempt(fn () => $this->success(new PrayerRequestResource($this->requests->find($this->user($request), $id))));
    }

    /** Publication directe (pas de relecture). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(PrayerRequests::rules(), PrayerRequests::MESSAGES);

        return $this->attempt(fn () => $this->created(
            new PrayerRequestResource($this->requests->create($request->user(), $data)),
            'Votre requête est publiée. Que Dieu vous réponde !'
        ));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(PrayerRequests::rules(partial: true), PrayerRequests::MESSAGES);

        return $this->attempt(function () use ($request, $id, $data) {
            $prayer = $this->requests->find($request->user(), $id);

            return $this->success(new PrayerRequestResource($this->requests->update($prayer, $request->user(), $data)), 'Requête mise à jour.');
        });
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        return $this->attempt(function () use ($request, $id) {
            $this->requests->delete($this->requests->find($request->user(), $id), $request->user());

            return $this->success(null, 'Requête supprimée.');
        });
    }

    /** « Je prie » : bascule, ou `prayed` = true | false pour fixer l'état. */
    public function pray(Request $request, string $id): JsonResponse
    {
        $request->validate(['prayed' => 'sometimes|boolean']);

        return $this->attempt(function () use ($request, $id) {
            $prayer = $this->requests->find($request->user(), $id);
            $prayed = $this->requests->setPrayed($prayer, $request->user(), $request->has('prayed') ? $request->boolean('prayed') : null);

            return $this->success(new PrayerRequestResource($prayer->refresh()->load(['user', 'event'])->setAttribute('has_prayed', $prayed)),
                $prayed ? 'Merci de prier pour cette requête.' : 'Prière retirée.');
        });
    }

    /** Exaucée (`note` facultative). */
    public function answered(Request $request, string $id): JsonResponse
    {
        $request->validate(['note' => 'nullable|string|max:1000']);

        return $this->attempt(function () use ($request, $id) {
            $prayer = $this->requests->find($request->user(), $id);

            return $this->success(new PrayerRequestResource($this->requests->setAnswered($prayer, $request->user(), true, $request->input('note'))),
                'Gloire à Dieu ! Pensez à témoigner de cet exaucement.');
        });
    }

    public function reopen(Request $request, string $id): JsonResponse
    {
        return $this->attempt(function () use ($request, $id) {
            $prayer = $this->requests->find($request->user(), $id);

            return $this->success(new PrayerRequestResource($this->requests->setAnswered($prayer, $request->user(), false)), 'Requête remise en attente.');
        });
    }

    // ─── Messages d'encouragement ────────────────────────────────────────────

    /** Du plus ancien au plus récent. */
    public function messages(Request $request, string $id): JsonResponse
    {
        return $this->attempt(function () use ($request, $id) {
            $viewer = $this->user($request);
            $prayer = $this->requests->find($viewer, $id);
            $items  = $prayer->messages()->with('user')->oldest()->paginate(min((int) $request->query('limit', 50), 100));

            return $this->paginated(collect($items->items())->map(fn ($m) => $m->toPayload($viewer, $prayer))->values(), $this->meta($items));
        });
    }

    public function storeMessage(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(PrayerRequests::messageRules(), PrayerRequests::MESSAGES);

        return $this->attempt(function () use ($request, $id, $data) {
            $prayer  = $this->requests->find($request->user(), $id);
            $message = $this->requests->addMessage($prayer, $request->user(), $data['message'], $data['bible_reference'] ?? null);

            return $this->created($message->toPayload($request->user(), $prayer), 'Merci pour votre encouragement !');
        });
    }

    public function destroyMessage(Request $request, string $id, string $messageId): JsonResponse
    {
        return $this->attempt(function () use ($request, $id, $messageId) {
            $prayer  = $this->requests->find($request->user(), $id);
            $message = $prayer->messages()->find($messageId);
            abort_if(!$message, 404, 'Message introuvable.');
            $this->requests->deleteMessage($prayer, $message, $request->user());

            return $this->success(null, 'Message supprimé.');
        });
    }

    // ─── Signalement et modération ───────────────────────────────────────────

    public function report(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(PrayerRequests::reportRules(), PrayerRequests::MESSAGES);

        return $this->attempt(function () use ($request, $id, $data) {
            $this->requests->report($this->requests->find($request->user(), $id), $request->user(), $data['reason'], $data['comment'] ?? null);

            return $this->success(null, 'Signalement enregistré. Merci pour votre vigilance.');
        });
    }

    /** File de modération (modérateurs) : `filter` = reported (défaut) | hidden. */
    public function moderation(Request $request): JsonResponse
    {
        $items = $this->requests->moderationQueue($request->query('filter', 'reported'))->paginate(30);

        return $this->paginated(collect($items->items())->map(fn (PrayerRequest $p) => array_merge(
            (new PrayerRequestResource($p))->toArray($request),
            ['reports' => $p->reports->map(fn ($r) => [
                'reason'      => $r->reason,
                'reasonLabel' => PrayerRequest::REPORT_REASONS[$r->reason] ?? $r->reason,
                'comment'     => $r->comment,
                'reporter'    => $r->reporter?->display_name,
                'reviewed'    => $r->reviewed_at !== null,
                'createdAt'   => $r->created_at?->toIso8601String(),
            ])->values()],
        ))->values(), $this->meta($items));
    }

    public function hide(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:300']);

        return $this->attempt(function () use ($request, $id) {
            $prayer = $this->requests->find($request->user(), $id);

            return $this->success(new PrayerRequestResource($this->requests->hide($prayer, $request->user(), $request->input('reason'))->load(['user', 'event'])),
                'Requête retirée.');
        });
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        return $this->attempt(function () use ($request, $id) {
            $prayer = $this->requests->find($request->user(), $id);

            return $this->success(new PrayerRequestResource($this->requests->restore($prayer, $request->user())->load(['user', 'event'])),
                'Requête rétablie.');
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

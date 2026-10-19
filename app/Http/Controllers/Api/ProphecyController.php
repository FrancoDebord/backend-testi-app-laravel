<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProphecyResource;
use App\Models\Prophecy;
use App\Models\ProphecyPrayer;
use App\Services\Prophecies;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Paroles prophétiques du carnet privé : visibles de leur seul auteur (404 pour tout autre).
 * Règles communes avec le site : App\Services\Prophecies. Voir docs/fonctionnalites/paroles-prophetiques.md
 */
class ProphecyController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly Prophecies $prophecies) {}

    /** `status` = waiting | fulfilled (toutes par défaut) ; en attente d'abord, puis les plus récentes. */
    public function index(Request $request): JsonResponse
    {
        $items  = $this->prophecies->list($request->user(), $request->query('status'))
            ->paginate(min((int) $request->query('limit', 100), 200));
        $counts = $this->prophecies->counts($request->user());

        return $this->paginated(ProphecyResource::collection($items->items()), [
            'currentPage' => $items->currentPage(),
            'lastPage'    => $items->lastPage(),
            'total'       => $items->total(),
            'waiting'     => $counts[Prophecy::WAITING],
            'fulfilled'   => $counts[Prophecy::FULFILLED],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->prophecies->rules(), Prophecies::messages());

        return $this->created(new ProphecyResource($this->prophecies->create($request->user(), $data)), __('prophecies.flash.created'));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return $this->success(new ProphecyResource($this->prophecies->find($request->user(), $id)));
    }

    /** Modification partielle ; `status` permet aussi de marquer « accomplie » sans témoignage. */
    public function update(Request $request, string $id): JsonResponse
    {
        $prophecy = $this->prophecies->find($request->user(), $id);
        $data = $request->validate($this->prophecies->updateRules(), Prophecies::messages());

        try {
            $prophecy = $this->prophecies->update($prophecy, $data);
        } catch (ValidationException $e) {
            return $this->error(collect($e->errors())->flatten()->first(), 422, $e->errors());
        }

        return $this->success(new ProphecyResource($prophecy), __('prophecies.flash.updated'));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->prophecies->delete($this->prophecies->find($request->user(), $id));

        return $this->success(null, __('prophecies.api.deleted'));
    }

    // ─── Journal de prière ───────────────────────────────────────────────────

    public function prayers(Request $request, string $id): JsonResponse
    {
        $items = $this->prophecies->find($request->user(), $id)->prayers()->paginate(50);

        return $this->paginated(collect($items->items())->map(fn (ProphecyPrayer $p) => $this->prayerPayload($p))->values(), [
            'currentPage' => $items->currentPage(),
            'lastPage'    => $items->lastPage(),
            'total'       => $items->total(),
        ]);
    }

    /** « J'ai prié » (note facultative). */
    public function pray(Request $request, string $id): JsonResponse
    {
        $prophecy = $this->prophecies->find($request->user(), $id);
        $data = $request->validate(['note' => 'nullable|string|max:1000', 'prayed_at' => 'nullable|date']);
        $prayer = $this->prophecies->pray($prophecy, $data['note'] ?? null, $data['prayed_at'] ?? null, $request->user());

        return $this->created([
            'prayer'   => $this->prayerPayload($prayer),
            'prophecy' => new ProphecyResource($prophecy->refresh()),
        ], __('prophecies.flash.prayed'));
    }

    /**
     * POST prophecies/{id}/pray/start : commence (ou recommence) le temps de prière. « J'ai prié » n'est
     * enregistré qu'après config('prayer.focus_seconds') secondes. Voir docs/fonctionnalites/temps-de-priere.md
     */
    public function startPrayer(Request $request, string $id): JsonResponse
    {
        $prophecy = $this->prophecies->find($request->user(), $id);

        return $this->success($this->prophecies->startPrayer($prophecy, $request->user()), __('prayer_focus.started'));
    }

    public function deletePrayer(Request $request, string $id, int $prayerId): JsonResponse
    {
        $prophecy = $this->prophecies->find($request->user(), $id);
        $this->prophecies->deletePrayer($prophecy, $prayerId);

        return $this->success(new ProphecyResource($prophecy->refresh()), __('prophecies.flash.prayer_deleted'));
    }

    private function prayerPayload(ProphecyPrayer $p): array
    {
        return ['id' => $p->id, 'note' => $p->note, 'prayedAt' => $p->prayed_at?->toIso8601String()];
    }
}

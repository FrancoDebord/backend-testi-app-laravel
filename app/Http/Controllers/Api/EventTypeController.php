<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventType;
use App\Services\EventTypeInUseException;
use App\Services\EventTypes;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Types d'événements : liste publique (GET event-types) et gestion par les administrateurs
 * (admin/event-types). Voir docs/fonctionnalites/types-evenements.md
 */
class EventTypeController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EventTypes $types) {}

    /** Types actifs dans l'ordre ; `?all=1` (administrateurs) : inactifs compris. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? $request->user('sanctum');
        $all  = $request->boolean('all') && (bool) $user?->isAdmin();

        return $this->success(EventTypes::listArray($all));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(EventTypes::createRules(), EventTypes::messages());

        return $this->created(EventTypes::toArray($this->types->create($data)), __('event_types.flash.created'));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $type = EventType::findOrFail($id);
        $data = $request->validate(EventTypes::updateRules(), EventTypes::messages());

        return $this->success(EventTypes::toArray($this->types->update($type, $data)), __('event_types.flash.updated'));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $type = EventType::findOrFail($id);

        try {
            $this->types->delete($type, $request->input('replace_with'));
        } catch (EventTypeInUseException $e) {
            return response()->json([
                'success'     => false,
                'data'        => null,
                'message'     => $e->getMessage(),
                'errors'      => ['events' => [$e->getMessage()]],
                'eventsCount' => $e->eventsCount,
            ], 409);
        }

        return $this->success(null, __('event_types.flash.deleted'));
    }

    /** Nouvel ordre : { ids: [id…] } → liste complète (inactifs compris). */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']],
            ['ids.required' => __('event_types.validation.ids_required')]);
        $this->types->reorder($request->input('ids'));

        return $this->success(EventTypes::listArray(all: true), __('event_types.flash.reordered'));
    }
}

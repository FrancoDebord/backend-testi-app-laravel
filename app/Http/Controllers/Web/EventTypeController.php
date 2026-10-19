<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\EventType;
use App\Services\EventTypeInUseException;
use App\Services\EventTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Administration des types d'événements (site). Mêmes règles que l'API admin/event-types.
 * Voir docs/fonctionnalites/types-evenements.md
 */
class EventTypeController extends Controller
{
    public function __construct(private readonly EventTypes $types) {}

    public function index(): View
    {
        return view('admin.event-types.index', [
            'types'  => EventType::query()->ordered()->get(),
            'counts' => EventType::eventCounts(),
            'icons'  => EventType::ICONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(EventTypes::createRules(), EventTypes::messages());
        $this->types->create($data);

        return back()->with('success', __('event_types.flash.created'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $type = EventType::findOrFail($id);
        $data = $request->validate(EventTypes::updateRules(), EventTypes::messages());
        // Case à cocher : absente = inactif (seulement depuis le formulaire de modification complet).
        if ($request->has('_form')) {
            $data['is_active'] = $request->boolean('is_active');
        }
        $this->types->update($type, $data);

        return back()->with('success', __('event_types.flash.updated'));
    }

    /** Activer / désactiver d'un clic. */
    public function toggle(int $id): RedirectResponse
    {
        $type = EventType::findOrFail($id);
        $this->types->update($type, ['is_active' => !$type->is_active]);

        return back()->with('success', __($type->is_active ? 'event_types.flash.activated' : 'event_types.flash.deactivated'));
    }

    /** Monter / descendre d'un rang (`direction` = up | down). */
    public function move(Request $request, int $id): RedirectResponse
    {
        $type = EventType::findOrFail($id);
        $ids  = EventType::query()->ordered()->pluck('id')->all();
        $i    = array_search($type->id, $ids, true);
        $j    = $request->input('direction') === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            $this->types->reorder($ids);
        }

        return back()->with('success', __('event_types.flash.reordered'));
    }

    /** Glisser-déposer : { ids: [...] } (JSON ou formulaire). */
    public function reorder(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']],
            ['ids.required' => __('event_types.validation.ids_required')]);
        $this->types->reorder($request->input('ids'));

        return $request->expectsJson()
            ? response()->json(['success' => true, 'data' => EventTypes::listArray(all: true), 'message' => __('event_types.flash.reordered')])
            : back()->with('success', __('event_types.flash.reordered'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $type    = EventType::findOrFail($id);
        $replace = $request->input('replace_with');
        $count   = $type->eventsCount();

        try {
            $this->types->delete($type, $replace);
        } catch (EventTypeInUseException $e) {
            return back()->withErrors(['replace_with' => $e->getMessage()]);
        }

        if ($count > 0) {
            $name = EventType::labelFor($replace);

            return back()->with('success', trans_choice('event_types.flash.moved', $count, ['count' => $count, 'name' => $name]));
        }

        return back()->with('success', __('event_types.flash.deleted'));
    }
}

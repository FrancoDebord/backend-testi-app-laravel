<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\PrayerRequest;
use App\Services\PrayerActionException;
use App\Services\PrayerRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Pages web des requêtes de prière. Règles communes avec l'API : App\Services\PrayerRequests.
 * Voir docs/fonctionnalites/requetes-de-priere.md
 */
class PrayerRequestController extends Controller
{
    public function __construct(private readonly PrayerRequests $requests) {}

    /** Onglets : toutes (visibles) · mes requêtes ; filtre exaucées. */
    public function index(Request $request): View
    {
        $user   = Auth::user();
        $tab    = $request->query('onglet') === 'miennes' && $user ? 'mine' : 'all';
        $status = $request->query('statut') === 'exaucees' ? PrayerRequest::ANSWERED : null;
        $items  = $this->requests->list($user, $tab, null, $status)->paginate(15)->withQueryString();

        return view('prayer.requests.index', compact('items', 'tab', 'status'));
    }

    public function create(Request $request): View
    {
        $eventId = $request->query('evenement');
        $event   = $eventId && Str::isUuid($eventId) ? Event::find($eventId) : null;

        return view('prayer.requests.create', [
            'event' => $event?->isVisibleTo(Auth::user()) ? $event : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(PrayerRequests::rules(), PrayerRequests::MESSAGES);
        $data['is_anonymous'] = $request->boolean('is_anonymous');

        return $this->attempt(function () use ($data) {
            $prayer = $this->requests->create(Auth::user(), $data);

            return redirect()->route('prayer.requests.show', $prayer->id)->with('success', 'Votre requête est publiée. Que Dieu vous réponde !');
        }, withInput: true);
    }

    public function show(string $id): View
    {
        $user   = Auth::user();
        $prayer = $this->find($id);

        return view('prayer.requests.show', [
            'prayer'   => $prayer,
            'messages' => $prayer->messages()->with('user')->oldest()->paginate(50),
            'reasons'  => PrayerRequest::REPORT_REASONS,
            'user'     => $user,
        ]);
    }

    public function destroy(string $id): RedirectResponse
    {
        return $this->attempt(function () use ($id) {
            $this->requests->delete($this->find($id), Auth::user());

            return redirect()->route('prayer.requests.index')->with('success', 'Requête supprimée.');
        });
    }

    public function pray(string $id): RedirectResponse
    {
        return $this->attempt(function () use ($id) {
            $prayed = $this->requests->setPrayed($this->find($id), Auth::user());

            return back()->with('success', $prayed ? 'Merci de prier pour cette requête.' : 'Prière retirée.');
        });
    }

    public function answered(Request $request, string $id): RedirectResponse
    {
        $request->validate(['note' => 'nullable|string|max:1000']);

        return $this->attempt(function () use ($request, $id) {
            $this->requests->setAnswered($this->find($id), Auth::user(), true, $request->input('note'));

            return back()->with('success', 'Gloire à Dieu ! Pensez à témoigner de cet exaucement.');
        });
    }

    public function reopen(string $id): RedirectResponse
    {
        return $this->attempt(function () use ($id) {
            $this->requests->setAnswered($this->find($id), Auth::user(), false);

            return back()->with('success', 'Requête remise en attente.');
        });
    }

    public function storeMessage(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(PrayerRequests::messageRules(), PrayerRequests::MESSAGES);

        return $this->attempt(function () use ($data, $id) {
            $this->requests->addMessage($this->find($id), Auth::user(), $data['message'], $data['bible_reference'] ?? null);

            return redirect()->to(route('prayer.requests.show', $id) . '#encouragements')->with('success', 'Merci pour votre encouragement !');
        }, withInput: true);
    }

    public function destroyMessage(string $id, string $message): RedirectResponse
    {
        return $this->attempt(function () use ($id, $message) {
            $prayer = $this->find($id);
            $row    = $prayer->messages()->findOrFail($message);
            $this->requests->deleteMessage($prayer, $row, Auth::user());

            return back()->with('success', 'Message supprimé.');
        });
    }

    public function report(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(PrayerRequests::reportRules(), PrayerRequests::MESSAGES);

        return $this->attempt(function () use ($data, $id) {
            $this->requests->report($this->find($id), Auth::user(), $data['reason'], $data['comment'] ?? null);

            return back()->with('success', 'Signalement enregistré. Merci pour votre vigilance.');
        });
    }

    // ─── Modération ──────────────────────────────────────────────────────────

    public function moderation(Request $request): View
    {
        $filter = $request->query('filtre') === 'retirees' ? 'hidden' : 'reported';

        return view('prayer.moderation', [
            'filter'  => $filter,
            'items'   => $this->requests->moderationQueue($filter)->paginate(20)->withQueryString(),
            'reasons' => PrayerRequest::REPORT_REASONS,
        ]);
    }

    public function hide(Request $request, string $id): RedirectResponse
    {
        $request->validate(['reason' => 'nullable|string|max:300']);

        return $this->attempt(function () use ($request, $id) {
            $this->requests->hide($this->find($id), Auth::user(), $request->input('reason'));

            return back()->with('success', 'Requête retirée.');
        });
    }

    public function restore(string $id): RedirectResponse
    {
        return $this->attempt(function () use ($id) {
            $this->requests->restore($this->find($id), Auth::user());

            return back()->with('success', 'Requête rétablie.');
        });
    }

    // ─── Outils ──────────────────────────────────────────────────────────────

    private function find(string $id): PrayerRequest
    {
        try {
            return $this->requests->find(Auth::user(), $id);
        } catch (PrayerActionException $e) {
            abort(404, $e->getMessage());
        }
    }

    private function attempt(callable $action, bool $withInput = false): RedirectResponse
    {
        try {
            return $action();
        } catch (PrayerActionException $e) {
            $back = back()->with('error', $e->getMessage());

            return $withInput ? $back->withInput() : $back;
        }
    }
}

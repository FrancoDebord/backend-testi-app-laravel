<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\PrayerSession;
use App\Services\LiveService;
use App\Services\PrayerActionException;
use App\Services\PrayerSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Pages web des sessions de prière. La salle réutilise les pages des directs
 * (studio de l'hôte, page de visionnage). Voir docs/fonctionnalites/sessions-de-priere.md
 */
class PrayerSessionController extends Controller
{
    public const TABS = [
        'upcoming' => 'À venir',
        'joined'   => 'Mes inscriptions',
        'mine'     => 'Que j\'anime',
        'past'     => 'Passées',
    ];

    public function __construct(
        private readonly PrayerSessions $sessions,
        private readonly LiveService $lives,
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        $tab  = array_key_exists($request->query('onglet'), self::TABS) ? $request->query('onglet') : 'upcoming';
        if (!$user && in_array($tab, ['joined', 'mine'], true)) $tab = 'upcoming';

        return view('prayer.sessions.index', [
            'tab'   => $tab,
            'items' => $this->sessions->list($user, $tab)->paginate(12)->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        $eventId = $request->query('evenement');
        $event   = $eventId && Str::isUuid($eventId) ? Event::find($eventId) : null;

        return view('prayer.sessions.form', [
            'session' => new PrayerSession(['duration_minutes' => 60, 'visibility' => PrayerSession::PUBLIC]),
            'event'   => $event?->canBeManagedBy(Auth::user()) ? $event : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        return $this->attempt(function () use ($data) {
            $session = $this->sessions->create(Auth::user(), $data);

            return redirect()->route('prayer.sessions.show', $session->id)->with('success', 'Session de prière programmée.');
        }, withInput: true);
    }

    public function show(string $id): View
    {
        $session = $this->find($id);
        $live    = $session->activeLive();

        return view('prayer.sessions.show', [
            'session'      => $session,
            'live'         => $live?->isVisibleTo(Auth::user()) ? $live : null,
            'configured'   => $this->lives->isConfigured(),
            'participants' => $session->canBeDeletedBy(Auth::user())
                ? $session->participants()->with('user')->latest()->limit(50)->get()
                : collect(),
        ]);
    }

    public function edit(string $id): View
    {
        $session = $this->find($id);
        abort_unless($session->canBeEditedBy(Auth::user()), 403);

        return view('prayer.sessions.form', ['session' => $session, 'event' => $session->event]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $session = $this->find($id);
        $data    = $this->validated($request, partial: true);

        return $this->attempt(function () use ($session, $data) {
            $this->sessions->update($session, Auth::user(), $data);

            return redirect()->route('prayer.sessions.show', $session->id)->with('success', 'Session mise à jour.');
        }, withInput: true);
    }

    public function destroy(string $id): RedirectResponse
    {
        return $this->attempt(function () use ($id) {
            $this->sessions->delete($this->find($id), Auth::user());

            return redirect()->route('prayer.sessions.index')->with('success', 'Session supprimée.');
        });
    }

    public function cancel(string $id): RedirectResponse
    {
        return $this->attempt(function () use ($id) {
            $this->sessions->cancel($this->find($id), Auth::user());

            return back()->with('success', 'Session annulée.');
        });
    }

    public function join(string $id): RedirectResponse
    {
        return $this->attempt(function () use ($id) {
            $this->sessions->register($this->find($id), Auth::user());

            return back()->with('success', 'Inscription enregistrée : vous serez prévenu à l\'ouverture de la salle.');
        });
    }

    public function leave(string $id): RedirectResponse
    {
        return $this->attempt(function () use ($id) {
            $this->sessions->unregister($this->find($id), Auth::user());

            return back()->with('success', 'Inscription retirée.');
        });
    }

    /** L'hôte ouvre la salle : studio du direct (caméra / micro, passage à l'antenne). */
    public function open(string $id): RedirectResponse
    {
        return $this->attempt(function () use ($id) {
            $live = $this->sessions->start($this->find($id), Auth::user());

            return redirect()->route('lives.studio', $live->id);
        });
    }

    /** Rejoindre : page de visionnage du direct (commentaires, demande d'intervention). */
    public function room(string $id): RedirectResponse
    {
        $session = $this->find($id);
        $live    = $session->activeLive();
        if ($live && $session->isHost(Auth::user())) {
            return redirect()->route('lives.studio', $live->id);
        }
        if (!$live || !$live->isVisibleTo(Auth::user())) {
            return redirect()->route('prayer.sessions.show', $session->id)->with('error', "La salle n'est pas encore ouverte.");
        }

        return redirect()->route('lives.show', $live->id);
    }

    // ─── Outils ──────────────────────────────────────────────────────────────

    /** Formulaire : date et heure séparées, sujets un par ligne. */
    private function validated(Request $request, bool $partial = false): array
    {
        $request->validate([
            'date' => [$partial ? 'sometimes' : 'required', 'date_format:Y-m-d'],
            'time' => [$partial ? 'sometimes' : 'required', 'date_format:H:i'],
        ], ['date.required' => 'Indiquez la date.', 'time.required' => "Indiquez l'heure."]);

        $input = $request->only(['title', 'description', 'visibility', 'duration_minutes', 'event_id']);
        if ($request->filled('date') && $request->filled('time')) {
            $input['starts_at'] = Carbon::createFromFormat('Y-m-d H:i', $request->input('date') . ' ' . $request->input('time'), config('app.timezone'))->toIso8601String();
        }
        if ($request->has('topics_text')) {
            $input['topics'] = preg_split('/\R/u', (string) $request->input('topics_text'), -1, PREG_SPLIT_NO_EMPTY);
        }
        $request->merge($input);

        return $request->validate(PrayerSessions::rules($partial), PrayerSessions::MESSAGES);
    }

    private function find(string $id): PrayerSession
    {
        try {
            return $this->sessions->find(Auth::user(), $id);
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

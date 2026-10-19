<?php

namespace App\Http\Controllers\Web;

use App\Enums\LiveStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\LiveSession;
use App\Services\LiveActionException;
use App\Services\LiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Pages web des témoignages en direct. Les actions JSON sont dans Api\LiveController. */
class LiveController extends Controller
{
    public function __construct(private readonly LiveService $lives) {}

    public function index(): View
    {
        $user   = Auth::user();
        $active = LiveSession::with('host')->active()->latest()->get()->filter(fn ($l) => $l->isVisibleTo($user));
        $recent = LiveSession::with(['host', 'testimony'])->where('status', LiveStatus::Ended)->whereNotNull('started_at')
            ->latest('ended_at')->limit(12)->get();

        return view('lives.index', [
            'active'     => $active,
            'recent'     => $recent,
            'configured' => $this->lives->isConfigured(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        // Direct d'un événement (?event=) : ouvert à ses gestionnaires (docs/fonctionnalites/evenements.md).
        $event = $this->authorizedEvent($request->query('event'));
        if ($event && $event->status !== \App\Enums\EventStatus::Published) {
            return redirect()->route('events.show', $event->id)
                ->with('error', __('lives.messages.event_not_published'));
        }

        $existing = LiveSession::active()->where('host_id', Auth::id())->first();
        if ($existing) {
            return redirect()->route('lives.studio', $existing->id)
                ->with('status', __('lives.messages.already_live'));
        }

        return view('lives.create', [
            'event'               => $event,
            'categories'          => Category::active()->get(),
            'configured'          => $this->lives->isConfigured(),
            'recordingConfigured' => $this->lives->isRecordingConfigured(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizedEvent($request->input('event_id'));

        $data = $request->validate([
            'event_id'         => 'nullable|uuid',
            'title'            => 'required|string|max:150',
            'description'      => 'nullable|string|max:1000',
            'category_slug'    => 'nullable|string|exists:categories,slug',
            'comments_enabled' => 'nullable|boolean',
            'record'           => 'nullable|boolean',
            // Caméra IP / encodeur (docs/fonctionnalites/lives-camera-ip.md)
            'source'           => 'nullable|in:browser,rtmp,url',
            'camera_url'       => ['nullable', 'required_if:source,url', 'string', 'max:500', 'regex:#^(rtsps?|rtmps?|https?|srt)://[^\s]+$#i'],
            // Vérifications de la caméra de l'appareil : inutiles avec une caméra IP.
            'checks_passed'    => $request->input('source', 'browser') === 'browser' ? 'accepted' : 'nullable',
        ], [
            'camera_url.required_if' => __('lives.messages.camera_url_required'),
            'camera_url.regex'       => __('lives.messages.camera_url_regex'),
            'title.required'         => __('lives.messages.title_required'),
            'checks_passed.accepted' => __('lives.messages.checks_required'),
        ]);
        $data['comments_enabled'] = $request->boolean('comments_enabled');
        $data['record'] = $request->boolean('record');

        try {
            $live = $this->lives->start(Auth::user(), $data);
        } catch (LiveActionException $e) {
            if ($e->live) {
                return redirect()->route('lives.studio', $e->live->id)->with('error', $e->getMessage());
            }
            // Sans l'adresse de la caméra (peut contenir un mot de passe).
            return back()->withInput($request->except(['camera_url', 'camera_password', 'camera_passphrase']))->with('error', $e->getMessage());
        }

        return redirect()->route('lives.studio', $live->id);
    }

    /**
     * Sans événement : modérateurs et administrateurs seulement. Avec un événement : ses gestionnaires
     * (organisateur, administrateur) ; LiveService::start revérifie (publié, organisation vérifiée).
     */
    private function authorizedEvent(?string $eventId): ?Event
    {
        $user = Auth::user();
        if (blank($eventId)) {
            abort_unless($user->canModerate(), 403);

            return null;
        }
        $event = Str::isUuid($eventId) ? Event::find($eventId) : null;
        abort_if(!$event, 404);
        abort_unless($event->canBeManagedBy($user), 403);

        return $event;
    }

    public function studio(string $id): View
    {
        $live = LiveSession::with('host')->findOrFail($id);
        abort_unless($live->isHost(Auth::user()), 403);
        abort_if($live->status === LiveStatus::Ended, 410, __('lives.messages.ended'));

        return view('lives.studio', ['live' => $live]);
    }

    public function show(string $id): View
    {
        $live = LiveSession::with(['host', 'event'])->findOrFail($id);
        abort_unless($live->isVisibleTo(Auth::user()), 404);

        // Direct terminé : revoir le chat (pagination ?chat=) et témoignages publiés sur le direct (docs/fonctionnalites/lives.md).
        $replay = null;
        if ($live->status === \App\Enums\LiveStatus::Ended) {
            $replay = [
                'comments'    => $live->comments()->with('user')->where('is_hidden', false)
                    ->oldest()->orderBy('id')->paginate(50, ['*'], 'chat')->withQueryString(),
                'testimonies' => $live->linkedTestimonies()->published()->with(['user', 'category'])->latest()->limit(12)->get(),
                'testimoniesCount' => $live->linkedTestimonies()->published()->count(),
            ];
        }

        return view('lives.show', [
            'live'       => $live,
            'configured' => $this->lives->isConfigured(),
            'replay'     => $replay,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Enums\LiveStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\LiveSession;
use App\Services\LiveActionException;
use App\Services\LiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function create(): View|RedirectResponse
    {
        abort_unless(Auth::user()->canModerate(), 403);

        $existing = LiveSession::active()->where('host_id', Auth::id())->first();
        if ($existing) {
            return redirect()->route('lives.studio', $existing->id)
                ->with('status', 'Vous avez déjà un direct en cours : vous pouvez le reprendre ici.');
        }

        return view('lives.create', [
            'categories'          => Category::active()->get(),
            'configured'          => $this->lives->isConfigured(),
            'recordingConfigured' => $this->lives->isRecordingConfigured(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->canModerate(), 403);

        $data = $request->validate([
            'title'            => 'required|string|max:150',
            'description'      => 'nullable|string|max:1000',
            'category_slug'    => 'nullable|string|exists:categories,slug',
            'comments_enabled' => 'nullable|boolean',
            'record'           => 'nullable|boolean',
            'checks_passed'    => 'accepted',
        ], [
            'title.required'         => 'Merci de donner un titre au direct.',
            'checks_passed.accepted' => 'Les vérifications de la caméra et du micro doivent réussir avant de lancer le direct.',
        ]);
        $data['comments_enabled'] = $request->boolean('comments_enabled');
        $data['record'] = $request->boolean('record');

        try {
            $live = $this->lives->start(Auth::user(), $data);
        } catch (LiveActionException $e) {
            if ($e->live) {
                return redirect()->route('lives.studio', $e->live->id)->with('error', $e->getMessage());
            }
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('lives.studio', $live->id);
    }

    public function studio(string $id): View
    {
        $live = LiveSession::with('host')->findOrFail($id);
        abort_unless($live->isHost(Auth::user()), 403);
        abort_if($live->status === LiveStatus::Ended, 410, 'Ce direct est terminé.');

        return view('lives.studio', ['live' => $live]);
    }

    public function show(string $id): View
    {
        $live = LiveSession::with('host')->findOrFail($id);
        abort_unless($live->isVisibleTo(Auth::user()), 404);

        return view('lives.show', [
            'live'       => $live,
            'configured' => $this->lives->isConfigured(),
        ]);
    }
}

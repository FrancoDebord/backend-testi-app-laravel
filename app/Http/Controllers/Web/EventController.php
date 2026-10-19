<?php

namespace App\Http\Controllers\Web;

use App\Enums\EventStatus;
use App\Enums\LiveStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\EventParticipation;
use App\Models\EventType;
use App\Services\EventActionException;
use App\Services\EventModeration;
use App\Services\EventService;
use App\Support\EventLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pages web des événements chrétiens (croisades, conférences, camps…).
 * Toutes les règles métier sont dans App\Services\EventService (partagé avec l'API mobile).
 * Voir docs/fonctionnalites/evenements.md
 */
class EventController extends Controller
{
    public function __construct(private readonly EventService $events) {}

    // ─── Pages ───────────────────────────────────────────────────────────────

    /** Liste : onglets à venir / passés / mes événements / j'y participe, filtre par type, recherche. */
    public function index(Request $request): View|RedirectResponse
    {
        $user      = Auth::user();
        $canCreate = Event::canBeCreatedBy($user);
        $tabs      = ['upcoming' => __('events.tabs.upcoming'), 'past' => __('events.tabs.past')];
        // « Mes événements » : ceux que la personne gère (organisateur, organisation gérée, co-gestionnaire).
        if ($canCreate || ($user && Event::query()->managedBy($user)->exists())) $tabs['mine'] = __('events.tabs.mine');
        if ($user) $tabs['going'] = __('events.tabs.going');

        $tab  = array_key_exists($request->query('onglet'), $tabs) ? $request->query('onglet') : 'upcoming';
        // Onglet « Passés » : page dédiée (filtres conservés).
        if ($tab === 'past') {
            return redirect()->route('events.past', array_filter($request->only(['type', 'q'])));
        }
        $type = EventType::knownSlug((string) $request->query('type'));
        $q    = trim((string) $request->query('q'));

        $query = Event::query()->with([
            'organizer', 'images',
            'lives' => fn ($l) => $l->where('status', LiveStatus::Live->value)->latest(),
        ]);

        match ($tab) {
            'mine'  => $query->managedBy($user)->orderByDesc('starts_at'),
            'going' => $query->published()
                ->whereHas('participations', fn ($p) => $p->where('user_id', $user->id)->where('status', EventParticipation::GOING))
                ->orderBy('starts_at'),
            'past'  => $query->published()->past(),
            default => $query->published()->upcoming(),
        };
        if ($type) $query->where('type', $type);
        if ($q !== '') {
            $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")
                ->orWhere('city', 'like', "%{$q}%")
                ->orWhere('location', 'like', "%{$q}%"));
        }

        $compact = EventLayout::isCompact($request);

        return view('events.index', [
            // Liste compacte : plus d'événements par page.
            'events'    => $query->paginate($compact ? 24 : 12)->withQueryString(),
            'compact'   => $compact,
            'tabs'      => $tabs,
            'tab'       => $tab,
            'type'      => $type,
            'q'         => $q,
            'canCreate' => $canCreate,
        ]);
    }

    /**
     * Événements passés : terminés (annulés exclus), du plus récent au plus ancien, avec le nombre de témoignages
     * publiés et la vignette ; chaque carte ouvre l'événement sur ses témoignages. Même liste que GET /events?when=past.
     */
    public function past(Request $request): View
    {
        $type = EventType::knownSlug((string) $request->query('type'));
        $q    = trim((string) $request->query('q'));

        $query = Event::query()->with(['organizer', 'images'])->withPublishedTestimonyCount()->finished();
        if ($type) $query->where('type', $type);
        if ($q !== '') {
            $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")
                ->orWhere('city', 'like', "%{$q}%")
                ->orWhere('location', 'like', "%{$q}%"));
        }

        return view('events.past', [
            'events' => $query->paginate(12)->withQueryString(),
            'type'   => $type,
            'q'      => $q,
        ]);
    }

    public function show(Request $request, string $id): View
    {
        $user    = Auth::user();
        $event   = $this->find($id);
        $event->load('managers');
        $manager       = $event->canBeManagedBy($user);
        $administrator = $event->canBeAdministeredBy($user);

        $section     = $request->query('section') === 'commentaires' ? 'commentaires' : 'temoignages';
        $testimonies = $event->testimoniesVisibleTo($user)->with(['user', 'category'])->latest()
            ->paginate(12, ['*'], 'tpage')->withQueryString();
        $comments    = $event->comments()->with(['user', 'testimony:id,status'])->latest()
            ->paginate(20, ['*'], 'cpage')->withQueryString();

        // Direct en cours ou en préparation ; le bouton « Regarder » n'apparaît que s'il est visible.
        $activeLive = $event->activeLive();
        $live       = $activeLive?->isVisibleTo($user) ? $activeLive : null;

        // Sondage d'après l'événement, signalement, modération (docs/fonctionnalites/evenements.md)
        $moderation = app(EventModeration::class);
        $myReview   = $moderation->reviewOf($event, $user);
        $moderator  = (bool) $user?->canModerate();
        $reviews    = $event->acceptsReviews() || $event->surveyTotal() > 0
            ? $event->reviews()->with('user')->where(fn ($q) => $q->whereNotNull('rating')->orWhereNotNull('body'))
                ->latest('updated_at')->paginate(10, ['*'], 'apage')->withQueryString()
            : null;

        return view('events.show', [
            'myReview'      => $myReview,
            'canReview'     => $moderation->canReview($event, $user, $myReview),
            'reviews'       => $reviews,
            'hasReported'   => $moderation->hasReported($event, $user),
            'moderator'     => $moderator,
            'reliability'   => Event::reliabilityOf($event->organizer_id),
            'reportReasons' => Event::reportReasons(),
            'event'         => $event,
            'manager'       => $manager,
            'participation' => $event->participationOf($user),
            'live'          => $live,
            'activeLive'    => $activeLive,
            'section'       => $section,
            'testimonies'   => $testimonies,
            'comments'      => $comments,
            'categories'    => $manager ? Category::active()->get() : collect(),
            'administrator' => $administrator,
            // Recherche sans JavaScript (?personne=…) : components/user-picker
            'pickerResults' => $administrator && $event->managers->count() < Event::MAX_MANAGERS && $request->filled('personne')
                ? UserSearchController::search((string) $request->query('personne'), [$event->organizer_id, $user->id, ...$event->managers->pluck('id')])
                : null,
        ]);
    }

    public function create(): View
    {
        abort_unless(Event::canBeCreatedBy(Auth::user()), 403);

        return view('events.form', [
            'event'      => new Event(['comments_enabled' => true, 'status' => EventStatus::Published]),
            'organizers' => self::organizerChoices(Auth::user()),
        ]);
    }

    /**
     * Identités au nom desquelles la personne peut créer un événement : elle-même (administrateur,
     * organisation vérifiée), puis chaque organisation vérifiée qu'elle gère. [id => nom]
     */
    public static function organizerChoices(\App\Models\User $user): array
    {
        $choices = [];
        if ($user->isAdmin() || $user->isVerifiedOrganization()) {
            $choices[$user->id] = $user->display_name;
        }
        foreach ($user->verifiedOrganizationsManaged()->orderBy('display_name')->get() as $org) {
            $choices[$org->id] = $org->organization_name ?: $org->display_name;
        }

        return $choices;
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Event::canBeCreatedBy(Auth::user()), 403);

        $data = $this->validated($request, creating: true);

        try {
            $event = $this->events->create($request->user(), $data);
            foreach ((array) $request->file('images', []) as $file) {
                $this->events->addImage($event, $request->user(), $file);
            }
        } catch (EventActionException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('events.show', $event->id)->with('success', $event->status === EventStatus::Draft
            ? __('events.flash.saved_draft')
            : __('events.flash.published'));
    }

    public function edit(string $id): View
    {
        $event = $this->find($id);
        abort_unless($event->canBeManagedBy(Auth::user()), 403);

        return view('events.form', ['event' => $event, 'organizers' => [], 'administrator' => $event->canBeAdministeredBy(Auth::user())]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);
        abort_unless($event->canBeManagedBy($request->user()), 403);

        $data  = $this->validated($request, creating: false, existingImages: $event->images->count(), currentType: $event->type);

        try {
            $this->events->update($event, $request->user(), $data);
            foreach ((array) $request->file('images', []) as $file) {
                $this->events->addImage($event, $request->user(), $file);
            }
        } catch (EventActionException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('events.show', $event->id)->with('success', __('events.flash.updated'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);

        return $this->attempt(function () use ($request, $event) {
            $this->events->delete($event, $request->user());

            return redirect()->route('events.index')->with('success', __('events.flash.deleted'));
        });
    }

    /** Personnes qui participent (gestionnaires). */
    public function participants(Request $request, string $id): View
    {
        $event = $this->find($id);
        abort_unless($event->canBeManagedBy($request->user()), 403);

        $status = $request->query('statut') === 'non' ? EventParticipation::NOT_GOING : EventParticipation::GOING;

        return view('events.participants', [
            'event'  => $event,
            'status' => $status,
            'items'  => $event->participations()->with('user')->where('status', $status)->latest('updated_at')
                ->paginate(30)->withQueryString(),
        ]);
    }

    // ─── Co-gestionnaires (2 au plus) ────────────────────────────────────────

    public function addManager(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);
        $request->validate(['user_id' => 'required|uuid'], [
            'user_id.required' => __('events.validation.user_required'),
            'user_id.uuid'     => __('events.validation.user_uuid'),
        ]);

        return $this->attempt(function () use ($request, $event) {
            $target = $this->events->addManager($event, $request->user(), $request->input('user_id'));

            return redirect()->to(route('events.show', $event->id) . '#co-gestionnaires')
                ->with('success', __('events.flash.manager_added', ['name' => $target->display_name]));
        });
    }

    public function removeManager(Request $request, string $id, string $user): RedirectResponse
    {
        $event = $this->find($id);
        $self  = $request->user()->id === $user;

        return $this->attempt(function () use ($request, $event, $user, $self) {
            $this->events->removeManager($event, $request->user(), $user);

            // Après s'être retiré d'un brouillon, la page n'est plus visible : retour à la liste.
            if ($self && !$event->fresh()->isVisibleTo($request->user())) {
                return redirect()->route('events.index')->with('success', __('events.flash.manager_self_removed'));
            }

            return redirect()->to(route('events.show', $event->id) . ($self ? '' : '#co-gestionnaires'))
                ->with('success', $self ? __('events.flash.manager_self_removed') : __('events.flash.manager_removed'));
        });
    }

    // ─── Images de couverture ────────────────────────────────────────────────

    public function storeImage(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);
        $request->validate(['image' => EventService::imageRules()], EventService::imageMessages());

        return $this->attempt(function () use ($request, $event) {
            $this->events->addImage($event, $request->user(), $request->file('image'));

            return back()->with('success', __('events.flash.image_added'));
        });
    }

    public function destroyImage(Request $request, string $id, string $image): RedirectResponse
    {
        $event = $this->find($id);

        return $this->attempt(function () use ($request, $event, $image) {
            $this->events->removeImage($event, $request->user(), $image);

            return back()->with('success', __('events.flash.image_removed'));
        });
    }

    public function coverImage(Request $request, string $id, string $image): RedirectResponse
    {
        $event = $this->find($id);

        return $this->attempt(function () use ($request, $event, $image) {
            $this->events->makeCover($event, $request->user(), $image);

            return back()->with('success', __('events.flash.image_cover'));
        });
    }

    // ─── Participation ───────────────────────────────────────────────────────

    public function participate(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);
        $request->validate(['status' => 'required|in:going,not_going']);

        return $this->attempt(function () use ($request, $event) {
            $status = $this->events->participate($event, $request->user(), $request->input('status'));

            return back()->with('success', $status === EventParticipation::GOING
                ? __('events.flash.going')
                : __('events.flash.answered'));
        });
    }

    public function cancelParticipation(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);
        $this->events->cancelParticipation($event, $request->user());

        return back()->with('success', __('events.flash.answer_removed'));
    }

    // ─── Commentaires ────────────────────────────────────────────────────────

    public function storeComment(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);
        $request->validate(['body' => 'required|string|min:2|max:3000'], [
            'body.required' => __('events.validation.comment_required'),
            'body.min'      => __('events.validation.comment_min'),
            'body.max'      => __('events.validation.comment_max'),
        ]);

        return $this->attempt(function () use ($request, $event) {
            $this->events->comment($event, $request->user(), $request->input('body'));

            return redirect()->route('events.show', [$event->id, 'section' => 'commentaires'])
                ->with('success', __('events.flash.comment_added'));
        }, withInput: true);
    }

    public function destroyComment(Request $request, string $id, string $comment): RedirectResponse
    {
        $item = $this->findComment($id, $comment);

        return $this->attempt(function () use ($request, $item) {
            $this->events->deleteComment($item, $request->user());

            return back()->with('success', __('events.flash.comment_deleted'));
        });
    }

    /** Enregistre un commentaire comme témoignage officiel de l'événement (gestionnaires). */
    public function promoteComment(Request $request, string $id, string $comment): RedirectResponse
    {
        $item = $this->findComment($id, $comment);
        $data = $request->validate([
            'title'    => 'nullable|string|max:200',
            'category' => 'nullable|string|exists:categories,slug',
        ], ['category.exists' => __('events.validation.category_exists')]);

        return $this->attempt(function () use ($request, $item, $data) {
            $this->events->promote($item, $request->user(), array_filter($data));

            return back()->with('success', $request->user()->canModerate()
                ? __('events.flash.promoted')
                : __('events.flash.promoted_pending'));
        });
    }

    // ─── Affichage de la liste (cartes / liste compacte) ─────────────────────

    public function layout(Request $request): RedirectResponse
    {
        $data = $request->validate(['layout' => ['required', Rule::in(array_keys(EventLayout::labels()))]]);

        return redirect()->back(fallback: route('events.index'))
            ->withCookie(cookie(EventLayout::COOKIE, $data['layout'], EventLayout::LIFETIME));
    }

    // ─── Report et annulation (gestionnaires) ────────────────────────────────

    public function postpone(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);
        $tbd   = $request->boolean('date_tbd');
        $data  = $request->validate(EventService::postponeRules($tbd), EventService::postponeMessages());

        return $this->attempt(function () use ($request, $event, $data, $tbd) {
            $this->events->postpone($event, $request->user(),
                $tbd ? null : $data['starts_at'], $tbd ? null : $data['ends_at'], $data['reason'] ?? null);

            return redirect()->route('events.show', $event->id)->with('success', $tbd
                ? __('events.flash.postponed_tbd')
                : __('events.flash.postponed'));
        }, withInput: true);
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);
        $data  = $request->validate(['reason' => 'nullable|string|max:500'], EventService::postponeMessages());

        return $this->attempt(function () use ($request, $event, $data) {
            $this->events->cancel($event, $request->user(), $data['reason'] ?? null);

            return redirect()->route('events.show', $event->id)->with('success', __('events.flash.cancelled'));
        });
    }

    // ─── Signalement ─────────────────────────────────────────────────────────

    public function report(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);
        $data  = $request->validate(EventModeration::reportRules(), EventModeration::messages());

        return $this->attempt(function () use ($request, $event, $data) {
            app(EventModeration::class)->report($event, $request->user(), $data['reason'], $data['comment'] ?? null);
            $event->refresh();

            // Masqué automatiquement : la page n'est plus visible pour la personne.
            return $event->isVisibleTo($request->user())
                ? back()->with('success', __('events.flash.reported'))
                : redirect()->route('events.index')->with('success', __('events.flash.reported'));
        });
    }

    // ─── Sondage d'après l'événement ─────────────────────────────────────────

    public function review(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);
        $data  = $request->validate(EventModeration::reviewRules(), EventModeration::messages());

        return $this->attempt(function () use ($request, $event, $data) {
            app(EventModeration::class)->review($event, $request->user(), $data);

            return redirect()->to(route('events.show', $event->id) . '#votre-avis')->with('success', __('events.flash.reviewed'));
        }, withInput: true);
    }

    // ─── Modération (modérateurs et administrateurs) ─────────────────────────

    /** File : signalés (défaut), non confirmés (?filtre=non-confirmes), masqués (?filtre=masques). */
    public function moderation(Request $request): View
    {
        $filter = match ($request->query('filtre')) {
            'masques'       => 'hidden',
            'non-confirmes' => 'unconfirmed',
            default         => 'reported',
        };
        $items = app(EventModeration::class)->queue($filter)->paginate(20)->withQueryString();

        return view('events.moderation', [
            'filter'      => $filter,
            'items'       => $items,
            'reasons'     => Event::reportReasons(),
            'reliability' => $items->getCollection()->pluck('organizer_id')->unique()
                ->mapWithKeys(fn ($id) => [$id => Event::reliabilityOf($id)]),
        ]);
    }

    public function hide(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);
        $data  = $request->validate(['reason' => 'nullable|string|max:300']);

        return $this->attempt(function () use ($request, $event, $data) {
            app(EventModeration::class)->hide($event, $request->user(), $data['reason'] ?? null);

            return back()->with('success', __('events.flash.hidden'));
        });
    }

    public function restore(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);

        return $this->attempt(function () use ($request, $event) {
            app(EventModeration::class)->restore($event, $request->user());

            return back()->with('success', __('events.flash.restored'));
        });
    }

    public function dismiss(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);

        return $this->attempt(function () use ($request, $event) {
            app(EventModeration::class)->dismiss($event, $request->user());

            return back()->with('success', __('events.flash.dismissed'));
        });
    }

    // ─── Outils (aussi utilisés par les vues) ────────────────────────────────

    /** « sam. 12 oct. 2026 · 18:00 – 22:00 », ou deux dates complètes si l'événement dure plusieurs jours. */
    public static function dateRange(Event $event, bool $original = false): string
    {
        if (!$original && $event->isDateTbd()) return __('events.date_tbd');
        $start = $original ? $event->original_starts_at : $event->starts_at;
        $end   = $original ? $event->original_ends_at : $event->ends_at;
        if (!$start) return '';
        $text = $start->translatedFormat(__('events.formats.short_datetime'));
        if (!$end || $end->equalTo($start)) return $text;

        return $start->isSameDay($end)
            ? $text . ' – ' . $end->format(__('events.formats.time'))
            : $text . ' → ' . $end->translatedFormat(__('events.formats.short_datetime'));
    }

    /** Lieu lisible : « Stade de l'Amitié, Cotonou, Bénin ». */
    public static function place(Event $event): string
    {
        return collect([$event->location, $event->city, $event->country])->filter(fn ($v) => filled($v))->implode(', ');
    }

    private function validated(Request $request, bool $creating, int $existingImages = 0, ?string $currentType = null): array
    {
        // Lignes d'invités laissées vides : ignorées (le formulaire en propose toujours une).
        $request->merge([
            'guests' => collect((array) $request->input('guests', []))
                ->filter(fn ($g) => is_array($g) && (filled($g['name'] ?? null) || filled($g['role'] ?? null)))
                ->values()->all(),
        ]);

        $statuses   = $creating
            ? [EventStatus::Draft->value, EventStatus::Published->value]
            : [EventStatus::Draft->value, EventStatus::Published->value, EventStatus::Postponed->value, EventStatus::Cancelled->value];
        $freeImages = max(Event::MAX_IMAGES - $existingImages, 0);

        $imageMessages = collect(EventService::imageMessages())
            ->mapWithKeys(fn ($m, $k) => [str_replace('image.', 'images.*.', $k) => $m])->all();

        $data = $request->validate([
            ...EventService::rules(currentType: $currentType),
            'status'   => ['required', Rule::in($statuses)],
            'images'   => ['nullable', 'array', 'max:' . $freeImages],
            'images.*' => EventService::imageRules(),
        ], [
            ...EventService::messages(),
            ...$imageMessages,
            'images.max'       => $creating
                ? __('events.validation.images_max')
                : __('events.validation.images_left', ['count' => $freeImages]),
            'starts_at.date'   => __('events.validation.starts_at_date'),
            'ends_at.date'     => __('events.validation.ends_at_date'),
        ]);
        unset($data['images']);
        $data['comments_enabled'] = $request->boolean('comments_enabled');

        return $data;
    }

    private function find(string $id): Event
    {
        $event = Event::with(['organizer', 'images'])->find($id);
        abort_if(!$event || !$event->isVisibleTo(Auth::user()), 404);

        return $event;
    }

    private function findComment(string $id, string $commentId): EventComment
    {
        $event   = $this->find($id);
        $comment = $event->comments()->with(['event', 'testimony'])->find($commentId);
        abort_if(!$comment, 404);

        return $comment;
    }

    private function attempt(callable $action, bool $withInput = false): RedirectResponse
    {
        try {
            return $action();
        } catch (EventActionException $e) {
            abort_if($e->status() === 404, 404);
            $back = back()->with('error', $e->getMessage());

            return $withInput ? $back->withInput() : $back;
        }
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\LiveStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\EventParticipation;
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
    public function index(Request $request): View
    {
        $user      = Auth::user();
        $canCreate = Event::canBeCreatedBy($user);
        $tabs      = ['upcoming' => 'À venir', 'past' => 'Passés'];
        // « Mes événements » : ceux que la personne gère (organisateur, organisation gérée, co-gestionnaire).
        if ($canCreate || ($user && Event::query()->managedBy($user)->exists())) $tabs['mine'] = 'Mes événements';
        if ($user) $tabs['going'] = "J'y participe";

        $tab  = array_key_exists($request->query('onglet'), $tabs) ? $request->query('onglet') : 'upcoming';
        $type = EventType::tryFrom((string) $request->query('type'));
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
        if ($type) $query->where('type', $type->value);
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
            'type'      => $type?->value,
            'q'         => $q,
            'canCreate' => $canCreate,
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
            'reportReasons' => Event::REPORT_REASONS,
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
            ? 'Événement enregistré comme brouillon : il reste visible de vous seul.'
            : 'Événement publié.');
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

        $data  = $this->validated($request, creating: false, existingImages: $event->images->count());

        try {
            $this->events->update($event, $request->user(), $data);
            foreach ((array) $request->file('images', []) as $file) {
                $this->events->addImage($event, $request->user(), $file);
            }
        } catch (EventActionException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('events.show', $event->id)->with('success', 'Événement mis à jour.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);

        return $this->attempt(function () use ($request, $event) {
            $this->events->delete($event, $request->user());

            return redirect()->route('events.index')->with('success', 'Événement supprimé.');
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
            'user_id.required' => 'Choisissez une personne.',
            'user_id.uuid'     => 'Choisissez une personne dans la liste.',
        ]);

        return $this->attempt(function () use ($request, $event) {
            $target = $this->events->addManager($event, $request->user(), $request->input('user_id'));

            return redirect()->to(route('events.show', $event->id) . '#co-gestionnaires')
                ->with('success', "{$target->display_name} peut maintenant gérer cet événement.");
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
                return redirect()->route('events.index')->with('success', "Vous ne gérez plus cet événement.");
            }

            return redirect()->to(route('events.show', $event->id) . ($self ? '' : '#co-gestionnaires'))
                ->with('success', $self ? 'Vous ne gérez plus cet événement.' : 'Co-gestionnaire retiré.');
        });
    }

    // ─── Images de couverture ────────────────────────────────────────────────

    public function storeImage(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);
        $request->validate(['image' => EventService::imageRules()], EventService::IMAGE_MESSAGES);

        return $this->attempt(function () use ($request, $event) {
            $this->events->addImage($event, $request->user(), $request->file('image'));

            return back()->with('success', 'Image ajoutée.');
        });
    }

    public function destroyImage(Request $request, string $id, string $image): RedirectResponse
    {
        $event = $this->find($id);

        return $this->attempt(function () use ($request, $event, $image) {
            $this->events->removeImage($event, $request->user(), $image);

            return back()->with('success', 'Image retirée.');
        });
    }

    public function coverImage(Request $request, string $id, string $image): RedirectResponse
    {
        $event = $this->find($id);

        return $this->attempt(function () use ($request, $event, $image) {
            $this->events->makeCover($event, $request->user(), $image);

            return back()->with('success', 'Image placée en premier.');
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
                ? 'Votre participation est enregistrée.'
                : 'Réponse enregistrée.');
        });
    }

    public function cancelParticipation(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);
        $this->events->cancelParticipation($event, $request->user());

        return back()->with('success', 'Réponse retirée.');
    }

    // ─── Commentaires ────────────────────────────────────────────────────────

    public function storeComment(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);
        $request->validate(['body' => 'required|string|min:2|max:3000'], [
            'body.required' => 'Écrivez votre message.',
            'body.min'      => 'Votre message est trop court.',
            'body.max'      => '3 000 caractères au plus.',
        ]);

        return $this->attempt(function () use ($request, $event) {
            $this->events->comment($event, $request->user(), $request->input('body'));

            return redirect()->route('events.show', [$event->id, 'section' => 'commentaires'])
                ->with('success', 'Merci pour votre témoignage !');
        }, withInput: true);
    }

    public function destroyComment(Request $request, string $id, string $comment): RedirectResponse
    {
        $item = $this->findComment($id, $comment);

        return $this->attempt(function () use ($request, $item) {
            $this->events->deleteComment($item, $request->user());

            return back()->with('success', 'Commentaire supprimé.');
        });
    }

    /** Enregistre un commentaire comme témoignage officiel de l'événement (gestionnaires). */
    public function promoteComment(Request $request, string $id, string $comment): RedirectResponse
    {
        $item = $this->findComment($id, $comment);
        $data = $request->validate([
            'title'    => 'nullable|string|max:200',
            'category' => 'nullable|string|exists:categories,slug',
        ], ['category.exists' => "La catégorie choisie n'existe plus."]);

        return $this->attempt(function () use ($request, $item, $data) {
            $this->events->promote($item, $request->user(), array_filter($data));

            return back()->with('success', $request->user()->canModerate()
                ? 'Témoignage enregistré et publié.'
                : 'Témoignage enregistré. Il sera publié après relecture par la modération.');
        });
    }

    // ─── Affichage de la liste (cartes / liste compacte) ─────────────────────

    public function layout(Request $request): RedirectResponse
    {
        $data = $request->validate(['layout' => ['required', Rule::in(array_keys(EventLayout::LABELS))]]);

        return redirect()->back(fallback: route('events.index'))
            ->withCookie(cookie(EventLayout::COOKIE, $data['layout'], EventLayout::LIFETIME));
    }

    // ─── Report et annulation (gestionnaires) ────────────────────────────────

    public function postpone(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);
        $tbd   = $request->boolean('date_tbd');
        $data  = $request->validate(EventService::postponeRules($tbd), EventService::POSTPONE_MESSAGES);

        return $this->attempt(function () use ($request, $event, $data, $tbd) {
            $this->events->postpone($event, $request->user(),
                $tbd ? null : $data['starts_at'], $tbd ? null : $data['ends_at'], $data['reason'] ?? null);

            return redirect()->route('events.show', $event->id)->with('success', $tbd
                ? 'Événement reporté : date à confirmer. Les participants sont prévenus.'
                : 'Événement reporté. Les participants sont prévenus.');
        }, withInput: true);
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);
        $data  = $request->validate(['reason' => 'nullable|string|max:500'], EventService::POSTPONE_MESSAGES);

        return $this->attempt(function () use ($request, $event, $data) {
            $this->events->cancel($event, $request->user(), $data['reason'] ?? null);

            return redirect()->route('events.show', $event->id)->with('success', 'Événement annulé. Les participants sont prévenus.');
        });
    }

    // ─── Signalement ─────────────────────────────────────────────────────────

    public function report(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);
        $data  = $request->validate(EventModeration::reportRules(), EventModeration::MESSAGES);

        return $this->attempt(function () use ($request, $event, $data) {
            app(EventModeration::class)->report($event, $request->user(), $data['reason'], $data['comment'] ?? null);
            $event->refresh();

            // Masqué automatiquement : la page n'est plus visible pour la personne.
            return $event->isVisibleTo($request->user())
                ? back()->with('success', 'Merci. Votre signalement sera examiné par la modération.')
                : redirect()->route('events.index')->with('success', 'Merci. Votre signalement sera examiné par la modération.');
        });
    }

    // ─── Sondage d'après l'événement ─────────────────────────────────────────

    public function review(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);
        $data  = $request->validate(EventModeration::reviewRules(), EventModeration::MESSAGES);

        return $this->attempt(function () use ($request, $event, $data) {
            app(EventModeration::class)->review($event, $request->user(), $data);

            return redirect()->to(route('events.show', $event->id) . '#votre-avis')->with('success', 'Merci pour votre avis !');
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
            'reasons'     => Event::REPORT_REASONS,
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

            return back()->with('success', 'Événement masqué.');
        });
    }

    public function restore(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);

        return $this->attempt(function () use ($request, $event) {
            app(EventModeration::class)->restore($event, $request->user());

            return back()->with('success', 'Événement rétabli.');
        });
    }

    public function dismiss(Request $request, string $id): RedirectResponse
    {
        $event = $this->find($id);

        return $this->attempt(function () use ($request, $event) {
            app(EventModeration::class)->dismiss($event, $request->user());

            return back()->with('success', 'Signalements classés : l’événement reste en ligne.');
        });
    }

    // ─── Outils (aussi utilisés par les vues) ────────────────────────────────

    /** « sam. 12 oct. 2026 · 18:00 – 22:00 », ou deux dates complètes si l'événement dure plusieurs jours. */
    public static function dateRange(Event $event, bool $original = false): string
    {
        if (!$original && $event->isDateTbd()) return 'Date à confirmer';
        $start = $original ? $event->original_starts_at : $event->starts_at;
        $end   = $original ? $event->original_ends_at : $event->ends_at;
        if (!$start) return '';
        $text = $start->translatedFormat('D j M Y · H:i');
        if (!$end || $end->equalTo($start)) return $text;

        return $start->isSameDay($end)
            ? $text . ' – ' . $end->format('H:i')
            : $text . ' → ' . $end->translatedFormat('D j M Y · H:i');
    }

    /** Lieu lisible : « Stade de l'Amitié, Cotonou, Bénin ». */
    public static function place(Event $event): string
    {
        return collect([$event->location, $event->city, $event->country])->filter(fn ($v) => filled($v))->implode(', ');
    }

    private function validated(Request $request, bool $creating, int $existingImages = 0): array
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

        $imageMessages = collect(EventService::IMAGE_MESSAGES)
            ->mapWithKeys(fn ($m, $k) => [str_replace('image.', 'images.*.', $k) => $m])->all();

        $data = $request->validate([
            ...EventService::rules(),
            'status'   => ['required', Rule::in($statuses)],
            'images'   => ['nullable', 'array', 'max:' . $freeImages],
            'images.*' => EventService::imageRules(),
        ], [
            ...EventService::MESSAGES,
            ...$imageMessages,
            'images.max'       => $creating
                ? 'Six images au plus.'
                : "Vous pouvez encore ajouter {$freeImages} image(s) au plus.",
            'starts_at.date'   => 'Date de début non valide.',
            'ends_at.date'     => 'Date de fin non valide.',
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

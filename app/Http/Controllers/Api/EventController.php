<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventType;
use App\Enums\LiveStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventCommentResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\TestimonyResource;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\EventParticipation;
use App\Models\User;
use App\Services\EventActionException;
use App\Services\EventService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Événements chrétiens (croisades, conférences, camps…), au format JSON { success, data, message }.
 * Voir docs/fonctionnalites/evenements.md
 */
class EventController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EventService $events) {}

    /**
     * Liste : `scope` = upcoming (défaut : à venir et en cours) | past | mine (que je gère)
     * | going (auxquels je participe) | following (à venir, organisés par les comptes que je suis :
     * « Mon fil ») ; filtres `type`, `q`.
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $this->user($request);
        $scope = $request->query('scope', 'upcoming');

        $query = Event::query()->with($this->relations());

        match ($scope) {
            'mine'  => $user ? $query->managedBy($user)->orderByDesc('starts_at') : $query->whereRaw('1 = 0'),
            'going' => $user
                ? $query->published()->whereHas('participations', fn ($q) => $q->where('user_id', $user->id)->where('status', EventParticipation::GOING))->orderBy('starts_at')
                : $query->whereRaw('1 = 0'),
            'following' => $user
                ? $query->published()->upcoming()->followedBy($user)
                : $query->whereRaw('1 = 0'),
            'past'  => $query->published()->past(),
            default => $query->published()->upcoming(),
        };

        if ($type = EventType::tryFrom((string) $request->query('type'))) {
            $query->where('type', $type->value);
        }
        if (filled($q = trim((string) $request->query('q')))) {
            $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")
                ->orWhere('city', 'like', "%{$q}%")
                ->orWhere('location', 'like', "%{$q}%"));
        }

        $limit  = min(max((int) $request->query('limit', 20), 1), 50);
        $events = $query->paginate($limit);

        return $this->paginated(EventResource::collection($events->items()), [
            ...$this->meta($events),
            'canCreate' => Event::canBeCreatedBy($user),
            // Au nom de qui créer un événement (soi-même et / ou les organisations vérifiées gérées).
            'organizations' => $this->creatableFor($user),
            'types'     => collect(EventType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->values(),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return $this->success(new EventResource($this->find($id, $request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(EventService::rules(), EventService::MESSAGES);

        return $this->attempt(function () use ($request, $data) {
            $event = $this->events->create($this->user($request), $data);

            return $this->created(new EventResource($event->load($this->relations())), 'Événement créé.');
        });
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $event = $this->find($id, $request);
        $data  = $request->validate(EventService::rules(partial: true), EventService::MESSAGES);

        return $this->attempt(function () use ($request, $event, $data) {
            $event = $this->events->update($event, $this->user($request), $data);

            return $this->success(new EventResource($event->load($this->relations())), 'Événement mis à jour.');
        });
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $event = $this->find($id, $request);

        return $this->attempt(function () use ($request, $event) {
            $this->events->delete($event, $this->user($request));

            return $this->success(null, 'Événement supprimé.');
        });
    }

    // ─── Co-gestionnaires (2 au plus) ────────────────────────────────────────

    public function addManager(Request $request, string $id): JsonResponse
    {
        $event = $this->find($id, $request);
        $request->validate(['user_id' => 'required|uuid'], ['user_id.required' => 'Choisissez une personne.']);

        return $this->attempt(function () use ($request, $event) {
            $target = $this->events->addManager($event, $this->user($request), $request->input('user_id'));

            return $this->success(new EventResource($event->refresh()->load($this->relations())),
                "{$target->display_name} peut maintenant gérer l'événement.");
        });
    }

    public function removeManager(Request $request, string $id, string $userId): JsonResponse
    {
        $event = $this->find($id, $request);

        return $this->attempt(function () use ($request, $event, $userId) {
            $this->events->removeManager($event, $this->user($request), $userId);

            return $this->success(new EventResource($event->refresh()->load($this->relations())), 'Co-gestionnaire retiré.');
        });
    }

    // ─── Images de couverture ────────────────────────────────────────────────

    public function storeImage(Request $request, string $id): JsonResponse
    {
        $event = $this->find($id, $request);
        $request->validate(['image' => EventService::imageRules()], EventService::IMAGE_MESSAGES);

        return $this->attempt(function () use ($request, $event) {
            $this->events->addImage($event, $this->user($request), $request->file('image'));

            return $this->created(new EventResource($event->refresh()->load($this->relations())), 'Image ajoutée.');
        });
    }

    public function destroyImage(Request $request, string $id, string $imageId): JsonResponse
    {
        $event = $this->find($id, $request);

        return $this->attempt(function () use ($request, $event, $imageId) {
            $this->events->removeImage($event, $this->user($request), $imageId);

            return $this->success(new EventResource($event->refresh()->load($this->relations())), 'Image retirée.');
        });
    }

    public function coverImage(Request $request, string $id, string $imageId): JsonResponse
    {
        $event = $this->find($id, $request);

        return $this->attempt(function () use ($request, $event, $imageId) {
            $this->events->makeCover($event, $this->user($request), $imageId);

            return $this->success(new EventResource($event->refresh()->load($this->relations())), 'Image placée en premier.');
        });
    }

    // ─── Participation ───────────────────────────────────────────────────────

    public function participate(Request $request, string $id): JsonResponse
    {
        $event = $this->find($id, $request);
        $request->validate(['status' => 'required|in:going,not_going']);

        return $this->attempt(function () use ($request, $event) {
            $status = $this->events->participate($event, $this->user($request), $request->input('status'));

            return $this->success(new EventResource($event->refresh()->load($this->relations())),
                $status === EventParticipation::GOING ? 'Votre participation est enregistrée.' : 'Réponse enregistrée.');
        });
    }

    public function cancelParticipation(Request $request, string $id): JsonResponse
    {
        $event = $this->find($id, $request);
        $this->events->cancelParticipation($event, $this->user($request));

        return $this->success(new EventResource($event->refresh()->load($this->relations())), 'Réponse retirée.');
    }

    /** Participants (gestionnaires) : `status` = going (défaut) | not_going. */
    public function participants(Request $request, string $id): JsonResponse
    {
        $event = $this->find($id, $request);

        return $this->attempt(function () use ($request, $event) {
            $this->events->ensureManager($event, $this->user($request));
            $status = $request->query('status') === 'not_going' ? EventParticipation::NOT_GOING : EventParticipation::GOING;
            $items  = $event->participations()->with('user')->where('status', $status)->latest()->paginate(50);

            return $this->paginated(collect($items->items())->map(fn ($p) => [
                'id'          => $p->user_id,
                'displayName' => $p->user?->display_name ?? 'Anonyme',
                'initials'    => $p->user?->initials,
                'avatarUrl'   => $p->user?->avatar_url,
                'respondedAt' => $p->updated_at?->toIso8601String(),
            ])->values(), $this->meta($items));
        });
    }

    // ─── Commentaires ────────────────────────────────────────────────────────

    /** Les plus récents d'abord. */
    public function comments(Request $request, string $id): JsonResponse
    {
        $event = $this->find($id, $request);
        $items = $event->comments()->with('user')->latest()->paginate(min((int) $request->query('limit', 30), 50));

        return $this->paginated(EventCommentResource::collection($items->items()), $this->meta($items));
    }

    public function storeComment(Request $request, string $id): JsonResponse
    {
        $event = $this->find($id, $request);
        $request->validate(['body' => 'required|string|min:2|max:3000'], [
            'body.required' => 'Écrivez votre message.',
            'body.max'      => '3 000 caractères au plus.',
        ]);

        return $this->attempt(function () use ($request, $event) {
            $comment = $this->events->comment($event, $this->user($request), $request->input('body'));

            return $this->created(new EventCommentResource($comment), 'Merci pour votre témoignage !');
        });
    }

    public function destroyComment(Request $request, string $id, string $commentId): JsonResponse
    {
        $comment = $this->findComment($id, $commentId, $request);

        return $this->attempt(function () use ($request, $comment) {
            $this->events->deleteComment($comment, $this->user($request));

            return $this->success(null, 'Commentaire supprimé.');
        });
    }

    /** Enregistre le commentaire comme témoignage officiel (gestionnaires). */
    public function promoteComment(Request $request, string $id, string $commentId): JsonResponse
    {
        $comment = $this->findComment($id, $commentId, $request);
        $data = $request->validate([
            'title'     => 'nullable|string|max:200',
            'body_text' => 'nullable|string|max:10000',
            'category'  => 'nullable|string|exists:categories,slug',
        ]);

        return $this->attempt(function () use ($request, $comment, $data) {
            $actor     = $this->user($request);
            $testimony = $this->events->promote($comment, $actor, $data);

            return $this->created(new TestimonyResource($testimony), $actor->canModerate()
                ? 'Témoignage enregistré et publié.'
                : 'Témoignage enregistré. Il sera publié après relecture par la modération.');
        });
    }

    // ─── Témoignages officiels ───────────────────────────────────────────────

    public function testimonies(Request $request, string $id): JsonResponse
    {
        $event = $this->find($id, $request);
        $items = $event->testimoniesVisibleTo($this->user($request))->with('user')->latest()
            ->paginate(min((int) $request->query('limit', 20), 50));

        return $this->paginated(TestimonyResource::collection($items->items()), $this->meta($items));
    }

    // ─── Outils ──────────────────────────────────────────────────────────────

    /** @return list<array{id: string, displayName: ?string, avatarUrl: ?string}> */
    private function creatableFor(?User $user): array
    {
        if (!$user) return [];
        $self = ($user->isAdmin() || $user->isVerified()) ? collect([$user]) : collect();

        return $self->concat($user->verifiedOrganizationsManaged()->get())
            ->map(fn (User $o) => [
                'id'          => $o->id,
                'displayName' => $o->organization_name ?: $o->display_name,
                'avatarUrl'   => $o->avatar_url,
            ])->values()->all();
    }

    private function relations(): array
    {
        return [
            'organizer', 'images', 'managers',
            'lives' => fn ($q) => $q->whereIn('status', [LiveStatus::Live->value, LiveStatus::Preparing->value])->latest(),
        ];
    }

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

    private function find(string $id, Request $request): Event
    {
        $event = Event::with($this->relations())->find($id);
        abort_if(!$event || !$event->isVisibleTo($this->user($request)), 404, 'Événement introuvable.');

        return $event;
    }

    private function findComment(string $id, string $commentId, Request $request): EventComment
    {
        $event   = $this->find($id, $request);
        $comment = $event->comments()->with('event')->find($commentId);
        abort_if(!$comment, 404, 'Commentaire introuvable.');

        return $comment;
    }

    private function attempt(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (EventActionException $e) {
            return $this->error($e->getMessage(), $e->status());
        }
    }
}

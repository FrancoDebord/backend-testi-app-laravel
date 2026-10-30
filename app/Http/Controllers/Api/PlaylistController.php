<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Testimony;
use App\Models\TestimonyPlaylist;
use App\Models\User;
use App\Services\ContentActionException;
use App\Services\TestimonyPlaylists;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Listes de témoignages (comme les playlists YouTube) pour l'application.
 * Voir docs/fonctionnalites/listes-de-temoignages.md
 */
class PlaylistController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TestimonyPlaylists $playlists) {}

    /** GET /playlists/mine : listes de la personne connectée (tous ses témoignages comptés). */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success($this->playlists->ofUser($user)->map(function (TestimonyPlaylist $p) use ($user) {
            $p->setAttribute('visible_count', $p->testimonies_count);
            $p->setRelation('firstTestimony', $p->testimonies()->first());

            return $this->playlists->playlistPayload($p->setRelation('user', $user), $user);
        })->values());
    }

    /** GET /users/{id}/playlists : listes d'un compte qui ont au moins un témoignage visible. */
    public function ofUser(Request $request, string $id): JsonResponse
    {
        $owner = User::find($id);
        if (!$owner) return $this->notFound();
        $viewer = $request->user('sanctum');

        return $this->success($this->playlists->publicOf($owner, $viewer)
            ->map(fn (TestimonyPlaylist $p) => $this->playlists->playlistPayload($p->setRelation('user', $owner), $viewer))->values());
    }

    /** GET /playlists/{id} : la liste et ses témoignages visibles, dans l'ordre. */
    public function show(Request $request, string $id): JsonResponse
    {
        $playlist = TestimonyPlaylist::with('user')->find($id);
        $viewer   = $request->user('sanctum');
        if (!$playlist) return $this->notFound();

        $payload = $this->playlists->playlistPayload($playlist, $viewer, withItems: true);
        if ($payload['count'] === 0 && !$playlist->isOwnedBy($viewer)) {
            return $this->notFound();
        }

        return $this->success($payload);
    }

    /** POST /playlists { title, description?, testimonyIds? } */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(TestimonyPlaylists::rules() + ['testimonyIds' => ['nullable', 'array', 'max:' . TestimonyPlaylist::ITEMS_MAX], 'testimonyIds.*' => ['uuid']]);
        $user = $request->user();

        return $this->attempt(function () use ($data, $user) {
            $playlist = DB::transaction(function () use ($data, $user) {
                $playlist = $this->playlists->create($user, $data);
                foreach (Testimony::whereIn('id', $data['testimonyIds'] ?? [])->get()->sortBy(fn ($t) => array_search($t->id, $data['testimonyIds'], true)) as $testimony) {
                    $this->playlists->add($playlist, $testimony, $user);
                }

                return $playlist;
            });

            return $this->created($this->playlists->playlistPayload($playlist->load('user'), $user, withItems: true), __('playlists.flash.created'));
        });
    }

    /** PUT /playlists/{id} { title?, description?, testimonyIds? (ordre complet) } */
    public function update(Request $request, string $id): JsonResponse
    {
        $playlist = TestimonyPlaylist::find($id);
        if (!$playlist) return $this->notFound();
        $data = $request->validate(TestimonyPlaylists::rules(creating: false) + ['testimonyIds' => ['nullable', 'array'], 'testimonyIds.*' => ['uuid']]);

        return $this->attempt(function () use ($playlist, $data, $request) {
            $this->playlists->update($playlist, $request->user(), array_intersect_key($data, array_flip(['title', 'description'])));
            if (isset($data['testimonyIds'])) {
                $this->playlists->reorder($playlist, $data['testimonyIds'], $request->user());
            }

            return $this->success($this->playlists->playlistPayload($playlist->fresh('user'), $request->user(), withItems: true), __('playlists.flash.updated'));
        });
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $playlist = TestimonyPlaylist::find($id);
        if (!$playlist) return $this->notFound();

        return $this->attempt(function () use ($playlist, $request) {
            $this->playlists->delete($playlist, $request->user());

            return $this->success(null, __('playlists.flash.deleted'));
        });
    }

    /** POST /playlists/{id}/testimonies { testimonyId } : ajouté à la fin (sans effet s'il y est déjà). */
    public function addItem(Request $request, string $id): JsonResponse
    {
        $playlist = TestimonyPlaylist::find($id);
        if (!$playlist) return $this->notFound();
        $data = $request->validate(['testimonyId' => ['required', 'uuid']]);
        $testimony = Testimony::find($data['testimonyId']);
        if (!$testimony) return $this->notFound();

        return $this->attempt(function () use ($playlist, $testimony, $request) {
            $this->playlists->add($playlist, $testimony, $request->user());

            return $this->success($this->playlists->playlistPayload($playlist->fresh('user'), $request->user(), withItems: true), __('playlists.flash.added', ['title' => $playlist->title]));
        });
    }

    public function removeItem(Request $request, string $id, string $testimonyId): JsonResponse
    {
        $playlist = TestimonyPlaylist::find($id);
        if (!$playlist) return $this->notFound();

        return $this->attempt(function () use ($playlist, $testimonyId, $request) {
            $this->playlists->remove($playlist, $testimonyId, $request->user());

            return $this->success($this->playlists->playlistPayload($playlist->fresh('user'), $request->user(), withItems: true), __('playlists.flash.removed'));
        });
    }

    private function attempt(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (ContentActionException $e) {
            return $this->error($e->getMessage(), $e->status(), $e->errors());
        }
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Testimony;
use App\Models\TestimonyPlaylist;
use App\Services\ContentActionException;
use App\Services\TestimonyPlaylists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Listes de témoignages sur le site : « Mes listes », page publique d'une liste, ajout depuis la page
 * d'un témoignage. Voir docs/fonctionnalites/listes-de-temoignages.md
 */
class PlaylistController extends Controller
{
    public function __construct(private readonly TestimonyPlaylists $playlists) {}

    /** GET /mes-listes */
    public function mine(Request $request): View
    {
        $playlists = $this->playlists->ofUser($request->user());

        return view('playlists.mine', compact('playlists'));
    }

    /** GET /listes/{id} : ouverte à tous ; seuls les témoignages que la personne peut voir y figurent. */
    public function show(Request $request, string $id): View
    {
        $playlist = $this->find($id);
        $viewer   = $request->user();
        $items    = $this->playlists->playable($playlist, $viewer);
        $isOwner  = $playlist->isOwnedBy($viewer);
        abort_if($items->isEmpty() && !$isOwner, 404);

        // L'auteur voit aussi ses témoignages pas encore publiés, et peut ajouter les autres.
        $allItems  = $isOwner ? $playlist->testimonies()->with(['user', 'category'])->get() : $items;
        $addable   = $isOwner
            ? Testimony::where('user_id', $viewer->id)->withoutJournal()->whereNotIn('id', $allItems->pluck('id'))->latest()->limit(100)->get(['id', 'title'])
            : collect();

        return view('playlists.show', [
            'playlist'   => $playlist->loadMissing('user'),
            'items'      => $allItems,
            'visibleIds' => $items->pluck('id')->all(),
            'isOwner'    => $isOwner,
            'addable'    => $addable,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(TestimonyPlaylists::rules());
        $playlist = $this->attempt(fn () => $this->playlists->create($request->user(), $data));

        return redirect()->route('playlists.show', $playlist->id)->with('success', __('playlists.flash.created'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(TestimonyPlaylists::rules());
        $this->attempt(fn () => $this->playlists->update($this->find($id), $request->user(), $data));

        return back()->with('success', __('playlists.flash.updated'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->attempt(fn () => $this->playlists->delete($this->find($id), $request->user()));

        return redirect()->route('playlists.mine')->with('success', __('playlists.flash.deleted'));
    }

    /** POST /listes/{id}/temoignages { testimony_id } */
    public function addItem(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(['testimony_id' => 'required|uuid']);
        $playlist  = $this->find($id);
        $testimony = Testimony::findOrFail($data['testimony_id']);
        $this->attempt(fn () => $this->playlists->add($playlist, $testimony, $request->user()));

        return back()->with('success', __('playlists.flash.added', ['title' => $playlist->title]));
    }

    public function removeItem(Request $request, string $id, string $testimonyId): RedirectResponse
    {
        $this->attempt(fn () => $this->playlists->remove($this->find($id), $testimonyId, $request->user()));

        return back()->with('success', __('playlists.flash.removed'));
    }

    /** POST /listes/{id}/temoignages/{testimonyId}/deplacer { direction: up|down } */
    public function moveItem(Request $request, string $id, string $testimonyId): RedirectResponse
    {
        $data = $request->validate(['direction' => 'required|in:up,down']);
        $this->attempt(fn () => $this->playlists->move($this->find($id), $testimonyId, $data['direction'], $request->user()));

        return back()->with('success', __('playlists.flash.moved'));
    }

    /**
     * POST /testimonies/{id}/listes { playlist_id | playlist_title } : depuis la page du témoignage, l'auteur
     * l'ajoute à une de ses listes ou à une nouvelle liste.
     */
    public function attachTestimony(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(TestimonyPlaylists::composerRules());
        $testimony = Testimony::findOrFail($id);
        abort_unless($testimony->user_id === $request->user()->id, 403);
        if (blank($data['playlist_id'] ?? null) && blank($data['playlist_title'] ?? null)) {
            throw ValidationException::withMessages(['playlist_id' => __('playlists.errors.choose_list')]);
        }

        // Nouvelle liste et ajout ensemble : un refus ne laisse pas de liste vide.
        $playlist = $this->attempt(fn () => \Illuminate\Support\Facades\DB::transaction(function () use ($request, $data, $testimony) {
            $playlist = $this->playlists->resolveForComposer($request->user(), $data['playlist_id'] ?? null, $data['playlist_title'] ?? null);
            $this->playlists->add($playlist, $testimony, $request->user());

            return $playlist;
        }));

        return back()->with('success', __('playlists.flash.added', ['title' => $playlist->title]));
    }

    private function find(string $id): TestimonyPlaylist
    {
        abort_unless(Str::isUuid($id), 404);

        return TestimonyPlaylist::findOrFail($id);
    }

    /** Refus du service : 403 tel quel, sinon message sous le champ concerné. */
    private function attempt(callable $action): mixed
    {
        try {
            return $action();
        } catch (ContentActionException $e) {
            abort_if($e->status() === 403, 403, $e->getMessage());
            throw ValidationException::withMessages($e->errors() ?? ['playlist' => $e->getMessage()]);
        }
    }
}

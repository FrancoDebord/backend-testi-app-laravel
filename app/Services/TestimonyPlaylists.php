<?php

namespace App\Services;

use App\Models\Testimony;
use App\Models\TestimonyPlaylist;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Listes de témoignages (comme les playlists YouTube) : création, ajout, retrait, ordre, et lecture
 * enchaînée (les autres témoignages de la liste sont proposés et lus automatiquement).
 * Un compte ne met dans ses listes que ses propres témoignages, jamais ceux du carnet privé.
 * Voir docs/fonctionnalites/listes-de-temoignages.md
 */
class TestimonyPlaylists
{
    /** Règles de validation partagées par le site et l'API. */
    public static function rules(bool $creating = true): array
    {
        return [
            'title'       => [$creating ? 'required' : 'sometimes', 'string', 'max:' . TestimonyPlaylist::TITLE_MAX],
            'description' => ['nullable', 'string', 'max:' . TestimonyPlaylist::DESCRIPTION_MAX],
        ];
    }

    /** Champs facultatifs du formulaire de publication : liste existante ou nouvelle liste. */
    public static function composerRules(): array
    {
        return [
            'playlist_id'    => ['nullable', 'uuid'],
            'playlist_title' => ['nullable', 'string', 'max:' . TestimonyPlaylist::TITLE_MAX],
        ];
    }

    /** Listes d'un compte, de la plus récemment modifiée à la plus ancienne, avec leur nombre de témoignages. */
    public function ofUser(User $user): Collection
    {
        return TestimonyPlaylist::where('user_id', $user->id)
            ->withCount('testimonies')
            ->latest('updated_at')
            ->get();
    }

    /**
     * Listes d'un compte montrées aux autres : seulement celles qui ont au moins un témoignage visible,
     * avec ce nombre (`visible_count`) et la couverture du premier.
     */
    public function publicOf(User $owner, ?User $viewer): Collection
    {
        return $this->ofUser($owner)
            ->map(function (TestimonyPlaylist $playlist) use ($viewer) {
                $items = $this->playable($playlist, $viewer);
                $playlist->setAttribute('visible_count', $items->count());
                $playlist->setRelation('firstTestimony', $items->first());

                return $playlist;
            })
            ->filter(fn (TestimonyPlaylist $p) => $p->visible_count > 0 || $p->isOwnedBy($viewer))
            ->values();
    }

    /** @throws ContentActionException */
    public function create(User $user, array $data): TestimonyPlaylist
    {
        return TestimonyPlaylist::create([
            'user_id'     => $user->id,
            'title'       => $this->cleanTitle($data['title'] ?? ''),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
        ]);
    }

    /** @throws ContentActionException */
    public function update(TestimonyPlaylist $playlist, User $user, array $data): TestimonyPlaylist
    {
        $this->ensureOwner($playlist, $user);
        $changes = [];
        if (array_key_exists('title', $data)) {
            $changes['title'] = $this->cleanTitle((string) $data['title']);
        }
        if (array_key_exists('description', $data)) {
            $changes['description'] = filled($data['description']) ? trim($data['description']) : null;
        }
        $playlist->update($changes);

        return $playlist;
    }

    /** Supprime la liste ; les témoignages restent publiés. */
    public function delete(TestimonyPlaylist $playlist, User $user): void
    {
        $this->ensureOwner($playlist, $user);
        $playlist->delete();
    }

    /**
     * Ajoute un témoignage à la fin de la liste (sans effet s'il y est déjà).
     *
     * @throws ContentActionException
     */
    public function add(TestimonyPlaylist $playlist, Testimony $testimony, User $user): void
    {
        $this->ensureOwner($playlist, $user);
        if ($testimony->user_id !== $playlist->user_id) {
            throw new ContentActionException(__('playlists.errors.not_your_testimony'), 403);
        }
        if ($testimony->isInJournal()) {
            throw new ContentActionException(__('playlists.errors.journal'), 422);
        }

        DB::transaction(function () use ($playlist, $testimony) {
            $items = DB::table('testimony_playlist_items')->where('playlist_id', $playlist->id)->lockForUpdate();
            if ((clone $items)->where('testimony_id', $testimony->id)->exists()) {
                return;
            }
            if ((clone $items)->count() >= TestimonyPlaylist::ITEMS_MAX) {
                throw new ContentActionException(__('playlists.errors.full', ['max' => TestimonyPlaylist::ITEMS_MAX]), 422);
            }
            $playlist->testimonies()->attach($testimony->id, ['position' => (int) (clone $items)->max('position') + 1]);
        });
        $playlist->touch();
    }

    public function remove(TestimonyPlaylist $playlist, string $testimonyId, User $user): void
    {
        $this->ensureOwner($playlist, $user);
        $playlist->testimonies()->detach($testimonyId);
        $playlist->touch();
    }

    /** Monte ou descend un témoignage d'une place (boutons du site). */
    public function move(TestimonyPlaylist $playlist, string $testimonyId, string $direction, User $user): void
    {
        $this->ensureOwner($playlist, $user);
        $ids = $playlist->testimonies()->pluck('testimonies.id')->all();
        $from = array_search($testimonyId, $ids, true);
        $to = $from === false ? false : ($direction === 'up' ? $from - 1 : $from + 1);
        if ($to === false || !isset($ids[$to])) {
            return;
        }
        [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
        $this->reorder($playlist, $ids, $user);
    }

    /**
     * Nouvel ordre complet (identifiants des témoignages de la liste) ; les identifiants inconnus sont ignorés
     * et ceux qui manquent restent à la fin, dans leur ordre actuel.
     */
    public function reorder(TestimonyPlaylist $playlist, array $orderedIds, User $user): void
    {
        $this->ensureOwner($playlist, $user);
        $current = $playlist->testimonies()->pluck('testimonies.id')->all();
        $ordered = array_values(array_unique(array_merge(array_intersect($orderedIds, $current), $current)));

        DB::transaction(function () use ($playlist, $ordered) {
            foreach ($ordered as $position => $id) {
                DB::table('testimony_playlist_items')
                    ->where('playlist_id', $playlist->id)->where('testimony_id', $id)
                    ->update(['position' => $position + 1]);
            }
        });
        $playlist->touch();
    }

    /**
     * Liste choisie au moment de publier : liste existante du compte, ou nouvelle liste si un titre est donné
     * (le titre l'emporte). Null : aucune liste (cas par défaut).
     *
     * @throws ContentActionException
     */
    public function resolveForComposer(User $user, ?string $playlistId, ?string $newTitle): ?TestimonyPlaylist
    {
        if (filled($newTitle)) {
            return $this->create($user, ['title' => $newTitle]);
        }
        if (blank($playlistId)) {
            return null;
        }
        $playlist = TestimonyPlaylist::where('user_id', $user->id)->find($playlistId);
        if (!$playlist) {
            throw new ContentActionException(__('playlists.errors.not_found'), 422, ['playlist_id' => [__('playlists.errors.not_found')]]);
        }

        return $playlist;
    }

    /**
     * Liste existante choisie au moment de publier, vérifiée AVANT la création du témoignage (422 sinon) ;
     * ignorée si un titre de nouvelle liste est donné.
     *
     * @throws ContentActionException
     */
    public function existingForComposer(User $user, ?string $playlistId, ?string $newTitle): ?TestimonyPlaylist
    {
        return filled($newTitle) ? null : $this->resolveForComposer($user, $playlistId, null);
    }

    /**
     * Après la publication : ajoute le témoignage à la liste existante, ou crée la nouvelle liste (titre) et l'y
     * ajoute. Rien pour une entrée du carnet privé (ignorée, sans erreur).
     */
    public function publishInto(User $user, Testimony $testimony, ?TestimonyPlaylist $existing, ?string $newTitle): ?TestimonyPlaylist
    {
        if ($testimony->isInJournal()) {
            return null;
        }
        $playlist = filled($newTitle) ? $this->create($user, ['title' => $newTitle]) : $existing;
        if ($playlist) {
            $this->add($playlist, $testimony, $user);
        }

        return $playlist;
    }

    /**
     * Témoignages de la liste que cette personne peut ouvrir, dans l'ordre. Sur le site, la règle de la page
     * (accord pour le web, 404 / 410) s'applique ; dans l'application, celle de l'API.
     */
    public function playable(TestimonyPlaylist $playlist, ?User $viewer): Collection
    {
        $web = Testimony::forWebContext();

        return $playlist->testimonies()->with(['user', 'category'])->get()
            ->filter(fn (Testimony $t) => $web ? $t->webStatusFor($viewer) === 200 : $t->isVisibleTo($viewer))
            ->values();
    }

    /**
     * Liste en cours de lecture pour ce témoignage : celle demandée (?list=) si elle le contient, sinon la plus
     * récemment modifiée qui le contient. Null si le témoignage n'est dans aucune liste visible.
     *
     * @return array{playlist: TestimonyPlaylist, items: Collection, index: int, previous: ?Testimony, next: ?Testimony}|null
     */
    public function contextFor(Testimony $testimony, ?User $viewer, ?string $requestedId = null): ?array
    {
        if ($testimony->isInJournal()) {
            return null;
        }
        $query = TestimonyPlaylist::with('user')
            ->whereHas('testimonies', fn ($q) => $q->where('testimonies.id', $testimony->id));
        $playlist = (Str::isUuid((string) $requestedId) ? (clone $query)->find($requestedId) : null)
            ?? $query->latest('updated_at')->first();
        if (!$playlist) {
            return null;
        }

        $items = $this->playable($playlist, $viewer);
        $index = $items->search(fn (Testimony $t) => $t->id === $testimony->id);
        if ($index === false) {
            return null;
        }

        return [
            'playlist' => $playlist,
            'items'    => $items,
            'index'    => $index,
            'previous' => $items->get($index - 1),
            'next'     => $items->get($index + 1),
        ];
    }

    /** Représentation compacte pour l'API (lecteur de l'application). */
    public function contextPayload(array $context): array
    {
        /** @var TestimonyPlaylist $playlist */
        $playlist = $context['playlist'];

        return [
            'id'          => $playlist->id,
            'title'       => $playlist->title,
            'description' => $playlist->description,
            'owner'       => ['id' => $playlist->user_id, 'displayName' => $playlist->user?->display_name],
            'position'    => $context['index'] + 1,
            'count'       => $context['items']->count(),
            'previousId'  => $context['previous']?->id,
            'nextId'      => $context['next']?->id,
            // Lecture automatique de la liste activée par défaut (la personne peut la couper dans le lecteur).
            'autoplay'    => true,
            'items'       => $context['items']->map(fn (Testimony $t) => self::itemPayload($t))->all(),
        ];
    }

    public function playlistPayload(TestimonyPlaylist $playlist, ?User $viewer, bool $withItems = false): array
    {
        $items = $withItems || !isset($playlist->visible_count) ? $this->playable($playlist, $viewer) : null;
        $first = $items?->first() ?? ($playlist->relationLoaded('firstTestimony') ? $playlist->firstTestimony : null);

        return [
            'id'          => $playlist->id,
            'title'       => $playlist->title,
            'description' => $playlist->description,
            'owner'       => ['id' => $playlist->user_id, 'displayName' => $playlist->user?->display_name],
            'isMine'      => $playlist->isOwnedBy($viewer),
            'count'       => $items?->count() ?? (int) $playlist->visible_count,
            'coverUrl'    => $first?->cover_url,
            'shareUrl'    => rtrim((string) config('app.share_url'), '/') . route('playlists.show', $playlist->id, false),
            'updatedAt'   => $playlist->updated_at?->toIso8601String(),
            'items'       => $withItems ? $items->map(fn (Testimony $t) => self::itemPayload($t))->all() : null,
        ];
    }

    public static function itemPayload(Testimony $t): array
    {
        return [
            'id'          => $t->id,
            'title'       => $t->title,
            'type'        => $t->type->value,
            'isShort'     => $t->isShort(),
            'coverUrl'    => $t->cover_url,
            'durationSec' => (int) $t->duration_sec,
            'viewsCount'  => (int) $t->views_count,
            'publishedAt' => $t->publishedAt()?->toIso8601String(),
        ];
    }

    /** @throws ContentActionException */
    private function ensureOwner(TestimonyPlaylist $playlist, User $user): void
    {
        if (!$playlist->isOwnedBy($user)) {
            throw new ContentActionException(__('playlists.errors.forbidden'), 403);
        }
    }

    /** @throws ContentActionException */
    private function cleanTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title));
        if ($title === '') {
            throw new ContentActionException(__('playlists.errors.title_required'), 422, ['title' => [__('playlists.errors.title_required')]]);
        }

        return mb_substr($title, 0, TestimonyPlaylist::TITLE_MAX);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Enums\TestimonyStatus;
use App\Enums\TestimonyVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTestimonyRequest;
use App\Http\Resources\TestimonyResource;
use App\Models\Category;
use App\Models\MediaFile;
use App\Models\Testimony;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestimonyController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        // Fil « Pour vous » (docs/fonctionnalites/recommandations.md) : classement selon la personne,
        // les plus récents et les plus vus pour un nouveau venu.
        if ($request->query('sort') === 'for_you' && !$request->query('after') && !$request->query('q')) {
            $viewer = $request->user() ?? $request->user('sanctum');
            $limit  = min((int) $request->query('limit', 20), 50);
            $page   = max(1, (int) $request->query('page', 1));
            $feed   = app(\App\Services\Recommendations::class)->feed($viewer, $page, $limit, $request->query('type'), $request->query('category'));

            return $this->paginated(TestimonyResource::collection($feed->items()), [
                'currentPage' => $feed->currentPage(),
                'lastPage'    => $feed->lastPage(),
                'total'       => $feed->total(),
                'perPage'     => $feed->perPage(),
                'nextCursor'  => null,
            ]);
        }

        $query = Testimony::with('user')
            ->published()
            ->latest('updated_at');

        // Delta sync: return only records updated after cursor
        if ($after = $request->query('after')) {
            $query->after($after);
        }

        // Category filter
        if ($category = $request->query('category')) {
            $query->ofCategory($category);
        }

        // Type filter
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        // Search
        if ($q = $request->query('q')) {
            $query->where(fn($q2) => $q2->where('title', 'like', "%{$q}%")
                                        ->orWhere('body_text', 'like', "%{$q}%"));
        }

        // Sort
        $sort = $request->query('sort', 'recent');
        match($sort) {
            'popular'     => $query->orderByDesc('like_count'),
            'recommended' => $query->orderByDesc('views_count'),
            default       => $query->latest(),
        };

        $limit = min((int) $request->query('limit', 20), 50);
        $testimonies = $query->paginate($limit);

        return $this->paginated(
            TestimonyResource::collection($testimonies->items()),
            [
                'currentPage'  => $testimonies->currentPage(),
                'lastPage'     => $testimonies->lastPage(),
                'total'        => $testimonies->total(),
                'perPage'      => $testimonies->perPage(),
                'nextCursor'   => $testimonies->hasMorePages() ? now()->toIso8601String() : null,
            ]
        );
    }

    /**
     * Recommandations pour un témoignage (« Témoignages similaires », lecture automatique) :
     * GET /testimonies/{id}/recommendations?limit=10. Voir docs/fonctionnalites/recommandations.md
     */
    public function recommendations(Request $request, string $id): JsonResponse
    {
        $testimony = Testimony::find($id);
        if (!$testimony || (!($testimony->status->value === 'approved' && $testimony->visibility->value === 'public') && ($request->user('sanctum')?->id !== $testimony->user_id))) {
            return $this->notFound();
        }

        $limit = max(1, min((int) $request->query('limit', 10), 30));
        $items = app(\App\Services\Recommendations::class)->forTestimony($testimony, $request->user() ?? $request->user('sanctum'), $limit);

        return $this->success(TestimonyResource::collection($items));
    }

    public function featured(): JsonResponse
    {
        $testimonies = Testimony::with('user')
            ->featured()
            ->latestPublished()
            ->limit(10)
            ->get();

        return $this->success(TestimonyResource::collection($testimonies));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $testimony = Testimony::with(['user', 'moderationLogs', 'mediaFile:id,url,processing_status'])->find($id);

        if (!$testimony) return $this->notFound();

        // Route publique : l'auteur est reconnu via son jeton Sanctum.
        $viewer = $request->user() ?? $request->user('sanctum');
        // L'équipe de modération lit aussi les témoignages réservés aux abonnés (relecture, preuves) ; jamais le carnet privé.
        $isModerator = $viewer?->canModerate() && !$testimony->isInJournal();
        if ($testimony->visibility->value !== 'public' && $viewer?->id !== $testimony->user_id && !$isModerator) {
            // 404 : ne pas révéler l'existence d'une entrée du carnet privé.
            return $testimony->isInJournal() ? $this->notFound() : $this->forbidden();
        }

        // Seules les lectures d'un témoignage publié comptent (pas la relecture ni son propre carnet).
        if (!$testimony->isInJournal() && $testimony->status->value === 'approved') {
            $testimony->increment('views_count');
            \App\Services\Recommendations::recordView($viewer, $testimony);
        }

        return $this->success(new TestimonyResource($testimony));
    }

    /**
     * Mon fil (GET /feed) : comptes suivis, avec des suggestions de comptes inconnus.
     * Chaque témoignage porte `feedReason` = following | suggested. Voir docs/fonctionnalites/recommandations.md
     */
    public function personalFeed(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $limit  = min((int) $request->query('limit', 20), 50);
        [$page, $reasons] = app(\App\Services\Recommendations::class)
            ->personalFeed($viewer, (int) $request->query('page', 1), $limit);

        return $this->paginated(
            collect($page->items())->map(fn ($t) => [
                ...(new TestimonyResource($t))->toArray($request),
                'feedReason' => $reasons[$t->id] ?? 'suggested',
            ])->values(),
            [
                'currentPage' => $page->currentPage(),
                'lastPage'    => $page->lastPage(),
                'total'       => $page->total(),
                'perPage'     => $page->perPage(),
                'followingCount' => count(app(\App\Services\Recommendations::class)->followedIds($viewer)),
            ]
        );
    }

    public function store(StoreTestimonyRequest $request): JsonResponse
    {
        if (!$request->user()->canPublish()) {
            return $this->forbidden('Vous n\'avez pas la permission de publier');
        }

        // Vidéo YouTube (docs/fonctionnalites/videos-youtube.md) : administrateurs seulement.
        $youtubeId = null;
        if (filled($request->youtube_url)) {
            if (!$request->user()->isAdmin()) {
                return $this->forbidden('Seuls les administrateurs peuvent publier une vidéo YouTube.');
            }
            $youtubeId = \App\Support\YouTube::parseId($request->youtube_url);
            if (!$youtubeId) {
                return $this->error("Ce lien YouTube n'est pas reconnu.", 422, ['youtube_url' => ["Ce lien YouTube n'est pas reconnu."]]);
            }
        }

        // Rattachement à un événement : seuls ses gestionnaires (docs/fonctionnalites/evenements.md).
        try {
            $event = app(\App\Services\EventService::class)->ensureCanAttach($request->event_id, $request->user());
        } catch (\App\Services\EventActionException $e) {
            return $this->error($e->getMessage(), $e->status());
        }

        // Parole prophétique accomplie : elle doit appartenir à l'auteur (docs/fonctionnalites/paroles-prophetiques.md).
        $prophecy = null;
        if (filled($request->prophecy_id)) {
            $prophecy = $request->user()->prophecies()->find($request->prophecy_id);
            if (!$prophecy) {
                return $this->notFound('Parole prophétique introuvable.');
            }
        }

        $isJournal    = $request->visibility === TestimonyVisibility::Private->value;
        $categorySlug = $request->category ?: 'autre';
        $category     = Category::where('slug', $categorySlug)->first();

        // Versions déjà produites pour ce fichier (docs/fonctionnalites/qualites-media.md).
        $media = $this->processedMediaFor($request->media_url);

        $testimony = Testimony::create([
            'user_id'      => $request->user()->id,
            'category_id'  => $category?->id,
            'event_id'     => $event?->id,
            'title'        => $request->title,
            'type'         => $youtubeId ? 'video' : $request->type,
            'category_slug' => $categorySlug,
            'body_text'    => $request->body_text,
            'media_url'    => $youtubeId ? null : $request->media_url,
            'youtube_id'   => $youtubeId,
            'proofs_public' => $request->boolean('proofs_public'),
            'renditions'   => $youtubeId ? null : $media?->renditions,
            'cover_url'    => $request->cover_url ?: ($youtubeId ? \App\Support\YouTube::thumbnailUrl($youtubeId) : null),
            'duration_sec' => ($request->duration ?: $media?->duration_sec) ?? 0,
            'bible_verse'  => $request->bible_verse,
            'bible_ref'    => $request->verse_ref,
            'tags'         => $request->tags ?? [],
            'visibility'   => $request->visibility ?? 'public',
            // Carnet privé : brouillon, jamais soumis à la modération.
            'status'       => ($isJournal ? TestimonyStatus::Draft : TestimonyStatus::Pending)->value,
        ]);

        $testimony->load('user');

        if ($prophecy) {
            // Montrée avec le témoignage (une fois publié), si l'auteur le souhaite.
            app(\App\Services\Prophecies::class)->attachTestimony($prophecy, $testimony->id, !$isJournal && $request->boolean('prophecy_public'));
            $testimony->setRelation('prophecy', $prophecy);
        }

        if ($isJournal) {
            return $this->created(new TestimonyResource($testimony), 'Témoignage enregistré dans votre carnet privé');
        }

        // Update category count
        $category?->increment('testimony_count');

        return $this->created(new TestimonyResource($testimony), 'Témoignage soumis pour modération');
    }

    public function myTestimonies(Request $request): JsonResponse
    {
        $status = $request->query('status', 'all');

        $query = Testimony::with('user')
            ->where('user_id', $request->user()->id)
            ->withoutJournal() // le carnet privé a sa propre liste : GET /journal
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $limit = min((int) $request->query('limit', 20), 50);
        $testimonies = $query->paginate($limit);

        $pendingCount = Testimony::where('user_id', $request->user()->id)
            ->pending()
            ->count();

        return $this->paginated(
            TestimonyResource::collection($testimonies->items()),
            [
                'currentPage'  => $testimonies->currentPage(),
                'lastPage'     => $testimonies->lastPage(),
                'total'        => $testimonies->total(),
                'perPage'      => $testimonies->perPage(),
                'pendingCount' => $pendingCount,
            ]
        );
    }

    public function update(StoreTestimonyRequest $request, string $id): JsonResponse
    {
        $testimony = Testimony::find($id);

        if (!$testimony) return $this->notFound();

        if ($testimony->user_id !== $request->user()->id && !$request->user()->canModerate()) {
            return $this->forbidden();
        }

        // Nouveau fichier média : versions et durée reprises de ce fichier s'il est déjà converti.
        if ($request->filled('media_url') && $request->media_url !== $testimony->media_url) {
            $media = $this->processedMediaFor($request->media_url);
            $testimony->renditions = $media?->renditions;
            if ($media?->duration_sec) {
                $testimony->duration_sec = $media->duration_sec;
            }
        }

        $testimony->update([
            'title'        => $request->title ?? $testimony->title,
            'body_text'    => $request->body_text ?? $testimony->body_text,
            'media_url'    => $request->media_url ?? $testimony->media_url,
            'cover_url'    => $request->cover_url ?? $testimony->cover_url,
            'bible_verse'  => $request->bible_verse ?? $testimony->bible_verse,
            'bible_ref'    => $request->verse_ref ?? $testimony->bible_ref,
            'tags'         => $request->tags ?? $testimony->tags,
            'visibility'   => $request->visibility ?? $testimony->visibility->value,
            'proofs_public' => $request->has('proofs_public') ? $request->boolean('proofs_public') : $testimony->proofs_public,
        ]);
        // Carnet privé : reste un brouillon ; sinon nouvelle relecture.
        $testimony->update(['status' => ($testimony->fresh()->isInJournal()
            ? TestimonyStatus::Draft : TestimonyStatus::Pending)->value]);

        return $this->success(new TestimonyResource($testimony->fresh(['user'])));
    }

    /** Fichier média déjà converti correspondant à media_url (sinon null : la tâche mettra le témoignage à jour). */
    private function processedMediaFor(?string $mediaUrl): ?MediaFile
    {
        $media = MediaFile::findForUrl($mediaUrl);

        return $media?->isProcessed() ? $media : null;
    }

    // ─── Carnet privé ─ docs/fonctionnalites/carnet-prive.md ───────────────

    /** Témoignages du carnet privé de l'utilisateur (?type=text|audio|video, ?q=recherche). */
    public function journal(Request $request): JsonResponse
    {
        $query = Testimony::with('user')
            ->where('user_id', $request->user()->id)
            ->journal()
            ->latest();

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn ($q2) => $q2->where('title', 'like', "%{$q}%")
                                         ->orWhere('body_text', 'like', "%{$q}%"));
        }

        $limit = min((int) $request->query('limit', 20), 50);
        $items = $query->paginate($limit);

        return $this->paginated(
            TestimonyResource::collection($items->items()),
            [
                'currentPage' => $items->currentPage(),
                'lastPage'    => $items->lastPage(),
                'total'       => $items->total(),
                'perPage'     => $items->perPage(),
            ]
        );
    }

    /** Partage une entrée du carnet : elle devient publique et passe en modération. */
    public function publishFromJournal(Request $request, string $id): JsonResponse
    {
        $request->validate(
            ['category' => ['nullable', 'string', 'exists:categories,slug']],
            ['category.exists' => "Cette catégorie n'existe pas. Choisissez-en une autre."]
        );

        $testimony = Testimony::find($id);
        if (!$testimony || $testimony->user_id !== $request->user()->id) return $this->notFound();
        if (!$request->user()->canPublish()) {
            return $this->forbidden('Vous n\'avez pas la permission de publier');
        }
        if (!$testimony->isInJournal()) {
            return $this->error('Ce témoignage est déjà partagé.', 409);
        }

        $slug     = $request->input('category') ?: $testimony->category_slug;
        $category = Category::where('slug', $slug)->first();

        $testimony->update([
            'visibility'    => TestimonyVisibility::Public->value,
            'status'        => TestimonyStatus::Pending->value,
            'category_slug' => $slug,
            'category_id'   => $category?->id ?? $testimony->category_id,
        ]);
        $category?->increment('testimony_count');

        return $this->success(new TestimonyResource($testimony->fresh(['user'])), 'Témoignage soumis pour modération');
    }

    /** Retire un témoignage du public et le range dans le carnet privé. */
    public function moveToJournal(Request $request, string $id): JsonResponse
    {
        $testimony = Testimony::find($id);
        if (!$testimony || $testimony->user_id !== $request->user()->id) return $this->notFound();
        if ($testimony->isInJournal()) {
            return $this->success(new TestimonyResource($testimony->load('user')), 'Déjà dans votre carnet privé');
        }

        $wasCounted = $testimony->status !== TestimonyStatus::Draft;
        $testimony->update([
            'visibility'  => TestimonyVisibility::Private->value,
            'status'      => TestimonyStatus::Draft->value,
            'is_featured' => false,
        ]);
        if ($wasCounted) {
            Category::where('slug', $testimony->category_slug)->where('testimony_count', '>', 0)->decrement('testimony_count');
        }

        return $this->success(new TestimonyResource($testimony->fresh(['user'])), 'Témoignage rangé dans votre carnet privé');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $testimony = Testimony::find($id);

        if (!$testimony) return $this->notFound();

        if ($testimony->user_id !== $request->user()->id && !$request->user()->canModerate()) {
            return $this->forbidden();
        }

        $testimony->delete();

        return $this->success(null, 'Témoignage supprimé');
    }

    public function recordShare(Request $request, string $id): JsonResponse
    {
        $testimony = Testimony::find($id);
        if (!$testimony) return $this->notFound();

        $testimony->increment('share_count');

        return $this->success(['shareUrl' => $testimony->share_url ?? Testimony::shareUrlFor($testimony->id)], 'Partage enregistré');
    }

    public function save(Request $request, string $id): JsonResponse
    {
        $testimony = Testimony::find($id);
        if (!$testimony) return $this->notFound();

        $request->user()->savedTestimonies()->syncWithoutDetaching([
            $id => ['saved_at' => now()],
        ]);

        $testimony->increment('bookmark_count');

        return $this->success(null, 'Témoignage sauvegardé');
    }

    public function unsave(Request $request, string $id): JsonResponse
    {
        $testimony = Testimony::find($id);
        if (!$testimony) return $this->notFound();

        $request->user()->savedTestimonies()->detach($id);
        $testimony->decrement('bookmark_count');

        return $this->success(null, 'Témoignage retiré des sauvegardes');
    }

    public function saved(Request $request): JsonResponse
    {
        $testimonies = $request->user()
            ->savedTestimonies()
            ->with('user')
            ->published()
            ->latest('saved_at')
            ->paginate(20);

        return $this->paginated(TestimonyResource::collection($testimonies->items()), [
            'total' => $testimonies->total(),
        ]);
    }
}

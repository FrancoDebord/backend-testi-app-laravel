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
        if ($request->query('sort') === 'for_you' && !$request->query('after') && !$request->query('q') && !$request->query('kind')) {
            $viewer = $request->user() ?? $request->user('sanctum');
            $limit  = min((int) $request->query('limit', 20), 50);
            $page   = max(1, (int) $request->query('page', 1));
            $reco   = app(\App\Services\Recommendations::class);
            $feed   = $reco->feed($viewer, $page, $limit, $request->query('type'), $request->query('category'));

            return $this->paginated(TestimonyResource::collection($feed->items()), [
                'currentPage' => $feed->currentPage(),
                'lastPage'    => $feed->lastPage(),
                'total'       => $feed->total(),
                'perPage'     => $feed->perPage(),
                'nextCursor'  => null,
                // Zone utilisée (« Près de chez vous · Cotonou ») et étiquettes des cartes proches : localisation.md
                'place'       => $reco->placeMeta($viewer),
                'nearby'      => (object) $reco->nearbyLabels($viewer, $feed->items()),
            ]);
        }

        // Filtres combinables (docs/fonctionnalites/gestion-des-temoignages.md) :
        // ?status=approved (défaut ; pending | rejected : équipe de modération), ?verified=1, ?featured=1,
        // ?sort=recent | popular | most_viewed (recommended : ancien nom de most_viewed).
        $viewer = $request->user() ?? $request->user('sanctum');
        $status = (string) $request->query('status', 'approved');
        if (!in_array($status, ['approved', 'pending', 'rejected'], true)) {
            return $this->error(__('testimonies.api.unknown_status'), 422, ['status' => [__('testimonies.api.status_values')]]);
        }
        if ($status !== 'approved' && !$viewer?->canModerate()) {
            return $this->forbidden(__('testimonies.api.moderators_only'));
        }

        $query = Testimony::with('user');
        if ($status === 'approved') {
            $query->published();
        } else {
            $query->where('status', $status)->withoutJournal()->videoPlayable();
        }
        if ($request->boolean('verified')) {
            $query->where('is_verified', true);
        }
        if ($request->boolean('featured')) {
            $query->manuallyFeatured();
        }
        // ?kind=conversion | standard (docs/fonctionnalites/temoignage-de-conversion.md)
        if ($kind = \App\Enums\TestimonyKind::tryFrom((string) $request->query('kind'))) {
            $query->where('kind', $kind->value);
        }

        // Delta sync: return only records updated after cursor
        if ($after = $request->query('after')) {
            $query->after($after)->latest('updated_at');
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
            'popular'                     => $query->orderByDesc('like_count')->orderByDesc('prayer_count')->orderByDesc('views_count'),
            'most_viewed', 'recommended'  => $query->orderByDesc('views_count'),
            default                       => $query->latestPublished(),
        };
        $query->orderByDesc('id'); // ordre stable entre les pages

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

    /**
     * À la une : mises à la une manuelles actives (featured_order), puis complément récent si
     * featured_fill_recent. Chaque élément porte featured_source = manual | recent ; seuls les
     * « manual » ont le badge « featured ». Liste en cache 10 min (App\Services\FeaturedTestimonies).
     */
    public function featured(): JsonResponse
    {
        return $this->success(TestimonyResource::collection(app(\App\Services\FeaturedTestimonies::class)->list()));
    }

    /**
     * POST /testimonies/{id}/view  { device_id?, watched_seconds? } (public) : vue dédoublonnée par
     * personne (compte, sinon appareil X-Device-Id / device_id, sinon IP) sur 30 min ; secondes de
     * vidéo regardées depuis le dernier envoi. Voir docs/fonctionnalites/gestion-des-temoignages.md
     */
    public function recordView(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'device_id'       => ['nullable', 'string', 'max:120'],
            'watched_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);
        $testimony = Testimony::find($id);
        $viewer = $request->user() ?? $request->user('sanctum');
        if (!$testimony || !$testimony->isVisibleTo($viewer)) {
            return $this->notFound();
        }

        $counted = app(\App\Services\ViewCounter::class)->record($testimony, $request);
        if (!empty($data['watched_seconds']) && $testimony->status === TestimonyStatus::Approved && !$testimony->isInJournal()) {
            app(\App\Services\TestimonyStats::class)->recordWatch($testimony, $request, (int) $data['watched_seconds']);
        }

        return $this->success(['counted' => $counted, 'viewsCount' => (int) $testimony->views_count]);
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

        // Non publié (en attente, refusé) : visible de son auteur et de la modération seulement
        // (docs/fonctionnalites/gestion-des-temoignages.md).
        if ($testimony->status !== TestimonyStatus::Approved && $viewer?->id !== $testimony->user_id && !$isModerator) {
            return $this->notFound();
        }

        // Seules les lectures d'un témoignage publié comptent, une fois par personne sur 30 min (ViewCounter).
        // Historique de lecture (recommandations) : chaque lecture, comme avant.
        app(\App\Services\ViewCounter::class)->record($testimony, $request, history: false);
        if (!$testimony->isInJournal() && $testimony->status === TestimonyStatus::Approved) {
            \App\Services\Recommendations::recordView($viewer, $testimony);
        }

        return $this->success(new TestimonyResource($testimony));
    }

    /**
     * Mon fil (GET /feed) : tous les témoignages publiés, les comptes suivis d'abord (leurs témoignages
     * et partages récents), puis le classement « Pour vous ». Chaque témoignage porte `feedReason` =
     * following | suggested | repost (+ repostId, repostedBy, repostComment, repostedAt).
     * Éléments insérés : GET /feed/inserts. Voir docs/fonctionnalites/recommandations.md et partage-interne.md
     */
    public function personalFeed(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $limit  = min((int) $request->query('limit', 20), 50);
        [$page, $reasons, $reposts] = app(\App\Services\Recommendations::class)
            ->personalFeed($viewer, (int) $request->query('page', 1), $limit);

        return $this->paginated(
            collect($page->items())->map(fn ($t) => isset($reposts[$t->id])
                ? RepostController::feedItem($request, $t, $reposts[$t->id])
                : [
                    ...(new TestimonyResource($t))->toArray($request),
                    'feedReason' => $reasons[$t->id] ?? 'suggested',
                ])->values(),
            [
                'currentPage' => $page->currentPage(),
                'lastPage'    => $page->lastPage(),
                'total'       => $page->total(),
                'perPage'     => $page->perPage(),
                'followingCount' => count(app(\App\Services\Recommendations::class)->followedIds($viewer)),
                'place'          => app(\App\Services\Recommendations::class)->placeMeta($viewer),
                'nearby'         => (object) app(\App\Services\Recommendations::class)->nearbyLabels($viewer, $page->items()),
            ]
        );
    }

    public function store(StoreTestimonyRequest $request): JsonResponse
    {
        try {
            $testimony = app(\App\Services\TestimonyCreator::class)->create($request);
        } catch (\App\Services\ContentActionException $e) {
            return $this->error($e->getMessage(), $e->status(), $e->errors());
        }
        // Modération non obligatoire (config testimonies.require_moderation) : approuvé aussitôt.
        app(\App\Services\TestimonyModeration::class)->submitted($testimony);

        return $this->created(new TestimonyResource($testimony->fresh(['user'])), match (true) {
            $testimony->isInJournal()                                    => __('testimonies.api.saved_in_journal'),
            $testimony->status === TestimonyStatus::Approved             => __('testimonies.api.published'),
            default                                                      => __('testimonies.api.submitted'),
        });
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

    /**
     * PUT /testimonies/{id}/web { publicationWeb?, isAnonymous? } : l'auteur donne ou retire son accord pour le
     * web (retrait : la page répond 410 Gone et sort des sitemaps) et choisit l'anonymat sur le site.
     * Même règle que la route du site (Web\TestimonyController::updateWebPublication) : auteur seulement,
     * sans nouvelle modération, contrairement à PUT /testimonies/{id}. Champs absents : inchangés.
     * Voir docs/fonctionnalites/referencement.md
     */
    public function updateWebPublication(Request $request, string $id): JsonResponse
    {
        $testimony = Testimony::find($id);
        if (!$testimony) return $this->notFound();
        if ($testimony->user_id !== $request->user()->id) return $this->forbidden();

        $request->validate([
            'publicationWeb'  => ['nullable', 'boolean'],
            'publication_web' => ['nullable', 'boolean'],
            'isAnonymous'     => ['nullable', 'boolean'],
            'is_anonymous'    => ['nullable', 'boolean'],
        ]);
        // Seulement l'accord et l'anonymat (pas le résumé ni la transcription : ce serait une modification).
        $changes = array_intersect_key(
            \App\Support\WebPublication::attributes($request),
            array_flip(['publication_web', 'is_anonymous'])
        );
        if ($changes === []) {
            return $this->error(__('seo.author.nothing_to_change'), 422);
        }
        $testimony->update($changes);

        return $this->success(new TestimonyResource($testimony->fresh(['user'])), __('seo.author.saved'));
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
            $media = \App\Services\TestimonyCreator::processedMediaFor($request->media_url);
            $testimony->renditions = $media?->renditions;
            if ($media?->duration_sec) {
                $testimony->duration_sec = $media->duration_sec;
            }
        }

        // Sorte et champs du témoignage de conversion (règles vérifiées pour l'auteur du témoignage).
        try {
            $kindFields = app(\App\Services\ConversionTestimonies::class)->attributesForUpdate($testimony->loadMissing('user'), $request);
        } catch (\App\Services\ContentActionException $e) {
            return $this->error($e->getMessage(), $e->status(), $e->errors());
        }

        $testimony->update([
            ...$kindFields,
            'title'        => $request->title ?? $testimony->title,
            // Mentions déjà présentes reconnues ; les nouvelles seront prévenues à la prochaine approbation.
            'body_text'    => \App\Services\TestimonyCreator::bodyWithMentions($request, $testimony->body_text) ?? $testimony->body_text,
            'media_url'    => $request->media_url ?? $testimony->media_url,
            'cover_url'    => $request->cover_url ?? $testimony->cover_url,
            'bible_verse'  => $request->bible_verse ?? $testimony->bible_verse,
            'bible_ref'    => $request->verse_ref ?? $testimony->bible_ref,
            'tags'         => $request->tags ?? $testimony->tags,
            'visibility'   => $request->visibility ?? $testimony->visibility->value,
            'proofs_public' => $request->has('proofs_public') ? $request->boolean('proofs_public') : $testimony->proofs_public,
            // Crédit de la vidéo d'origine : vidéo YouTube seulement, vidé sinon (docs/fonctionnalites/videos-youtube.md).
            ...\App\Support\YouTubeCredit::attributesForUpdate($request, filled($testimony->youtube_id)),
            // Champs envoyés seulement (docs/fonctionnalites/referencement.md)
            ...\App\Support\WebPublication::attributes($request),
        ]);
        // Carnet privé : reste un brouillon. Modification par l'AUTEUR (« Modifier et renvoyer ») : refusé →
        // en attente, approuvé → en attente si la modération est obligatoire, label retiré
        // (TestimonyModeration::editedByAuthor). Une correction par la modération garde le statut.
        $testimony->refresh();
        if ($testimony->isInJournal()) {
            $testimony->update(['status' => TestimonyStatus::Draft->value]);
        } elseif ($testimony->user_id === $request->user()->id) {
            app(\App\Services\TestimonyModeration::class)->editedByAuthor($testimony, $request->user());
        }

        return $this->success(new TestimonyResource($testimony->fresh(['user'])));
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
            ['category.exists' => __('testimonies.api.category_missing')]
        );

        $testimony = Testimony::find($id);
        if (!$testimony || $testimony->user_id !== $request->user()->id) return $this->notFound();
        if (!$request->user()->canPublish()) {
            return $this->forbidden(__('testimonies.api.cannot_publish'));
        }
        if (!$testimony->isInJournal()) {
            return $this->error(__('testimonies.api.already_shared'), 409);
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
        app(\App\Services\TestimonyModeration::class)->submitted($testimony->refresh());

        return $this->success(new TestimonyResource($testimony->fresh(['user'])), $testimony->status === TestimonyStatus::Approved
            ? __('testimonies.api.published') : __('testimonies.api.submitted'));
    }

    /** Retire un témoignage du public et le range dans le carnet privé. */
    public function moveToJournal(Request $request, string $id): JsonResponse
    {
        $testimony = Testimony::find($id);
        if (!$testimony || $testimony->user_id !== $request->user()->id) return $this->notFound();
        if ($testimony->isInJournal()) {
            return $this->success(new TestimonyResource($testimony->load('user')), __('testimonies.api.already_in_journal'));
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

        return $this->success(new TestimonyResource($testimony->fresh(['user'])), __('testimonies.api.moved_to_journal'));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $testimony = Testimony::find($id);

        if (!$testimony) return $this->notFound();

        // Auteur ou administrateur ; un modérateur ne supprime pas ce qu'un autre a soumis.
        if (!$testimony->canBeDeletedBy($request->user())) {
            return $this->forbidden();
        }

        $testimony->delete();

        return $this->success(null, __('testimonies.api.deleted'));
    }

    public function recordShare(Request $request, string $id): JsonResponse
    {
        $testimony = Testimony::find($id);
        // Invités compris : seul un témoignage publié et public se partage.
        if (!$testimony || $testimony->status !== \App\Enums\TestimonyStatus::Approved
            || $testimony->visibility === \App\Enums\TestimonyVisibility::Private) {
            return $this->notFound();
        }

        $testimony->increment('share_count');
        app(\App\Services\TestimonyStats::class)->recordShare($testimony, $request);

        return $this->success(['shareUrl' => $testimony->share_url ?? Testimony::shareUrlFor($testimony->id)], __('testimonies.api.share_recorded'));
    }

    public function save(Request $request, string $id): JsonResponse
    {
        $testimony = Testimony::find($id);
        if (!$testimony) return $this->notFound();

        $request->user()->savedTestimonies()->syncWithoutDetaching([
            $id => ['saved_at' => now()],
        ]);

        $testimony->increment('bookmark_count');

        return $this->success(null, __('testimonies.api.saved'));
    }

    public function unsave(Request $request, string $id): JsonResponse
    {
        $testimony = Testimony::find($id);
        if (!$testimony) return $this->notFound();

        $request->user()->savedTestimonies()->detach($id);
        $testimony->decrement('bookmark_count');

        return $this->success(null, __('testimonies.api.unsaved'));
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

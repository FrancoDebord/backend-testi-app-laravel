<?php

namespace App\Http\Controllers\Api;

use App\Enums\TestimonyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTestimonyRequest;
use App\Http\Resources\TestimonyResource;
use App\Models\Category;
use App\Models\Testimony;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestimonyController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
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

    public function featured(): JsonResponse
    {
        $testimonies = Testimony::with('user')
            ->featured()
            ->orderByDesc('views_count')
            ->limit(10)
            ->get();

        return $this->success(TestimonyResource::collection($testimonies));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $testimony = Testimony::with(['user', 'moderationLogs'])->find($id);

        if (!$testimony) return $this->notFound();

        // Check visibility
        if ($testimony->visibility->value !== 'public' && $request->user()?->id !== $testimony->user_id) {
            return $this->forbidden();
        }

        // Increment views
        $testimony->increment('views_count');

        return $this->success(new TestimonyResource($testimony));
    }

    public function store(StoreTestimonyRequest $request): JsonResponse
    {
        if (!$request->user()->canPublish()) {
            return $this->forbidden('Vous n\'avez pas la permission de publier');
        }

        $category = Category::where('slug', $request->category)->first();

        $testimony = Testimony::create([
            'user_id'      => $request->user()->id,
            'category_id'  => $category?->id,
            'title'        => $request->title,
            'type'         => $request->type,
            'category_slug' => $request->category,
            'body_text'    => $request->body_text,
            'media_url'    => $request->media_url,
            'cover_url'    => $request->cover_url,
            'duration_sec' => $request->duration ?? 0,
            'bible_verse'  => $request->bible_verse,
            'bible_ref'    => $request->verse_ref,
            'tags'         => $request->tags ?? [],
            'visibility'   => $request->visibility ?? 'public',
            'status'       => TestimonyStatus::Approved->value,
            'approved_at'  => now(),
        ]);

        $testimony->load('user');

        // Update category count
        $category?->increment('testimony_count');

        return $this->created(new TestimonyResource($testimony), 'Témoignage soumis pour modération');
    }

    public function update(StoreTestimonyRequest $request, string $id): JsonResponse
    {
        $testimony = Testimony::find($id);

        if (!$testimony) return $this->notFound();

        if ($testimony->user_id !== $request->user()->id && !$request->user()->canModerate()) {
            return $this->forbidden();
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
            'status'       => TestimonyStatus::Pending->value, // re-review on edit
        ]);

        return $this->success(new TestimonyResource($testimony->fresh(['user'])));
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

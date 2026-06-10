<?php

namespace App\Http\Controllers\Web;

use App\Enums\TestimonyStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Reaction;
use App\Models\Testimony;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TestimonyController extends Controller
{
    public function show(string $id): View
    {
        $testimony = Testimony::with(['user', 'category'])->find($id);

        if (!$testimony) abort(404);

        if ($testimony->visibility->value === 'private' && Auth::id() !== $testimony->user_id) {
            abort(403);
        }

        $testimony->increment('views_count');

        $comments = Comment::with('user')
            ->where('testimony_id', $id)
            ->whereNull('parent_id')
            ->latest()
            ->paginate(15);

        $userReactions = Auth::check()
            ? Reaction::where('testimony_id', $id)
                       ->where('user_id', Auth::id())
                       ->pluck('type')
                       ->map(fn($t) => $t->value)
                       ->toArray()
            : [];

        $isSaved = Auth::check()
            ? DB::table('saved_testimonies')
                ->where('user_id', Auth::id())
                ->where('testimony_id', $id)
                ->exists()
            : false;

        return view('testimonies.show', compact('testimony', 'comments', 'userReactions', 'isSaved'));
    }

    public function create(): View
    {
        $categories = Category::active()->get();
        return view('testimonies.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'       => 'required|string|max:200',
            'type'        => 'required|in:text,audio,video',
            'category'    => 'required|string',
            'body_text'   => 'nullable|string',
            'bible_verse' => 'nullable|string|max:500',
            'bible_ref'   => 'nullable|string|max:100',
            'tags'        => 'nullable|string',
            'visibility'  => 'nullable|in:public,private,followers',
            'cover'       => 'nullable|image|max:5120',
            'media_file'  => 'nullable|file|max:102400',
        ]);

        $coverUrl = null;
        $mediaUrl = null;

        if ($request->hasFile('cover')) {
            $path     = $request->file('cover')->store('covers', 'public');
            $coverUrl = asset('storage/' . $path);
        }

        if ($request->hasFile('media_file')) {
            $path     = $request->file('media_file')->store('media', 'public');
            $mediaUrl = asset('storage/' . $path);
        }

        $category = Category::where('slug', $data['category'])->first();
        $tags     = $data['tags'] ? array_map('trim', explode(',', $data['tags'])) : [];

        $testimony = Testimony::create([
            'user_id'       => Auth::id(),
            'category_id'   => $category?->id,
            'title'         => $data['title'],
            'type'          => $data['type'],
            'category_slug' => $data['category'],
            'body_text'     => $data['body_text'] ?? null,
            'cover_url'     => $coverUrl,
            'media_url'     => $mediaUrl,
            'bible_verse'   => $data['bible_verse'] ?? null,
            'bible_ref'     => $data['bible_ref'] ?? null,
            'tags'          => $tags,
            'visibility'    => $data['visibility'] ?? 'public',
            'status'        => TestimonyStatus::Pending->value,
        ]);

        return redirect()->route('testimonies.show', $testimony->id)
                         ->with('success', 'Témoignage soumis pour modération.');
    }

    public function myTestimonies(Request $request): View
    {
        $status = $request->query('status', 'all');
        $query  = Testimony::where('user_id', Auth::id())->latest();

        if ($status !== 'all') $query->where('status', $status);

        $testimonies = $query->paginate(12);
        return view('testimonies.mine', compact('testimonies', 'status'));
    }

    public function report(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'reason'  => 'required|string',
            'details' => 'nullable|string|max:500',
        ]);

        Testimony::findOrFail($id);

        \App\Models\TestimonyReport::firstOrCreate(
            ['testimony_id' => $id, 'reporter_id' => Auth::id()],
            ['reason' => $request->reason, 'details' => $request->details]
        );

        return back()->with('success', 'Signalement envoyé.');
    }

    public function storeReaction(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $request->validate(['type' => 'required|in:like,love,pray,amen,fire']);

        $testimony = Testimony::findOrFail($id);

        $existing = Reaction::where([
            'user_id'      => Auth::id(),
            'testimony_id' => $id,
            'type'         => $request->type,
        ])->first();

        if ($existing) {
            $field = in_array($existing->type->value, ['pray', 'amen']) ? 'prayer_count' : 'like_count';
            $testimony->decrement($field);
            $existing->delete();
            $reacted = false;
        } else {
            Reaction::create([
                'user_id'      => Auth::id(),
                'testimony_id' => $id,
                'type'         => $request->type,
            ]);
            $field = in_array($request->type, ['pray', 'amen']) ? 'prayer_count' : 'like_count';
            $testimony->increment($field);
            $reacted = true;
        }

        $testimony->refresh();

        if ($request->ajax()) {
            return response()->json([
                'reacted'       => $reacted,
                'like_count'    => $testimony->like_count,
                'prayer_count'  => $testimony->prayer_count,
            ]);
        }

        return back();
    }

    public function saveTestimony(string $id): JsonResponse|RedirectResponse
    {
        Testimony::findOrFail($id);

        $saved = DB::table('saved_testimonies')
            ->where('user_id', Auth::id())
            ->where('testimony_id', $id)
            ->exists();

        if ($saved) {
            DB::table('saved_testimonies')
                ->where('user_id', Auth::id())
                ->where('testimony_id', $id)
                ->delete();
        } else {
            DB::table('saved_testimonies')->insert([
                'user_id'      => Auth::id(),
                'testimony_id' => $id,
                'saved_at'     => now(),
            ]);
        }

        if (request()->ajax()) {
            return response()->json(['saved' => !$saved]);
        }

        return back();
    }

    public function storeComment(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $request->validate(['body' => 'required|string|max:2000']);

        $testimony = Testimony::findOrFail($id);

        $comment = Comment::create([
            'testimony_id' => $id,
            'user_id'      => Auth::id(),
            'body'         => $request->body,
        ]);

        $testimony->increment('comment_count');

        if ($request->ajax()) {
            $user = Auth::user();
            return response()->json([
                'comment' => [
                    'id'           => $comment->id,
                    'body'         => $comment->body,
                    'display_name' => $user->display_name,
                    'initials'     => $user->initials,
                ],
            ]);
        }

        return back()->with('success', 'Commentaire ajouté.');
    }
}

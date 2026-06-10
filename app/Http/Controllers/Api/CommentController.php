<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\Testimony;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    use ApiResponse;

    public function index(Request $request, string $testimonyId): JsonResponse
    {
        $testimony = Testimony::find($testimonyId);
        if (!$testimony) return $this->notFound();

        $comments = Comment::with(['user', 'replies.user'])
            ->where('testimony_id', $testimonyId)
            ->whereNull('parent_id')
            ->oldest()
            ->paginate(30);

        return $this->paginated(
            CommentResource::collection($comments->items()),
            ['total' => $comments->total()]
        );
    }

    public function store(StoreCommentRequest $request, string $testimonyId): JsonResponse
    {
        $testimony = Testimony::find($testimonyId);
        if (!$testimony) return $this->notFound();

        $comment = Comment::create([
            'testimony_id' => $testimonyId,
            'user_id'      => $request->user()->id,
            'parent_id'    => $request->parent_id,
            'body'         => $request->text,
        ]);

        // Update counts
        $testimony->increment('comment_count');

        if ($request->parent_id) {
            Comment::where('id', $request->parent_id)->increment('replies_count');
        }

        // Send notification to testimony author
        if ($testimony->user_id !== $request->user()->id) {
            AppNotification::create([
                'recipient_id'   => $testimony->user_id,
                'actor_id'       => $request->user()->id,
                'actor_name'     => $request->user()->display_name,
                'actor_avatar'   => $request->user()->avatar_url,
                'type'           => $request->parent_id ? 'reply' : 'comment',
                'testimony_id'   => $testimonyId,
                'testimony_title' => $testimony->title,
                'comment_id'     => $comment->id,
                'message'        => $request->user()->display_name . ' a commenté votre témoignage',
                'created_at'     => now(),
            ]);
        }

        $comment->load('user');

        return $this->created(new CommentResource($comment), 'Commentaire ajouté');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate(['text' => 'required|string|max:2000']);

        $comment = Comment::find($id);
        if (!$comment) return $this->notFound();

        if ($comment->user_id !== $request->user()->id) {
            return $this->forbidden();
        }

        $comment->update(['body' => $request->text]);

        return $this->success(new CommentResource($comment->fresh('user')));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $comment = Comment::find($id);
        if (!$comment) return $this->notFound();

        if ($comment->user_id !== $request->user()->id && !$request->user()->canModerate()) {
            return $this->forbidden();
        }

        $comment->testimony->decrement('comment_count');
        $comment->delete();

        return $this->success(null, 'Commentaire supprimé');
    }
}

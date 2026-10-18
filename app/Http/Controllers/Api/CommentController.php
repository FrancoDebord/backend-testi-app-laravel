<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\Testimony;
use App\Services\Mentions;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Rules\VisibleLength;

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
        // « J'aime » de la personne : une requête pour toute la page, réponses comprises.
        app(\App\Services\CommentLikes::class)->preload(\App\Services\CommentLikes::TESTIMONY, $comments->items(), $request->user('sanctum'));

        return $this->paginated(
            CommentResource::collection($comments->items()),
            ['total' => $comments->total()]
        );
    }

    public function store(StoreCommentRequest $request, string $testimonyId): JsonResponse
    {
        $testimony = Testimony::find($testimonyId);
        if (!$testimony) return $this->notFound();
        if (!$testimony->acceptsCommentsFrom($request->user())) {
            return $this->error(Testimony::commentsClosedMessage(), 403);
        }

        // Mentions « @ » validées (docs/fonctionnalites/mentions.md)
        $mentions = Mentions::prepare((string) $request->text, (array) $request->input('mention_ids', []));

        $comment = Comment::create([
            'testimony_id' => $testimonyId,
            'user_id'      => $request->user()->id,
            'parent_id'    => $request->parent_id,
            'body'         => $mentions['text'],
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
                ...\App\Support\NotificationText::fields('comments.notify_comment', ['actor' => $request->user()->display_name]),
                'created_at'     => now(),
            ]);
        }

        // L'auteur du témoignage, déjà prévenu du commentaire, ne reçoit pas de seconde notification.
        Mentions::notify($mentions['ids'], $request->user(), [
            'testimony_id'    => $testimonyId,
            'testimony_title' => $testimony->title,
            'comment_id'      => $comment->id,
        ], [$testimony->user_id]);

        $comment->load('user');

        return $this->created(new CommentResource($comment), __('comments.api.added'));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'text'          => ['required', 'string', new VisibleLength(2000, __('comments.too_long'))],
            'mention_ids'   => 'nullable|array|max:20',
            'mention_ids.*' => 'string|uuid',
        ]);

        $comment = Comment::find($id);
        if (!$comment) return $this->notFound();

        if ($comment->user_id !== $request->user()->id) {
            return $this->forbidden();
        }

        // Les mentions déjà présentes restent reconnues ; seules les nouvelles sont notifiées.
        $before   = Mentions::ids($comment->body);
        $mentions = Mentions::prepare((string) $request->text, (array) $request->input('mention_ids', []), $comment->body);
        $comment->update(['body' => $mentions['text']]);
        Mentions::notify(array_diff($mentions['ids'], $before), $request->user(), [
            'testimony_id'    => $comment->testimony_id,
            'testimony_title' => $comment->testimony?->title,
            'comment_id'      => $comment->id,
        ]);

        return $this->success(new CommentResource($comment->fresh('user')));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $comment = Comment::find($id);
        if (!$comment) return $this->notFound();

        // Auteur ou administrateur ; un modérateur ne supprime pas ce qu'un autre a écrit.
        if ($comment->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return $this->forbidden();
        }

        $comment->testimony->decrement('comment_count');
        $comment->delete();

        return $this->success(null, __('comments.api.deleted'));
    }
}

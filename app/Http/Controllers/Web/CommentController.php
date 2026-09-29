<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommentRequest;
use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Commentaires et réponses de la page de lecture (/videos/{id}).
 * Réponses : un seul niveau. Répondre à une réponse rattache au commentaire principal.
 * Chaque action renvoie du JSON (page dynamique) ou redirige (sans JavaScript).
 */
class CommentController extends Controller
{
    private const REPLIES_PER_PAGE = 20;

    public function store(CommentRequest $request, string $id): JsonResponse|RedirectResponse
    {
        $testimony = VideoController::findVisible($request, $id);
        $user      = $request->user();
        $parent    = $this->resolveParent($testimony, $request->validated('parent_id'));

        $comment = DB::transaction(function () use ($testimony, $user, $parent, $request) {
            $comment = Comment::create([
                'testimony_id' => $testimony->id,
                'user_id'      => $user->id,
                'parent_id'    => $parent?->id,
                'body'         => $request->validated('body'),
            ]);

            // Même règle que l'API : comment_count compte aussi les réponses.
            $testimony->increment('comment_count');
            $parent?->increment('replies_count');

            return $comment;
        });

        $this->notify($testimony, $comment, $parent, $user);
        $comment->setRelation('user', $user);

        if ($request->expectsJson()) {
            return response()->json([
                'html'     => view('videos.partials.comment', compact('comment', 'testimony'))->render(),
                'parentId' => $parent?->id,
                'replies'  => $parent?->replies_count,
                'count'    => $testimony->comment_count,
            ], 201);
        }

        return redirect(route('videos.show', $testimony->id) . '#comment-' . $comment->id)
            ->with('success', $parent ? 'Réponse publiée.' : 'Commentaire publié.');
    }

    public function update(CommentRequest $request, Comment $comment): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $comment);
        VideoController::findVisible($request, $comment->testimony_id);

        $comment->update(['body' => $request->validated('body')]);

        if ($request->expectsJson()) {
            return response()->json(['body' => $comment->body]);
        }

        return redirect(route('videos.show', $comment->testimony_id) . '#comment-' . $comment->id)
            ->with('success', 'Commentaire modifié.');
    }

    public function destroy(Request $request, Comment $comment): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $testimony = Testimony::find($comment->testimony_id);

        $parentReplies = DB::transaction(function () use ($comment, $testimony) {
            $removed       = 1;
            $parentReplies = null;

            if ($comment->parent_id) {
                $parent = Comment::find($comment->parent_id);
                if ($parent) {
                    $parentReplies = max(0, $parent->replies_count - 1);
                    $parent->forceFill(['replies_count' => $parentReplies])->saveQuietly();
                }
            } else {
                // Supprimer un commentaire principal retire aussi ses réponses.
                $removed += $comment->replies()->count();
                $comment->replies()->delete();
            }

            $comment->delete();

            // Les compteurs peuvent déjà être décalés : jamais en dessous de zéro.
            $testimony?->forceFill(['comment_count' => max(0, $testimony->comment_count - $removed)])->saveQuietly();

            return $parentReplies;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'count'    => $testimony?->comment_count ?? 0,
                'parentId' => $comment->parent_id,
                'replies'  => $parentReplies,
            ]);
        }

        return redirect()->route('videos.show', $comment->testimony_id)->with('success', 'Commentaire supprimé.');
    }

    /** Réponses d'un commentaire principal, de la plus ancienne à la plus récente. */
    public function replies(Request $request, Comment $comment): JsonResponse
    {
        $testimony = VideoController::findVisible($request, $comment->testimony_id);
        abort_if($comment->parent_id !== null, 404);

        $replies = Comment::with('user')
            ->where('parent_id', $comment->id)
            ->oldest()
            ->paginate(self::REPLIES_PER_PAGE);

        return response()->json([
            'html' => view('videos.partials.comments', ['comments' => $replies, 'testimony' => $testimony])->render(),
            'next' => $replies->nextPageUrl(),
        ]);
    }

    // ─── Outils ──────────────────────────────────────────────────────────

    /** Commentaire principal auquel rattacher une réponse (même témoignage, un seul niveau). */
    private function resolveParent(Testimony $testimony, ?string $parentId): ?Comment
    {
        if (!$parentId) {
            return null;
        }

        $parent = Comment::where('testimony_id', $testimony->id)->find($parentId);
        if ($parent?->parent_id) {
            $parent = $parent->parent;
        }

        if (!$parent) {
            throw ValidationException::withMessages(['parent_id' => "Ce commentaire n'existe plus."]);
        }

        return $parent;
    }

    /** Même notification que l'API pour l'auteur du témoignage, plus l'auteur du commentaire en cas de réponse. */
    private function notify(Testimony $testimony, Comment $comment, ?Comment $parent, User $actor): void
    {
        $recipients = [];
        if ($testimony->user_id !== $actor->id) {
            $recipients[$testimony->user_id] = $actor->display_name . ' a commenté votre témoignage';
        }
        if ($parent && $parent->user_id !== $actor->id) {
            $recipients[$parent->user_id] = $actor->display_name . ' a répondu à votre commentaire';
        }

        foreach ($recipients as $recipientId => $message) {
            AppNotification::create([
                'recipient_id'    => $recipientId,
                'actor_id'        => $actor->id,
                'actor_name'      => $actor->display_name,
                'actor_avatar'    => $actor->avatar_url,
                'type'            => $parent ? 'reply' : 'comment',
                'testimony_id'    => $testimony->id,
                'testimony_title' => $testimony->title,
                'comment_id'      => $comment->id,
                'message'         => $message,
                'created_at'      => now(),
            ]);
        }
    }
}

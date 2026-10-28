<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommentRequest;
use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\Testimony;
use App\Models\User;
use App\Services\Mentions;
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
        if (!$testimony->acceptsCommentsFrom($user)) {
            return $request->expectsJson()
                ? response()->json(['message' => \App\Models\Testimony::commentsClosedMessage()], 403)
                : back()->with('error', \App\Models\Testimony::commentsClosedMessage());
        }
        $parent    = $this->resolveParent($testimony, $request->validated('parent_id'));

        // Mentions « @ » (docs/fonctionnalites/mentions.md) : jetons envoyés par l'autocomplétion.
        $mentions = Mentions::prepare($request->validated('body'), $this->mentionHints($request));

        $comment = DB::transaction(function () use ($testimony, $user, $parent, $mentions) {
            $comment = Comment::create([
                'testimony_id' => $testimony->id,
                'user_id'      => $user->id,
                'parent_id'    => $parent?->id,
                'body'         => $mentions['text'],
            ]);

            // Même règle que l'API : comment_count compte aussi les réponses.
            $testimony->increment('comment_count');
            $parent?->increment('replies_count');

            return $comment;
        });

        $notified = $this->notify($testimony, $comment, $parent, $user);
        Mentions::notify($mentions['ids'], $user, [
            'testimony_id'    => $testimony->id,
            'testimony_title' => $testimony->title,
            'comment_id'      => $comment->id,
        ], $notified);
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
            ->with('success', $parent ? __('comments.reply_published') : __('comments.published'));
    }

    public function update(CommentRequest $request, Comment $comment): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $comment);
        VideoController::findVisible($request, $comment->testimony_id);

        // Les mentions déjà présentes restent reconnues (« @Nom » en clair) ; seules les nouvelles sont notifiées.
        $before   = Mentions::ids($comment->body);
        $mentions = Mentions::prepare($request->validated('body'), $this->mentionHints($request), $comment->body);
        $comment->update(['body' => $mentions['text']]);
        Mentions::notify(array_diff($mentions['ids'], $before), $request->user(), [
            'testimony_id'    => $comment->testimony_id,
            'testimony_title' => $comment->testimony?->title,
            'comment_id'      => $comment->id,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'body'     => Mentions::plain($comment->body),
                'html'     => (string) Mentions::html($comment->body),
                'mentions' => Mentions::of($comment->body),
                'preview'  => \Illuminate\Support\Facades\Blade::render('<x-link-preview :text="$text" data-comment-preview />', ['text' => $comment->body]),
            ]);
        }

        return redirect(route('videos.show', $comment->testimony_id) . '#comment-' . $comment->id)
            ->with('success', __('comments.updated'));
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

        return redirect()->route('videos.show', $comment->testimony_id)->with('success', __('comments.deleted'));
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
            throw ValidationException::withMessages(['parent_id' => __('comments.parent_gone')]);
        }

        return $parent;
    }

    /** @return string[] comptes choisis dans l'autocomplétion « @ » (champ mention_ids : liste ou texte séparé par des virgules). */
    private function mentionHints(Request $request): array
    {
        $raw = $request->input('mention_ids', []);
        $ids = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_slice(array_values(array_filter($ids, fn ($id) => is_string($id) && \Illuminate\Support\Str::isUuid($id))), 0, 20);
    }

    /**
     * Même notification que l'API pour l'auteur du témoignage, plus l'auteur du commentaire en cas de réponse.
     * @return string[] personnes prévenues (elles ne reçoivent pas en plus la notification de mention)
     */
    private function notify(Testimony $testimony, Comment $comment, ?Comment $parent, User $actor): array
    {
        $recipients = [];
        if ($testimony->user_id !== $actor->id) {
            $recipients[$testimony->user_id] = 'comments.notify_comment';
        }
        if ($parent && $parent->user_id !== $actor->id) {
            $recipients[$parent->user_id] = 'comments.notify_reply';
        }

        foreach ($recipients as $recipientId => $messageKey) {
            AppNotification::create([
                'recipient_id'    => $recipientId,
                'actor_id'        => $actor->id,
                'actor_name'      => $actor->display_name,
                'actor_avatar'    => $actor->avatar_url,
                'type'            => $parent ? 'reply' : 'comment',
                'testimony_id'    => $testimony->id,
                'testimony_title' => $testimony->title,
                'comment_id'      => $comment->id,
                ...\App\Support\NotificationText::fields($messageKey, ['actor' => $actor->display_name]),
                'created_at'      => now(),
            ]);
        }

        return array_map('strval', array_keys($recipients));
    }
}

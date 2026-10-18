<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CommentLikes;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * « J'aime » sur les commentaires — docs/fonctionnalites/jaime-commentaires.md
 * Partagé par l'API (auth:sanctum) et le site (auth, CSRF) : mêmes réponses JSON
 * { success, data: { likesCount, isLikedByMe } } ; 404 si le commentaire ou son contenu n'est pas visible.
 *
 *   POST|DELETE comments/{id}/like                             (commentaire d'un témoignage)
 *   POST|DELETE events/{id}/comments/{commentId}/like          (commentaire d'un événement)
 *   POST|DELETE daily-verse/{id}/comments/{commentId}/like     (commentaire du verset du jour)
 */
class CommentLikeController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CommentLikes $likes) {}

    public function likeTestimonyComment(Request $request, string $id): JsonResponse
    {
        return $this->toggle($request, CommentLikes::TESTIMONY, $id, null, true);
    }

    public function unlikeTestimonyComment(Request $request, string $id): JsonResponse
    {
        return $this->toggle($request, CommentLikes::TESTIMONY, $id, null, false);
    }

    public function likeEventComment(Request $request, string $id, string $commentId): JsonResponse
    {
        return $this->toggle($request, CommentLikes::EVENT, $commentId, $id, true);
    }

    public function unlikeEventComment(Request $request, string $id, string $commentId): JsonResponse
    {
        return $this->toggle($request, CommentLikes::EVENT, $commentId, $id, false);
    }

    public function likeVerseComment(Request $request, string $id, string $commentId): JsonResponse
    {
        return $this->toggle($request, CommentLikes::VERSE, $commentId, $id, true);
    }

    public function unlikeVerseComment(Request $request, string $id, string $commentId): JsonResponse
    {
        return $this->toggle($request, CommentLikes::VERSE, $commentId, $id, false);
    }

    private function toggle(Request $request, string $type, string $commentId, ?string $parentId, bool $like): JsonResponse
    {
        $user    = $request->user();
        $comment = $this->likes->find($type, $commentId, $parentId, $user);
        if (!$comment) {
            return $this->notFound(__('comment_likes.not_found'));
        }

        $state = $like ? $this->likes->like($type, $comment, $user) : $this->likes->unlike($type, $comment, $user);

        return $this->success($state, $like ? __('comment_likes.liked') : __('comment_likes.unliked'));
    }
}

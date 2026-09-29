<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Reaction;
use App\Models\Testimony;
use App\Models\User;
use App\Services\FollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Données de la page de lecture d'un témoignage, partagées par /videos/{id} et /testimonies/{id}.
 * Voir docs/fonctionnalites/videos.md
 */
class WatchPage
{
    public const COMMENTS_PER_PAGE = 10;
    public const RECOMMENDED       = 10;

    /**
     * @param string $pageRoute Route de la page affichée : les liens « Afficher plus de commentaires »
     *                          et « À regarder également » y renvoient.
     */
    public function data(Testimony $testimony, ?User $user, string $pageRoute): array
    {
        $userReactions = $user
            ? Reaction::where('testimony_id', $testimony->id)->where('user_id', $user->id)->pluck('type')->map(fn ($t) => $t->value)->all()
            : [];

        $isSaved = $user
            && DB::table('saved_testimonies')->where('user_id', $user->id)->where('testimony_id', $testimony->id)->exists();

        return [
            'testimony'     => $testimony,
            'comments'      => $this->comments($testimony, $pageRoute),
            'userReactions' => $userReactions,
            'isSaved'       => $isSaved,
            // Bouton « Suivre » à côté de l'auteur (docs/fonctionnalites/abonnements.md)
            'isFollowingAuthor' => $testimony->user && app(FollowService::class)->isFollowing($user, $testimony->user),
            'recommended'   => $this->recommendations($testimony),
            'pageRoute'     => $pageRoute,
        ];
    }

    /** Commentaires principaux, du plus récent au plus ancien. Les réponses sont chargées à la demande. */
    public function comments(Testimony $testimony, string $pageRoute): LengthAwarePaginator
    {
        return Comment::with('user')
            ->where('testimony_id', $testimony->id)
            ->whereNull('parent_id')
            ->latest()
            ->paginate(self::COMMENTS_PER_PAGE, ['*'], 'comments')
            ->withPath(route($pageRoute, $testimony->id));
    }

    /** Page suivante des commentaires (« Afficher plus de commentaires »). */
    public function commentsResponse(Testimony $testimony, string $pageRoute): JsonResponse
    {
        $comments = $this->comments($testimony, $pageRoute);

        return response()->json([
            'html' => view('videos.partials.comments', compact('comments', 'testimony'))->render(),
            'next' => $comments->nextPageUrl(),
        ]);
    }

    /**
     * « À regarder également » : même type et même catégorie, puis même type récents,
     * puis les plus vus. Trois requêtes au plus.
     */
    public function recommendations(Testimony $testimony): Collection
    {
        $picked = collect();
        $steps  = [
            fn ($q) => $q->where('type', $testimony->type)->where('category_slug', $testimony->category_slug)->latestPublished(),
            fn ($q) => $q->where('type', $testimony->type)->latestPublished(),
            fn ($q) => $q->orderByDesc('views_count'),
        ];

        foreach ($steps as $step) {
            $missing = self::RECOMMENDED - $picked->count();
            if ($missing <= 0) {
                break;
            }

            $query = Testimony::with(['user', 'category'])->published()
                ->whereKeyNot($testimony->id)
                ->whereNotIn('id', $picked->pluck('id'));

            $picked = $picked->concat($step($query)->limit($missing)->get());
        }

        return $picked;
    }
}

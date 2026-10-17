<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestimonyResource;
use App\Http\Resources\UserResource;
use App\Models\Testimony;
use App\Models\TestimonyRepost;
use App\Models\User;
use App\Services\RepostException;
use App\Services\Reposts;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Partage interne d'un témoignage sur TestiApp (« X a partagé »).
 * Règles communes avec le site : App\Services\Reposts. Voir docs/fonctionnalites/partage-interne.md
 */
class RepostController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly Reposts $reposts) {}

    /** POST /testimonies/{id}/repost { comment? } → 201 (nouveau) ou 200 (commentaire modifié). */
    public function store(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(Reposts::rules(), Reposts::messages());

        try {
            [$repost, $created] = $this->reposts->repost($request->user(), $id, $data['comment'] ?? null);
        } catch (RepostException $e) {
            return $this->error($e->getMessage(), $e->status());
        }
        $payload = $this->payload($request, $repost);

        return $created
            ? $this->created($payload, __('testimonies.repost.created'))
            : $this->success($payload, __('testimonies.repost.updated'));
    }

    /** DELETE /testimonies/{id}/repost : annuler son partage. */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $removed = $this->reposts->remove($request->user(), $id);
        if (!$removed) {
            return $this->notFound(__('testimonies.repost.not_reposted'));
        }

        return $this->success(['repostsCount' => (int) Testimony::withTrashed()->whereKey($id)->value('repost_count')], __('testimonies.repost.removed'));
    }

    /** GET /users/{id}/reposts : partages d'un compte (profil, onglet « Partages »), paginés. */
    public function ofUser(Request $request, string $id): JsonResponse
    {
        $owner = Str::isUuid($id) ? User::find($id) : null;
        if (!$owner) return $this->notFound();

        $page = $this->reposts->ofUser($owner)->paginate(min(max((int) $request->query('limit', 20), 1), 50));

        return $this->paginated(
            collect($page->items())->map(fn (TestimonyRepost $r) => self::feedItem($request, $r->testimony, $r))->values(),
            [
                'currentPage' => $page->currentPage(),
                'lastPage'    => $page->lastPage(),
                'total'       => $page->total(),
                'perPage'     => $page->perPage(),
            ]
        );
    }

    /**
     * Témoignage d'origine (son auteur reste `user`) avec les informations du partage :
     * `feedReason` = repost, `repostId`, `repostedBy`, `repostComment`, `repostedAt`.
     */
    public static function feedItem(Request $request, Testimony $testimony, TestimonyRepost $repost): array
    {
        return [
            ...(new TestimonyResource($testimony))->toArray($request),
            'feedReason'    => 'repost',
            'repostId'      => $repost->id,
            'repostedBy'    => (new UserResource($repost->user))->toArray($request),
            'repostComment' => $repost->comment,
            'repostedAt'    => $repost->created_at?->toIso8601String(),
        ];
    }

    private function payload(Request $request, TestimonyRepost $repost): array
    {
        return [
            'id'           => $repost->id,
            'testimonyId'  => $repost->testimony_id,
            'comment'      => $repost->comment,
            'repostedAt'   => $repost->created_at?->toIso8601String(),
            'repostedBy'   => (new UserResource($repost->user))->toArray($request),
            'repostsCount' => (int) Testimony::whereKey($repost->testimony_id)->value('repost_count'),
        ];
    }
}

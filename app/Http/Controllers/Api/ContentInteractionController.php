<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReactionType;
use App\Http\Controllers\Controller;
use App\Models\ContentRepost;
use App\Services\ContentActionException;
use App\Services\ContentInteractions;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Réactions et partages internes des événements, requêtes et sessions de prière.
 * Le type de publication (`kind`) est fixé par la route : event, prayer_request, prayer_session.
 *
 *   POST   {publication}/reactions { type, again? }  → pose la réaction (une par groupe, idempotent) ; again : prier encore
 *   DELETE {publication}/reactions ?group=|?type=    → retire la réaction du groupe (toutes sans paramètre) ; &last=1 : la dernière prière
 *   POST   {publication}/repost { comment? }         → partage avec ses abonnés (201) ou commentaire modifié (200)
 *   DELETE {publication}/repost                      → annule son partage
 *
 * Réponse : `reactions` (compteurs, état de la personne) et `repost` (« X a partagé »).
 * Voir docs/fonctionnalites/reactions.md et partage-interne.md
 */
class ContentInteractionController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ContentInteractions $interactions) {}

    public function react(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['type' => ['required', Rule::enum(ReactionType::class)], 'again' => 'sometimes|boolean']);

        return $this->run($request, $id, function ($subject) use ($request, $data) {
            $created = $this->interactions->react($request->user(), $subject, ReactionType::from($data['type']), $request->boolean('again'));

            return [$created ? 201 : 200, $created ? __('reactions.api.added') : __('reactions.api.saved')];
        });
    }

    /**
     * POST events/{id}/pray/start, prayer/sessions/{id}/pray/start : commence (ou recommence) le temps de prière.
     * Une réaction du groupe « je prie » n'est ajoutée qu'après config('prayer.focus_seconds') secondes.
     * Voir docs/fonctionnalites/temps-de-priere.md
     */
    public function startPrayer(Request $request, string $id): JsonResponse
    {
        try {
            $subject = $this->interactions->find((string) $request->route('kind'), $id, $request->user());
        } catch (ContentActionException $e) {
            return $this->error($e->getMessage(), $e->status());
        }

        return $this->success($this->interactions->startPrayer($request->user(), $subject), __('prayer_focus.started'));
    }

    public function unreact(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'group' => 'nullable|in:appreciation,prayer',
            'type'  => ['nullable', Rule::enum(ReactionType::class)],
            'last'  => 'sometimes|boolean',
        ]);
        $group = $request->query('group') ?? ($request->query('type') ? ReactionType::from($request->query('type'))->group() : null);

        return $this->run($request, $id, function ($subject) use ($request, $group) {
            if ($group === 'prayer' && $request->boolean('last')) {
                $this->interactions->removeLastPrayer($request->user(), $subject);
            } else {
                $this->interactions->unreact($request->user(), $subject, $group);
            }

            return [200, __('reactions.api.removed')];
        });
    }

    public function repost(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(
            ['comment' => ['nullable', 'string', 'max:' . ContentRepost::COMMENT_MAX]],
            ['comment.max' => __('reactions.content.comment_max')],
        );

        return $this->run($request, $id, function ($subject) use ($request, $data) {
            [, $created] = $this->interactions->repost($request->user(), $subject, $data['comment'] ?? null);

            return [$created ? 201 : 200, $created ? __('reactions.content.reposted') : __('testimonies.repost.updated')];
        });
    }

    public function unrepost(Request $request, string $id): JsonResponse
    {
        return $this->run($request, $id, function ($subject) use ($request) {
            if (!$this->interactions->unrepost($request->user(), $subject)) {
                throw new ContentActionException(__('reactions.content.not_reposted'), 404);
            }

            return [200, __('testimonies.repost.removed')];
        });
    }

    /** Trouve la publication visible, applique l'action, renvoie l'état à jour. */
    private function run(Request $request, string $id, \Closure $action): JsonResponse
    {
        try {
            $subject = $this->interactions->find((string) $request->route('kind'), $id, $request->user());
            [$status, $message] = $action($subject);
        } catch (ContentActionException $e) {
            return $this->error($e->getMessage(), $e->status());
        }

        $payload = $this->interactions->payload($subject, $request->user(), $request);

        return $status === 201 ? $this->created($payload, $message) : $this->success($payload, $message);
    }
}

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
 *   POST   {publication}/reactions { type }          → pose la réaction (une par groupe, idempotent)
 *   DELETE {publication}/reactions ?group=|?type=    → retire la réaction du groupe (toutes sans paramètre)
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
        $data = $request->validate(['type' => ['required', Rule::enum(ReactionType::class)]]);

        return $this->run($request, $id, function ($subject) use ($request, $data) {
            $created = $this->interactions->react($request->user(), $subject, ReactionType::from($data['type']));

            return [$created ? 201 : 200, $created ? 'Réaction ajoutée' : 'Réaction enregistrée'];
        });
    }

    public function unreact(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'group' => 'nullable|in:appreciation,prayer',
            'type'  => ['nullable', Rule::enum(ReactionType::class)],
        ]);
        $group = $request->query('group') ?? ($request->query('type') ? ReactionType::from($request->query('type'))->group() : null);

        return $this->run($request, $id, function ($subject) use ($request, $group) {
            $this->interactions->unreact($request->user(), $subject, $group);

            return [200, 'Réaction retirée'];
        });
    }

    public function repost(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(
            ['comment' => ['nullable', 'string', 'max:' . ContentRepost::COMMENT_MAX]],
            ['comment.max' => 'Votre commentaire : 500 caractères au plus.'],
        );

        return $this->run($request, $id, function ($subject) use ($request, $data) {
            [, $created] = $this->interactions->repost($request->user(), $subject, $data['comment'] ?? null);

            return [$created ? 201 : 200, $created ? 'Partagé avec vos abonnés.' : 'Partage mis à jour.'];
        });
    }

    public function unrepost(Request $request, string $id): JsonResponse
    {
        return $this->run($request, $id, function ($subject) use ($request) {
            if (!$this->interactions->unrepost($request->user(), $subject)) {
                throw new ContentActionException("Vous n'avez pas partagé cette publication.", 404);
            }

            return [200, 'Partage annulé.'];
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

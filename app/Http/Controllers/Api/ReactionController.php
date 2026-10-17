<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReactionRequest;
use App\Http\Resources\ReactionResource;
use App\Models\Reaction;
use App\Models\Testimony;
use App\Services\TestimonyReactions;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReactionController extends Controller
{
    use ApiResponse;

    public function index(string $testimonyId): JsonResponse
    {
        $testimony = Testimony::find($testimonyId);
        if (!$testimony) return $this->notFound();

        $reactions = Reaction::where('testimony_id', $testimonyId)->get();

        return $this->success(ReactionResource::collection($reactions));
    }

    /**
     * POST testimonies/{id}/reactions {type} : pose la réaction (idempotent). Une autre réaction
     * du même groupe (j'aime / je prie) est remplacée : rien n'est jamais compté deux fois.
     * Réponse : la réaction + l'état de la personne et les compteurs à jour.
     */
    public function store(StoreReactionRequest $request, string $testimonyId, TestimonyReactions $reactions): JsonResponse
    {
        $testimony = Testimony::find($testimonyId);
        if (!$testimony) return $this->notFound();

        [$reaction, $created] = $reactions->set($request->user(), $testimony, ReactionType::from($request->type));

        $payload = [...(new ReactionResource($reaction))->toArray($request), ...$reactions->state($request->user(), $testimony)];

        return $created
            ? $this->created($payload, __('reactions.api.added'))
            : $this->success($payload, __('reactions.api.saved'));
    }

    /**
     * DELETE testimonies/{id}/reactions/{reactionId} : retire la réaction (et toute autre du même groupe).
     * DELETE testimonies/{id}/reactions?group=appreciation|prayer (ou ?type=like…) : sans identifiant,
     * retire les réactions de la personne de ce groupe (toutes sans paramètre).
     */
    public function destroy(Request $request, TestimonyReactions $reactions, string $testimonyId, ?string $reactionId = null): JsonResponse
    {
        $testimony = Testimony::find($testimonyId);
        if (!$testimony) return $this->notFound();

        $request->validate([
            'group' => 'nullable|in:appreciation,prayer',
            'type'  => ['nullable', \Illuminate\Validation\Rule::enum(ReactionType::class)],
        ]);

        if ($reactionId !== null) {
            $reaction = Reaction::where('id', $reactionId)->where('user_id', $request->user()->id)
                ->where('testimony_id', $testimony->id)->first();
            if (!$reaction) return $this->notFound();
            $group = $reaction->type->group();
        } else {
            $group = $request->query('group') ?? ($request->query('type') ? ReactionType::from($request->query('type'))->group() : null);
        }

        $reactions->remove($request->user(), $testimony, $group);

        return $this->success($reactions->state($request->user(), $testimony), __('reactions.api.removed'));
    }
}

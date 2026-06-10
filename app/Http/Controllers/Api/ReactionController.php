<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReactionRequest;
use App\Http\Resources\ReactionResource;
use App\Models\AppNotification;
use App\Models\Reaction;
use App\Models\Testimony;
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

    public function store(StoreReactionRequest $request, string $testimonyId): JsonResponse
    {
        $testimony = Testimony::find($testimonyId);
        if (!$testimony) return $this->notFound();

        $existing = Reaction::where([
            'user_id'      => $request->user()->id,
            'testimony_id' => $testimonyId,
            'type'         => $request->type,
        ])->first();

        if ($existing) {
            return $this->success(new ReactionResource($existing), 'Réaction déjà enregistrée');
        }

        $reaction = Reaction::create([
            'user_id'      => $request->user()->id,
            'testimony_id' => $testimonyId,
            'type'         => $request->type,
        ]);

        // Update counts on testimony
        $field = match($request->type) {
            'pray', 'amen' => 'prayer_count',
            default        => 'like_count',
        };
        $testimony->increment($field);

        // Notify author
        if ($testimony->user_id !== $request->user()->id) {
            AppNotification::create([
                'recipient_id'    => $testimony->user_id,
                'actor_id'        => $request->user()->id,
                'actor_name'      => $request->user()->display_name,
                'actor_avatar'    => $request->user()->avatar_url,
                'type'            => 'like',
                'testimony_id'    => $testimonyId,
                'testimony_title' => $testimony->title,
                'message'         => $request->user()->display_name . ' a réagi à votre témoignage',
                'created_at'      => now(),
            ]);
        }

        return $this->created(new ReactionResource($reaction), 'Réaction ajoutée');
    }

    public function destroy(Request $request, string $testimonyId, string $reactionId): JsonResponse
    {
        $reaction = Reaction::where('id', $reactionId)
                            ->where('user_id', $request->user()->id)
                            ->first();

        if (!$reaction) return $this->notFound();

        $testimony = Testimony::find($testimonyId);
        $field = in_array($reaction->type->value, ['pray', 'amen']) ? 'prayer_count' : 'like_count';
        $testimony?->decrement($field);

        $reaction->delete();

        return $this->success(null, 'Réaction retirée');
    }
}

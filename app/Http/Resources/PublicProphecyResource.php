<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Parole prophétique rendue publique avec son témoignage d'accomplissement (fils : « Mon fil »).
 * Jamais les rappels ni le journal de prière. Charger `user` et `testimony.user`.
 * Voir docs/fonctionnalites/paroles-prophetiques.md et recommandations.md
 *
 * @mixin \App\Models\Prophecy
 */
class PublicProphecyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $testimony = $this->testimony;

        return [
            'type' => 'prophecy',
            'id'   => $this->id,
            ...$this->publicPayload(),
            'user' => new UserResource($this->whenLoaded('user')),
            'testimony' => $testimony ? [
                'id'          => $testimony->id,
                'title'       => $testimony->title,
                'type'        => $testimony->type->value,
                'coverUrl'    => $testimony->cover_url,
                'publishedAt' => $testimony->publishedAt()?->toIso8601String(),
            ] : null,
        ];
    }
}

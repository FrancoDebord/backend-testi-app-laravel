<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Commentaire d'un événement (témoignage d'un participant). */
class EventCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user() ?? $request->user('sanctum');

        return [
            'id'          => $this->id,
            'body'        => $this->body,
            'user'        => [
                'id'          => $this->user_id,
                'displayName' => $this->user?->display_name ?? 'Anonyme',
                'initials'    => $this->user?->initials,
                'avatarUrl'   => $this->user?->avatar_url,
                'isVerified'  => (bool) $this->user?->isVerified(),
            ],
            // Enregistré comme témoignage officiel de l'événement.
            'testimonyId' => $this->testimony_id,
            'isMine'      => $user?->id === $this->user_id,
            'createdAt'   => $this->created_at?->toIso8601String(),
        ];
    }
}

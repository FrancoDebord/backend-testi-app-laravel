<?php

namespace App\Http\Resources;

use App\Services\Mentions;
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
            // Texte lisible (« @Nom ») + mentions cliquables (docs/fonctionnalites/mentions.md).
            'body'        => Mentions::plain($this->body),
            'mentions'    => Mentions::of($this->body),
            'user'        => [
                'id'          => $this->user_id,
                'displayName' => $this->user?->display_name ?? 'Anonyme',
                'initials'    => $this->user?->initials,
                'avatarUrl'   => $this->user?->avatar_url,
                'isVerified'  => (bool) $this->user?->isVerified(),
                ...\App\Models\User::approvalFieldsFor($this->user), // badge-approuve.md
            ],
            // Enregistré comme témoignage officiel de l'événement.
            'testimonyId' => $this->testimony_id,
            'isMine'      => $user?->id === $this->user_id,
            // Auteur ou administrateur ; les gestionnaires de l'événement le peuvent aussi (`canManage`).
            'canDelete'   => $user !== null && ($user->id === $this->user_id || $user->isAdmin()),
            'createdAt'   => $this->created_at?->toIso8601String(),
        ];
    }
}

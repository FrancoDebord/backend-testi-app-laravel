<?php

namespace App\Http\Resources;

use App\Services\CommentLikes;
use App\Services\Mentions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user() ?? $request->user('sanctum');

        return [
            'id'          => $this->id,
            'testimonyId' => $this->testimony_id,
            'userId'      => $this->user_id,
            'user'        => new UserResource($this->whenLoaded('user')),
            // Mentions « @ » : texte affichable (@Nom) + comptes mentionnés (docs/fonctionnalites/mentions.md).
            'text'        => Mentions::plain($this->body),
            'mentions'    => Mentions::of($this->body),
            // « J'aime » (docs/fonctionnalites/jaime-commentaires.md) ; isLikedByMe faux pour un visiteur.
            'likesCount'  => (int) $this->likes_count,
            'isLikedByMe' => app(CommentLikes::class)->isLikedBy(CommentLikes::TESTIMONY, (string) $this->id, $viewer),
            'repliesCount' => $this->replies_count,
            'parentId'    => $this->parent_id,
            // Auteur ou administrateur (CommentPolicy::delete), jamais un modérateur.
            'canDelete'   => $viewer !== null && ($viewer->id === $this->user_id || $viewer->isAdmin()),
            'isReply'     => !is_null($this->parent_id),
            'replies'     => CommentResource::collection($this->whenLoaded('replies')),
            'createdAt'   => $this->created_at?->toIso8601String(),
            'updatedAt'   => $this->updated_at?->toIso8601String(),
        ];
    }
}

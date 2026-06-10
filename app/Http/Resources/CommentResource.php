<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'testimonyId' => $this->testimony_id,
            'userId'      => $this->user_id,
            'user'        => new UserResource($this->whenLoaded('user')),
            'text'        => $this->body,
            'likesCount'  => $this->likes_count,
            'repliesCount' => $this->replies_count,
            'parentId'    => $this->parent_id,
            'isReply'     => !is_null($this->parent_id),
            'replies'     => CommentResource::collection($this->whenLoaded('replies')),
            'createdAt'   => $this->created_at?->toIso8601String(),
            'updatedAt'   => $this->updated_at?->toIso8601String(),
        ];
    }
}

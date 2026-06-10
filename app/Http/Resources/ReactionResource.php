<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'type'        => $this->type->value,
            'userId'      => $this->user_id,
            'testimonyId' => $this->testimony_id,
            'createdAt'   => $this->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModerationItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lastLog = $this->moderationLogs->sortByDesc('created_at')->first();

        return [
            'id'              => $this->id,
            'author' => [
                'uid'         => $this->user->id,
                'displayName' => $this->user->display_name,
                'country'     => $this->user->country,
                'avatarUrl'   => $this->user->avatar_url,
            ],
            'title'           => $this->title,
            'category'        => $this->category_slug,
            'type'            => $this->type->value,
            'status'          => $this->status->value,
            'submittedAt'     => $this->created_at?->toIso8601String(),
            'contentPreview'  => $this->body_text ? substr($this->body_text, 0, 200) : null,
            'rejectionReason' => $lastLog?->rejection_reason?->value,
            'moderatorNote'   => $lastLog?->moderator_note,
            'mediaUrl'        => $this->media_url,
            'coverUrl'        => $this->cover_url,
        ];
    }
}

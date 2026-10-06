<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Commentaire sous un verset du jour (docs/fonctionnalites/verset-du-jour.md). */
class DailyVerseCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user() ?? $request->user('sanctum');
        $mine   = $viewer?->id === $this->user_id;

        return [
            'id'        => $this->id,
            // Texte lisible (« @Nom ») et mentions cliquables — docs/fonctionnalites/mentions.md
            'body'      => \App\Services\Mentions::plain($this->body),
            'mentions'  => \App\Services\Mentions::of($this->body),
            'user'      => [
                'id'          => $this->user_id,
                'displayName' => $this->user?->display_name ?? 'Membre',
                'initials'    => $this->user?->initials,
                'avatarUrl'   => $this->user?->avatar_url,
                'isVerified'  => (bool) $this->user?->isVerified(),
            ],
            'isMine'    => $mine,
            'isHidden'  => $this->isHidden(),
            'canDelete' => $this->canBeDeletedBy($viewer),
            'canReport' => $viewer !== null && !$mine,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}

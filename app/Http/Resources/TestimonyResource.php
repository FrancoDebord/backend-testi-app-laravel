<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestimonyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;

        return [
            'id'           => $this->id,
            'userId'       => $this->user_id,
            'user'         => new UserResource($this->whenLoaded('user')),
            'title'        => $this->title,
            'type'         => $this->type->value,
            'category'     => $this->category_slug,
            'bodyText'     => $this->body_text,
            'mediaUrl'     => $this->media_url,
            // Versions allégées, débit croissant ; [] si aucune (docs/fonctionnalites/qualites-media.md).
            'renditions'   => \App\Models\MediaFile::renditionsForApi($this->renditions),
            // État des versions allégées (détail d'un témoignage) : done, pending, processing, failed, none ou null.
            'renditionsStatus' => $this->renditionsStatus(),
            'coverUrl'     => $this->cover_url,
            'shareUrl'     => $this->share_url ?? \App\Models\Testimony::shareUrlFor($this->id),
            'duration'     => $this->duration_sec,
            'bibleVerse'   => $this->bible_verse,
            'verseReference' => $this->bible_ref,
            'tags'         => $this->tags ?? [],
            'visibility'   => $this->visibility->value,
            'status'       => $this->status->value,
            'isFeatured'   => $this->isCurrentlyFeatured(),
            'isPinned'     => (bool) $this->is_featured,
            'stats' => [
                'viewsCount'     => $this->views_count,
                'likesCount'     => $this->like_count,
                'prayersCount'   => $this->prayer_count,
                'commentsCount'  => $this->comment_count,
                'sharesCount'    => $this->share_count,
                'bookmarksCount' => $this->bookmark_count,
            ],
            'isLikedByMe'     => $userId ? $this->isLikedBy($userId) : false,
            'isPrayedByMe'    => $userId ? $this->isPrayedBy($userId) : false,
            'isBookmarkedByMe' => $userId ? $this->isSavedBy($userId) : false,
            'createdAt'    => $this->created_at?->toIso8601String(),
            'updatedAt'    => $this->updated_at?->toIso8601String(),
        ];
    }
}

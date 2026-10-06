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
            // Événement auquel le témoignage est rattaché (docs/fonctionnalites/evenements.md).
            'eventId'      => $this->event_id,
            // Parole prophétique accomplie : montrée si l'auteur l'a rendue publique ; à l'auteur toujours.
            'prophecy'     => $this->prophecyFor($request),
            'bodyText'     => $this->body_text,
            'mediaUrl'     => $this->media_url,
            // Vidéo YouTube (docs/fonctionnalites/videos-youtube.md) : l'application l'affiche avec le lecteur YouTube.
            'youtubeId'    => $this->youtube_id,
            'youtubeUrl'   => $this->youtube_id ? \App\Support\YouTube::watchUrl($this->youtube_id) : null,
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
            // Preuves (docs/fonctionnalites/preuves.md) : auteur et équipe, ou tout le monde si l'auteur l'a accepté.
            'proofsPublic' => (bool) $this->proofs_public,
            'proofs'       => $this->when(
                \App\Services\TestimonyProofs::canView($request->user() ?? $request->user('sanctum'), $this->resource),
                fn () => $this->proofs->map(fn ($p) => [
                    'id'       => $p->id,
                    'position' => $p->position,
                    'name'     => $p->original_name,
                    'mimeType' => $p->mime_type,
                    'size'     => $p->size_bytes,
                    'isPdf'    => $p->isPdf(),
                    'url'      => url("/api/v1/testimonies/{$this->id}/proofs/{$p->id}"),
                ])->values()
            ),
            'createdAt'    => $this->created_at?->toIso8601String(),
            'updatedAt'    => $this->updated_at?->toIso8601String(),
        ];
    }

    /** Parole prophétique accomplie (docs/fonctionnalites/paroles-prophetiques.md). */
    private function prophecyFor(Request $request): ?array
    {
        $prophecy = $this->prophecy;
        if (!$prophecy) return null;
        $viewer = $request->user() ?? $request->user('sanctum');
        if (!$prophecy->is_public && $viewer?->id !== $this->user_id) return null;

        return [...$prophecy->publicPayload(), 'isPublic' => $prophecy->is_public];
    }
}

<?php

namespace App\Http\Resources;

use App\Enums\ReactionType;
use App\Models\User;
use App\Services\Reposts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestimonyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Routes publiques : la personne connectée est lue sur le garde sanctum.
        $viewer = $request->user() ?? $request->user('sanctum');
        $userId = $viewer?->id;
        // Réactions de la personne : une « j'aime » et une « je prie » au plus (docs/fonctionnalites/reactions.md).
        $myTypes = $userId
            ? $this->reactions()->where('user_id', $userId)->get()->map(fn ($r) => $r->type)
            : collect();
        $appreciation = $myTypes->first(fn (ReactionType $t) => $t->group() === 'appreciation');
        $prayer       = $myTypes->first(fn (ReactionType $t) => $t->group() === 'prayer');
        // Vidéo Bunny Stream ou ancienne vidéo LWS (docs/fonctionnalites/videos-bunny-stream.md).
        $videos = app(\App\Services\BunnyStreamVideos::class);
        $video  = $videos->payload($this->resource);
        $bunnyReady = $video !== null && $video['hls_url'] !== null;

        return [
            'id'           => $this->id,
            'userId'       => $this->user_id,
            'user'         => new UserResource($this->whenLoaded('user')),
            'title'        => $this->title,
            'type'         => $this->type->value,
            // standard | conversion ; détails (année, lieu, verset) d'un témoignage de conversion, sinon null
            // (docs/fonctionnalites/temoignage-de-conversion.md).
            'kind'         => $this->kind?->value ?? \App\Enums\TestimonyKind::Standard->value,
            'isConversion' => $this->resource->isConversion(),
            'conversion'   => $this->resource->conversionDetails(),
            'category'     => $this->category_slug,
            // Événement auquel le témoignage est rattaché (docs/fonctionnalites/evenements.md).
            'eventId'      => $this->event_id,
            // Parole prophétique accomplie : montrée si l'auteur l'a rendue publique ; à l'auteur toujours.
            'prophecy'     => $this->prophecyFor($request),
            // Texte lisible (« @Nom ») + mentions cliquables (docs/fonctionnalites/mentions.md).
            'bodyText'     => $this->body_text === null ? null : \App\Services\Mentions::plain($this->body_text),
            'mentions'     => \App\Services\Mentions::of($this->body_text),
            // Vidéo Bunny sans ancien fichier : meilleure qualité MP4, pour les versions de l'application sans objet « video ».
            'mediaUrl'     => $this->media_url ?? ($bunnyReady ? (collect((array) $video['mp4_urls'])->last()) : null),
            // Vidéo YouTube (docs/fonctionnalites/videos-youtube.md) : l'application l'affiche avec le lecteur YouTube.
            'youtubeId'    => $this->youtube_id,
            'youtubeUrl'   => $this->youtube_id ? \App\Support\YouTube::watchUrl($this->youtube_id) : null,
            // Versions allégées, débit croissant ; [] si aucune (docs/fonctionnalites/qualites-media.md).
            'renditions'   => \App\Models\MediaFile::renditionsForApi($this->renditions),
            // État des versions allégées (détail d'un témoignage) : done, pending, processing, failed, none ou null.
            'renditionsStatus' => $this->renditionsStatus(),
            'coverUrl'     => $this->cover_url ?? ($bunnyReady ? $video['thumbnail_url'] : null),
            // { status, hls_url, thumbnail_url, mp4_urls {"240": …}, duration } ; null sans vidéo (texte, audio, YouTube).
            'video'        => $video,
            'shareUrl'     => $this->share_url ?? \App\Models\Testimony::shareUrlFor($this->id),
            'duration'     => $this->duration_sec,
            'bibleVerse'   => $this->bible_verse,
            'verseReference' => $this->bible_ref,
            'tags'         => $this->tags ?? [],
            'visibility'   => $this->visibility->value,
            'status'       => $this->status->value,
            // Motif du refus (« Mes témoignages » de l'application) : auteur et modération seulement.
            'rejectionReason' => $this->rejectionReasonFor($request),
            'isFeatured'   => $this->isCurrentlyFeatured(),
            // Mise à la une manuelle en cours (dates comprises).
            'isPinned'     => $this->resource->isManuallyFeatured(),
            // Gestion des témoignages (docs/fonctionnalites/gestion-des-temoignages.md) :
            // badges d'affichage : verified (label « Témoignage approuvé »), featured (à la une manuelle).
            'badges'       => $this->resource->badges(),
            // GET /testimonies/featured : manual (mis à la une) | recent (complément) ; ailleurs manual ou null.
            'featured_source' => $this->resource->getAttribute('featured_source') ?? ($this->resource->isManuallyFeatured() ? 'manual' : null),
            'featuredOrder' => $this->featured_order,
            'featuredFrom'  => $this->featured_from?->toIso8601String(),
            'featuredUntil' => $this->featured_until?->toIso8601String(),
            'isVerified'    => (bool) $this->is_verified && $this->status === \App\Enums\TestimonyStatus::Approved,
            'verifiedAt'    => $this->is_verified ? $this->verified_at?->toIso8601String() : null,
            // Date de la dernière décision (approbation, refus ; approbation automatique : date de création).
            'moderatedAt'   => $this->moderated_at?->toIso8601String(),
            'stats' => [
                'viewsCount'     => $this->views_count,
                'likesCount'     => $this->like_count,
                'prayersCount'   => $this->prayer_count,
                'commentsCount'  => $this->comment_count,
                'sharesCount'    => $this->share_count,
                'bookmarksCount' => $this->bookmark_count,
                // Partages internes sur TestiApp (docs/fonctionnalites/partage-interne.md).
                'repostsCount'   => (int) ($this->repost_count ?? 0),
            ],
            'isRepostedByMe'  => $userId ? $this->reposts()->where('user_id', $userId)->exists() : false,
            'isLikedByMe'     => $appreciation !== null,
            'isPrayedByMe'    => $prayer !== null,
            // Réaction « j'aime » choisie (like, love, fire) et réaction de prière (pray, amen, worship).
            'myReaction'       => $appreciation?->value,
            'myPrayerReaction' => $prayer?->value,
            // Supprimer : l'auteur ou un administrateur (jamais un modérateur).
            'canDelete'       => $this->resource->canBeDeletedBy($viewer),
            'isBookmarkedByMe' => $userId ? $this->isSavedBy($userId) : false,
            // Réglage « Qui peut commenter » de l'auteur appliqué à la personne connectée.
            'canComment'      => $this->acceptsCommentsFrom($viewer),
            // Partage interne le plus parlant pour la personne (compte suivi d'abord) : bandeau « X a partagé »
            // dans tous les fils et listes. « Mon fil » le remplace par le partage précis (RepostController::feedItem).
            ...$this->repostHighlight($request, $viewer),
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

    /** Motif et note du dernier refus, lisibles (« Faux témoignage — note »), pour l'auteur et la modération. */
    private function rejectionReasonFor(Request $request): ?string
    {
        $viewer = $request->user() ?? $request->user('sanctum');
        if ($this->status !== \App\Enums\TestimonyStatus::Rejected
            || !$viewer || ($viewer->id !== $this->user_id && !$viewer->canModerate())) {
            return null;
        }
        // Motif enregistré au refus (gestion-des-temoignages.md) ; anciens refus : dernier journal de modération.
        if (filled($this->rejection_reason)) {
            return $this->rejection_reason;
        }
        $log = $this->moderationLogs()->where('action', 'rejected')->latest('created_at')->first();
        if (!$log) {
            return null;
        }
        $text = implode(' — ', array_filter([$log->rejection_reason?->label(), trim((string) $log->moderator_note)]));

        return $text !== '' ? $text : null;
    }

    /** @return array{repostId?: string, repostedBy?: array, repostComment?: ?string, repostedAt?: ?string} */
    private function repostHighlight(Request $request, ?User $viewer): array
    {
        $repost = app(Reposts::class)->highlightFor($this->resource, $viewer);
        if (!$repost) return [];

        return [
            'repostId'      => $repost->id,
            'repostedBy'    => (new UserResource($repost->user))->toArray($request),
            'repostComment' => $repost->comment,
            'repostedAt'    => $repost->created_at?->toIso8601String(),
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

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Direct, au format de l'API mobile. */
class LiveSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user() ?? $request->user('sanctum');

        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'description'     => $this->description,
            'category'        => $this->category_slug,
            'status'          => $this->status->value,
            'statusLabel'     => $this->status->label(),
            'commentsEnabled' => $this->comments_enabled,
            'speakersEnabled' => (bool) $this->speakers_enabled, // demandes d'intervention ouvertes
            'host'            => [
                'id'          => $this->host_id,
                'displayName' => $this->host?->display_name,
                'initials'    => $this->host?->initials,
                'avatarUrl'   => $this->host?->avatar_url,
            ],
            'stats' => [
                'peakViewers'  => $this->peak_viewers,
                'commentCount' => $this->comment_count,
                'reactions'    => $this->reactionCounts(),
            ],
            'pinnedComment' => $this->pinnedCommentPayload(),
            // Enregistrement → témoignage vidéo. « replayTestimonyId » n'est renseigné qu'une fois la vidéo publiée.
            'recording'   => [
                'enabled'           => (bool) $this->record,
                'status'            => $this->recording_status,
                'label'             => $this->recordingLabel(),
                'durationSec'       => $this->recording_duration,
                'replayTestimonyId' => $this->testimony?->status?->value === 'approved' ? $this->testimony_id : null,
            ],
            'isHost'      => $this->isHost($user),
            'canModerate' => $this->canBeModeratedBy($user),
            'webUrl'      => route('lives.show', $this->id),
            'startedAt'   => $this->started_at?->toIso8601String(),
            'endedAt'     => $this->ended_at?->toIso8601String(),
            'endReason'   => $this->end_reason,
            'createdAt'   => $this->created_at?->toIso8601String(),
        ];
    }
}

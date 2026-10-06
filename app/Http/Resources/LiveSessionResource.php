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
            // Caméra IP / encodeur : adresse et clé visibles du seul diffuseur (docs/fonctionnalites/lives-camera-ip.md)
            'source'          => $this->source ?? 'browser',
            // Clé ajoutée seulement pour le diffuseur (la ressource est aussi fusionnée à la main : pas de when()).
            ...($this->usesExternalCamera() && $user?->id === $this->host_id
                ? ['camera' => ['url' => $this->ingress_url, 'streamKey' => $this->ingress_stream_key, 'sourceUrl' => self::maskCameraUrl($this->camera_url)]]
                : []),
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
            // Événement diffusé (docs/fonctionnalites/evenements.md)
            'event'       => $this->event_id ? ['id' => $this->event_id, 'title' => $this->event?->title] : null,
            // Salle d'une session de prière (docs/fonctionnalites/sessions-de-priere.md)
            'prayerSession' => $this->prayer_session_id ? ['id' => $this->prayer_session_id, 'title' => $this->prayerSession?->title] : null,
            'isHost'      => $this->isHost($user),
            'canModerate' => $this->canBeModeratedBy($user),
            'webUrl'      => route('lives.show', $this->id),
            'startedAt'   => $this->started_at?->toIso8601String(),
            'endedAt'     => $this->ended_at?->toIso8601String(),
            'endReason'   => $this->end_reason,
            'createdAt'   => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Adresse du flux de la caméra sans secret : mot de passe et phrase secrète SRT
     * remplacés par « •••• » (jamais renvoyés en clair, docs/fonctionnalites/lives-camera-ip.md).
     */
    public static function maskCameraUrl(?string $url): ?string
    {
        if (blank($url)) return $url;
        $url = preg_replace('#^([a-z][a-z0-9+.-]*://[^:@/?]*):[^?]*@#i', '$1:••••@', $url, 1);

        return preg_replace('#([?&]passphrase=)[^&]*#i', '$1••••', $url);
    }
}

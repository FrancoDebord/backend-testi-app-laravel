<?php

namespace App\Http\Resources;

use App\Enums\LiveStatus;
use App\Models\PrayerSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Session de prière, au format de l'API mobile. Voir docs/fonctionnalites/sessions-de-priere.md
 *
 * Charger `host`, `event`, `lives` (actives) et `withRegistrationOf($viewer)`.
 *
 * @mixin PrayerSession
 */
class PrayerSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user() ?? $request->user('sanctum');
        $isHost = $this->isHost($viewer);
        $live   = $this->activeLive();
        // Salle en préparation : montrée à l'hôte et à la modération seulement.
        if ($live && $live->status === LiveStatus::Preparing && !$live->canBeModeratedBy($viewer)) {
            $live = null;
        }

        return [
            'type'            => 'prayer_session',
            'id'              => $this->id,
            'title'           => $this->title,
            'description'     => $this->description,
            'topics'          => array_values($this->topics ?? []),
            'startsAt'        => $this->starts_at?->toIso8601String(),
            'endsAt'          => $this->endsAt()->toIso8601String(),
            'durationMinutes' => $this->duration_minutes,
            'visibility'      => $this->visibility,
            'visibilityLabel' => PrayerSession::VISIBILITY_LABELS[$this->visibility] ?? $this->visibility,
            'status'          => $this->status,      // scheduled | cancelled
            'phase'           => $this->phase(),     // upcoming | live | ended | cancelled
            'phaseLabel'      => $this->phaseLabel(),
            'host'            => [
                'id'          => $this->host_id,
                'displayName' => $this->host?->display_name,
                'initials'    => $this->host?->initials,
                'avatarUrl'   => $this->host?->avatar_url,
                'isVerified'  => (bool) $this->host?->isVerified(),
            ],
            'event'            => $this->event_id && $this->event ? ['id' => $this->event_id, 'title' => $this->event->title] : null,
            'participantCount' => $this->participant_count,
            'isRegistered'     => $this->isRegistered($viewer),
            'isHost'           => $isHost,
            'canEdit'          => $this->canBeEditedBy($viewer),
            'canDelete'        => $this->canBeDeletedBy($viewer),
            // Ouvrir la salle : l'hôte, de 30 min avant l'heure jusqu'à la fin prévue (ou reprendre la salle ouverte).
            'canStart'         => $this->canBeStartedBy($viewer),
            'opensAt'          => $this->starts_at?->copy()->subMinutes(PrayerSession::OPEN_BEFORE_MINUTES)->toIso8601String(),
            // Salle active (direct) : la rejoindre avec /lives/{id} (ou son studio pour l'hôte).
            'live'             => $live ? ['id' => $live->id, 'status' => $live->status->value] : null,
            'webUrl'           => route('prayer.sessions.show', $this->id),
            'createdAt'        => $this->created_at?->toIso8601String(),
        ];
    }
}

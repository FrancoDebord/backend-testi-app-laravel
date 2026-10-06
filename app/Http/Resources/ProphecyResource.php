<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Parole prophétique, pour son seul auteur. Voir docs/fonctionnalites/paroles-prophetiques.md */
class ProphecyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'receivedOn'    => $this->received_on?->toDateString(),
            'bodyText'      => $this->body_text,
            'audioUrl'      => $this->audio_url,
            'audioDuration' => $this->audio_duration,
            'givenBy'       => $this->given_by,
            'dueOn'         => $this->due_on?->toDateString(),
            'status'        => $this->status,                 // waiting | fulfilled
            'fulfilledOn'   => $this->fulfilled_on?->toDateString(),
            'testimonyId'   => $this->testimony_id,
            'isPublic'      => $this->is_public,
            'reminder'      => $this->reminder_frequency ? [
                'frequency' => $this->reminder_frequency,     // daily | weekly
                'time'      => $this->reminder_time,          // HH:MM, heure du téléphone
                'weekday'   => $this->reminder_weekday,       // 1 (lundi) … 7, hebdomadaire
            ] : null,
            'prayerCount'   => $this->prayer_count,
            'lastPrayedAt'  => $this->last_prayed_at?->toIso8601String(),
            'createdAt'     => $this->created_at?->toIso8601String(),
            'updatedAt'     => $this->updated_at?->toIso8601String(),
        ];
    }
}

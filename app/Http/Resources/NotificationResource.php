<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'type'           => $this->type->value,
            'actorId'        => $this->actor_id,
            'actorName'      => $this->actor_name,
            'actorAvatar'    => $this->actor_avatar,
            'testimonyId'    => $this->testimony_id,
            'testimonyTitle' => $this->testimony_title,
            'message'        => $this->message,
            // Direct annoncé (type live_started) : ouvre l'écran du direct.
            'liveId'         => is_array($this->payload) ? ($this->payload['live_id'] ?? null) : null,
            // Requête / session de prière (types prayer_*) : ouvre l'écran correspondant.
            'prayerRequestId' => is_array($this->payload) ? ($this->payload['prayer_request_id'] ?? null) : null,
            'prayerSessionId' => is_array($this->payload) ? ($this->payload['prayer_session_id'] ?? null) : null,
            'isRead'         => $this->is_read,
            'createdAt'      => $this->created_at?->toIso8601String(),
        ];
    }
}

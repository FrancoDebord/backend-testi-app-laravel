<?php

namespace App\Http\Resources;

use App\Enums\LiveStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Événement chrétien, au format de l'API mobile. Voir docs/fonctionnalites/evenements.md
 *
 * Charger `organizer`, `images` et `lives` (directs actifs) pour éviter les requêtes en boucle.
 */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user() ?? $request->user('sanctum');
        $live = $this->relationLoaded('lives')
            ? $this->lives->first(fn ($l) => in_array($l->status, [LiveStatus::Live, LiveStatus::Preparing], true))
            : $this->activeLive();
        // Un direct en préparation n'est montré qu'aux gestionnaires.
        if ($live && $live->status === LiveStatus::Preparing && !$this->canBeManagedBy($user)) {
            $live = null;
        }

        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'description'     => $this->description,
            'type'            => $this->type->value,
            'typeLabel'       => $this->type->label(),
            'status'          => $this->status->value,
            'statusLabel'     => $this->status->label(),
            'phase'           => $this->phase(),          // upcoming | ongoing | past
            'phaseLabel'      => $this->phaseLabel(),
            'startsAt'        => $this->starts_at?->toIso8601String(),
            'endsAt'          => $this->ends_at?->toIso8601String(),
            'location'        => $this->location,
            'city'            => $this->city,
            'country'         => $this->country,
            'address'         => $this->address,
            'latitude'        => $this->latitude,
            'longitude'       => $this->longitude,
            'directionsUrl'   => $this->directionsUrl(),
            'guests'          => array_values($this->guests ?? []),
            'commentsEnabled' => $this->comments_enabled,
            'images'          => $this->images->map(fn ($i) => ['id' => $i->id, 'url' => $i->url])->values(),
            'coverUrl'        => $this->images->first()?->url,
            'organizer'       => [
                'id'               => $this->organizer_id,
                'displayName'      => $this->organizer?->organization_name ?: $this->organizer?->display_name,
                'initials'         => $this->organizer?->initials,
                'avatarUrl'        => $this->organizer?->avatar_url,
                'isOrganization'   => (bool) $this->organizer?->isOrganization(),
                'isVerified'       => (bool) $this->organizer?->isVerified(),
            ],
            'stats' => [
                'going'       => $this->going_count,
                'notGoing'    => $this->not_going_count,
                'comments'    => $this->comment_count,
                'testimonies' => (int) ($this->testimonies_count ?? $this->testimoniesVisibleTo($user)->count()),
            ],
            'myParticipation'     => $this->participationOf($user), // going | not_going | null
            'acceptsParticipation' => $this->acceptsParticipation(),
            'canManage'       => $this->canBeManagedBy($user),
            // Responsable : supprimer, désigner les co-gestionnaires (organisateur, ses gestionnaires, admin).
            'canAdministrate' => $this->canBeAdministeredBy($user),
            // Co-gestionnaires (2 au plus) : visibles des seuls gestionnaires.
            'managers'        => $this->canBeManagedBy($user)
                ? $this->managers->map(fn ($m) => [
                    'id' => $m->id, 'displayName' => $m->display_name,
                    'initials' => $m->initials, 'avatarUrl' => $m->avatar_url,
                ])->values()
                : [],
            'live'            => $live ? ['id' => $live->id, 'status' => $live->status->value, 'title' => $live->title] : null,
            'webUrl'          => route('events.show', $this->id),
            'createdAt'       => $this->created_at?->toIso8601String(),
        ];
    }
}

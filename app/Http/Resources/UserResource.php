<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /** Réponse destinée à la personne elle-même (connexion, inscription) : coordonnées incluses. */
    private bool $forOwner = false;

    public static function owner($user): static
    {
        $resource = new static($user);
        $resource->forOwner = true;

        return $resource;
    }

    public function toArray(Request $request): array
    {
        // Coordonnées privées : seulement pour la personne elle-même et les administrateurs
        // (ce format sert aussi pour l'auteur d'un témoignage ou d'un commentaire, visibles de tous).
        $viewer  = $request->user() ?? $request->user('sanctum');
        $private = $this->forOwner || ($viewer && ($viewer->id === $this->id || $viewer->isAdmin()));

        return [
            'id'               => $this->id,
            'display_name'     => $this->display_name,
            'email'            => $private ? $this->email : null,
            'phone'            => $private ? $this->phone : null,
            'phone_country'    => $private ? $this->phone_country : null,
            'is_phone_verified'=> $private && $this->hasVerifiedPhone(),
            'avatar_url'       => $this->avatar_url,
            'cover_url'        => $this->cover_url, // photo de couverture (docs/fonctionnalites/photo-de-couverture.md)
            'country'          => $this->country,
            'bio'              => $this->bio,
            'role'             => $this->role->value,
            'status'           => $this->status->value,
            'is_email_verified'=> !is_null($this->email_verified_at),
            'testimony_count'  => $this->publishedTestimonyCount(), // nombre réel quand il est chargé (Communauté, abonnements)
            'like_count'       => $this->like_count,
            'prayer_count'     => $this->prayer_count,
            'follower_count'   => $this->follower_count,
            'following_count'  => $this->following_count,
            'can_publish'      => $this->canPublish(),
            'can_moderate'     => $this->canModerate(),
            'is_admin'         => $this->isAdmin(),
            // Comptes organisation (docs/fonctionnalites/comptes-organisation.md)
            'account_type'         => $this->account_type?->value ?? 'individual',
            'organization_name'    => $this->organization_name,
            'organization_type'    => $this->organization_type?->value,
            'organization_city'    => $this->organization_city,
            'organization_website' => $this->organization_website,
            'is_verified'          => $this->isVerified(),
            // Abonnement de la personne connectée (profil, page Communauté) : présent seulement s'il a été chargé.
            'is_following'         => $this->when(array_key_exists('is_followed', $this->resource->getAttributes()), fn () => (bool) $this->is_followed),
            'verification_status'  => $this->verification_status?->value,
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}

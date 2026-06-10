<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'display_name'     => $this->display_name,
            'email'            => $this->email,
            'phone'            => $this->phone,
            'avatar_url'       => $this->avatar_url,
            'country'          => $this->country,
            'bio'              => $this->bio,
            'role'             => $this->role->value,
            'is_email_verified'=> !is_null($this->email_verified_at),
            'testimony_count'  => $this->testimony_count,
            'like_count'       => $this->like_count,
            'prayer_count'     => $this->prayer_count,
            'follower_count'   => $this->follower_count,
            'following_count'  => $this->following_count,
            'can_publish'      => $this->canPublish(),
            'can_moderate'     => $this->canModerate(),
            'is_admin'         => $this->isAdmin(),
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Message d'encouragement sous une requête de prière (verset facultatif). */
class PrayerRequestMessage extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['prayer_request_id', 'user_id', 'body', 'bible_reference'];

    public function prayerRequest(): BelongsTo
    {
        return $this->belongsTo(PrayerRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Son auteur, l'auteur de la requête et la modération. */
    public function canBeDeletedBy(?User $user, PrayerRequest $request): bool
    {
        return $user !== null && ($user->id === $this->user_id || $request->isAuthor($user) || $user->canModerate());
    }

    /** Format JSON (API et site). */
    public function toPayload(?User $viewer, PrayerRequest $request): array
    {
        return [
            'id'             => $this->id,
            'body'           => $this->body,
            'bibleReference' => $this->bible_reference,
            'author'         => [
                'id'          => $this->user_id,
                'displayName' => $this->user?->display_name ?? 'Utilisateur',
                'initials'    => $this->user?->initials,
                'avatarUrl'   => $this->user?->avatar_url,
                'isVerified'  => (bool) $this->user?->isVerified(),
            ],
            'isMine'    => $viewer !== null && $viewer->id === $this->user_id,
            'canDelete' => $this->canBeDeletedBy($viewer, $request),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}

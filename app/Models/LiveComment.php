<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveComment extends Model
{
    use HasUuids;

    protected $fillable = ['live_session_id', 'user_id', 'body', 'is_hidden', 'hidden_by'];

    protected function casts(): array
    {
        return ['is_hidden' => 'boolean'];
    }

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Format commun web / mobile / messages temps réel. */
    public function toPayload(): array
    {
        return [
            'id'        => $this->id,
            // Mentions « @ » : texte affichable + comptes mentionnés (docs/fonctionnalites/mentions.md)
            'body'      => \App\Services\Mentions::plain($this->body),
            'mentions'  => \App\Services\Mentions::of($this->body),
            'createdAt' => $this->created_at?->toIso8601String(),
            'user'      => [
                'id'          => $this->user_id,
                'displayName' => $this->user?->display_name,
                'initials'    => $this->user?->initials,
                'avatarUrl'   => $this->user?->avatar_url,
            ],
        ];
    }
}

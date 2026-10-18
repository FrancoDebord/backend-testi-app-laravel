<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Commentaire sous un verset du jour. Retiré automatiquement après AUTO_HIDE_REPORTS signalements
 * distincts ; supprimable par son auteur et la modération. Voir docs/fonctionnalites/verset-du-jour.md
 */
class DailyVerseComment extends Model
{
    use HasUuids, SoftDeletes;

    public const AUTO_HIDE_REPORTS = 3;

    protected $fillable = ['daily_verse_id', 'user_id', 'body', 'report_count', 'hidden_at'];

    protected function casts(): array
    {
        return [
            'report_count' => 'integer',
            'hidden_at'    => 'datetime',
            // « J'aime » (docs/fonctionnalites/jaime-commentaires.md) : tenu par App\Services\CommentLikes.
            'likes_count'  => 'integer',
        ];
    }

    public function verse(): BelongsTo
    {
        return $this->belongsTo(DailyVerse::class, 'daily_verse_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    /** Visible de la personne : retiré → auteur et modération seulement. */
    public function isVisibleTo(?User $user): bool
    {
        return !$this->isHidden() || ($user !== null && ($user->id === $this->user_id || $user->canModerate()));
    }

    public function canBeDeletedBy(?User $user): bool
    {
        // Auteur ou administrateur (un modérateur ne supprime pas ce qu'un autre a écrit).
        return $user !== null && ($user->id === $this->user_id || $user->isAdmin());
    }
}

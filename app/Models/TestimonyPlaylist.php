<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Liste de témoignages d'un compte (comme une playlist YouTube), dans l'ordre choisi par son auteur.
 * Lecture : App\Services\TestimonyPlaylists. Voir docs/fonctionnalites/listes-de-temoignages.md
 */
class TestimonyPlaylist extends Model
{
    use HasUuids;

    public const TITLE_MAX       = 120;
    public const DESCRIPTION_MAX = 1000;
    public const ITEMS_MAX       = 200;

    protected $fillable = ['user_id', 'title', 'description'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Témoignages dans l'ordre de la liste (tous, y compris ceux que la personne ne peut pas voir). */
    public function testimonies(): BelongsToMany
    {
        return $this->belongsToMany(Testimony::class, 'testimony_playlist_items', 'playlist_id', 'testimony_id')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $user->id === $this->user_id;
    }
}

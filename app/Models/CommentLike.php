<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * « J'aime » d'une personne sur un commentaire (témoignage, événement ou verset du jour).
 * likeable_type : testimony_comment | event_comment | verse_comment. Voir App\Services\CommentLikes
 * et docs/fonctionnalites/jaime-commentaires.md
 */
class CommentLike extends Model
{
    protected $fillable = ['likeable_type', 'likeable_id', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

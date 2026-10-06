<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Partage interne d'un témoignage (« X a partagé ») : l'auteur d'origine reste toujours affiché,
 * le contenu n'est jamais copié. Voir docs/fonctionnalites/partage-interne.md
 */
class TestimonyRepost extends Model
{
    use HasUuids;

    public const COMMENT_MAX = 500;

    protected $fillable = ['user_id', 'testimony_id', 'comment'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function testimony(): BelongsTo
    {
        return $this->belongsTo(Testimony::class);
    }
}

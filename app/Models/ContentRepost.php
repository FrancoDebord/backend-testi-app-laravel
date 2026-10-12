<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Partage interne d'un événement, d'une requête ou d'une session de prière (App\Services\ContentInteractions). */
class ContentRepost extends Model
{
    use HasUuids;

    public const COMMENT_MAX = 500;

    protected $fillable = ['user_id', 'subject_type', 'subject_id', 'comment'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

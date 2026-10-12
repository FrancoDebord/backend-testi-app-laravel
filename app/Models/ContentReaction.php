<?php

namespace App\Models;

use App\Enums\ReactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Réaction à un événement, une requête ou une session de prière (App\Services\ContentInteractions). */
class ContentReaction extends Model
{
    protected $fillable = ['user_id', 'subject_type', 'subject_id', 'type'];

    protected function casts(): array
    {
        return ['type' => ReactionType::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

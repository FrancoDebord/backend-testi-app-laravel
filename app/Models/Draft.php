<?php

namespace App\Models;

use App\Enums\TestimonyType;
use App\Enums\TestimonyVisibility;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Draft extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id', 'type', 'title', 'category_id', 'category_slug',
        'body_text', 'audio_path', 'video_path', 'cover_path', 'thumbnail_path',
        'bible_verse', 'bible_ref', 'tags', 'visibility', 'consent_given',
    ];

    protected function casts(): array
    {
        return [
            'type'          => TestimonyType::class,
            'visibility'    => TestimonyVisibility::class,
            'tags'          => 'array',
            'consent_given' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}

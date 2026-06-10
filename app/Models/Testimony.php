<?php

namespace App\Models;

use App\Enums\TestimonyType;
use App\Enums\TestimonyStatus;
use App\Enums\TestimonyVisibility;
use App\Enums\TestimonyCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Testimony extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id', 'category_id', 'title', 'type', 'category_slug',
        'body_text', 'media_url', 'cover_url', 'duration_sec',
        'bible_verse', 'bible_ref', 'tags', 'visibility', 'status',
        'is_featured', 'views_count', 'like_count', 'prayer_count',
        'comment_count', 'share_count', 'bookmark_count',
        'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'type'        => TestimonyType::class,
            'status'      => TestimonyStatus::class,
            'visibility'  => TestimonyVisibility::class,
            'tags'        => 'array',
            'is_featured' => 'boolean',
            'approved_at' => 'datetime',
            'duration_sec'    => 'integer',
            'views_count'     => 'integer',
            'like_count'      => 'integer',
            'prayer_count'    => 'integer',
            'comment_count'   => 'integer',
            'share_count'     => 'integer',
            'bookmark_count'  => 'integer',
        ];
    }

    // ---------- Relations ----------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ModerationLog::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(TestimonyReport::class);
    }

    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_testimonies', 'testimony_id', 'user_id')
                    ->withPivot('saved_at');
    }

    // ---------- Scopes ----------

    public function scopePublished($query)
    {
        return $query->where('status', TestimonyStatus::Approved)->where('visibility', TestimonyVisibility::Public);
    }

    public function scopePending($query)
    {
        return $query->where('status', TestimonyStatus::Pending);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true)->where('status', TestimonyStatus::Approved);
    }

    public function scopeOfCategory($query, string $slug)
    {
        return $query->where('category_slug', $slug);
    }

    public function scopeAfter($query, ?string $after)
    {
        if ($after) {
            $query->where('updated_at', '>', $after);
        }
        return $query;
    }

    // ---------- Helpers ----------

    public function formattedDuration(): string
    {
        $mins = intdiv($this->duration_sec, 60);
        $secs = $this->duration_sec % 60;
        return sprintf('%d:%02d', $mins, $secs);
    }

    public function isLikedBy(?string $userId): bool
    {
        if (!$userId) return false;
        return $this->reactions()->where('user_id', $userId)->where('type', 'like')->exists();
    }

    public function isPrayedBy(?string $userId): bool
    {
        if (!$userId) return false;
        return $this->reactions()->where('user_id', $userId)->where('type', 'pray')->exists();
    }

    public function isSavedBy(?string $userId): bool
    {
        if (!$userId) return false;
        return $this->savedByUsers()->where('user_id', $userId)->exists();
    }
}

<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    use HasUuids;

    protected $table = 'app_notifications';
    public $timestamps = false;

    protected $fillable = [
        'recipient_id', 'actor_id', 'actor_name', 'actor_avatar',
        'type', 'testimony_id', 'testimony_title', 'comment_id',
        'message', 'payload', 'is_read', 'read_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type'       => NotificationType::class,
            'is_read'    => 'boolean',
            'payload'    => 'array',
            'created_at' => 'datetime',
            'read_at'    => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function testimony(): BelongsTo
    {
        return $this->belongsTo(Testimony::class);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('recipient_id', $userId);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeAfter($query, ?string $after)
    {
        if ($after) {
            $query->where('created_at', '>', $after);
        }
        return $query;
    }
}

<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserAccountStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids, SoftDeletes;

    protected $fillable = [
        'display_name', 'email', 'phone', 'firebase_uid', 'password',
        'avatar_url', 'country', 'bio', 'role', 'status', 'is_active',
        'email_verified_at', 'testimony_count', 'like_count', 'prayer_count',
        'follower_count', 'following_count',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'role'              => UserRole::class,
            'status'            => UserAccountStatus::class,
            'testimony_count'   => 'integer',
            'like_count'        => 'integer',
            'prayer_count'      => 'integer',
            'follower_count'    => 'integer',
            'following_count'   => 'integer',
        ];
    }

    // ---------- Relations ----------

    public function testimonies(): HasMany
    {
        return $this->hasMany(Testimony::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class, 'recipient_id');
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(Draft::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    public function socialProviders(): HasMany
    {
        return $this->hasMany(SocialAuthProvider::class);
    }

    public function savedTestimonies(): BelongsToMany
    {
        return $this->belongsToMany(Testimony::class, 'saved_testimonies', 'user_id', 'testimony_id')
                    ->withPivot('saved_at');
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')
                    ->withPivot('created_at');
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')
                    ->withPivot('created_at');
    }

    // ---------- Helpers ----------

    public function canPublish(): bool
    {
        return $this->role->canPublish();
    }

    public function canModerate(): bool
    {
        return $this->role->canModerate();
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    public function getInitialsAttribute(): string
    {
        $parts = explode(' ', trim($this->display_name));
        return strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
    }

    public function isFollowing(string $userId): bool
    {
        return $this->following()->where('following_id', $userId)->exists();
    }
}

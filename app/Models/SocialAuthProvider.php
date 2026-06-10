<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAuthProvider extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'provider', 'provider_id', 'access_token'];

    protected $hidden = ['access_token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

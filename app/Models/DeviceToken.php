<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    // access_token_id : session Sanctum de l'application qui a enregistré le jeton (supprimé à sa déconnexion).
    protected $fillable = ['user_id', 'token', 'platform', 'last_used_at', 'access_token_id'];

    protected function casts(): array
    {
        return [
            // Dernier enregistrement par l'application ou dernier push reçu (PushNotifier)
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

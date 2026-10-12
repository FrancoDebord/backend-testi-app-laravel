<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Action faite par un administrateur au nom d'un compte (docs/fonctionnalites/administrateurs-de-compte.md). */
class AccountDelegateAction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['account_id', 'actor_id', 'actor_name', 'method', 'path', 'route_name', 'status', 'created_at'];

    protected $casts = ['created_at' => 'datetime', 'status' => 'integer'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

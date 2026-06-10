<?php

namespace App\Models;

use App\Enums\ReactionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reaction extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['user_id', 'testimony_id', 'type'];

    protected function casts(): array
    {
        return [
            'type' => ReactionType::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function testimony(): BelongsTo
    {
        return $this->belongsTo(Testimony::class);
    }
}

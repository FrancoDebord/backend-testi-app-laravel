<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerseNote extends Model
{
    protected $fillable = ['user_id', 'translation', 'book', 'chapter', 'verse', 'note'];

    protected function casts(): array
    {
        return ['book' => 'integer', 'chapter' => 'integer', 'verse' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerseBookmark extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'translation', 'book', 'chapter', 'verse', 'tag'];

    protected function casts(): array
    {
        return ['book' => 'integer', 'chapter' => 'integer', 'verse' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

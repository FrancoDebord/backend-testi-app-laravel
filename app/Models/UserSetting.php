<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'is_private_account', 'comment_permission',
        'push_comments', 'push_likes', 'push_prayers', 'push_approval', 'push_new_followed',
        'app_theme', 'language',
        'last_bible_book', 'last_bible_chapter', 'last_bible_translation',
    ];

    protected function casts(): array
    {
        return [
            'is_private_account'    => 'boolean',
            'push_comments'         => 'boolean',
            'push_likes'            => 'boolean',
            'push_prayers'          => 'boolean',
            'push_approval'         => 'boolean',
            'push_new_followed'     => 'boolean',
            'last_bible_book'       => 'integer',
            'last_bible_chapter'    => 'integer',
            'updated_at'            => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

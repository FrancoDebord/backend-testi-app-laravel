<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyVerseCommentReport extends Model
{
    protected $fillable = ['daily_verse_comment_id', 'reporter_id', 'reason'];
}

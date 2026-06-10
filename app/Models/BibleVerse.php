<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BibleVerse extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'translation', 'book', 'chapter', 'verse', 'text',
    ];

    protected function casts(): array
    {
        return [
            'book'    => 'integer',
            'chapter' => 'integer',
            'verse'   => 'integer',
        ];
    }

    public function bookMeta(): ?BibleBook
    {
        return BibleBook::where('translation', $this->translation)
                        ->where('number', $this->book)
                        ->first();
    }

    public function reference(): string
    {
        return "{$this->chapter}:{$this->verse}";
    }
}

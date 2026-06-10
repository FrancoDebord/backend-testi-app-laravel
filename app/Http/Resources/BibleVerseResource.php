<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BibleVerseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'translation' => $this->translation,
            'book'        => $this->book,
            'chapter'     => $this->chapter,
            'verse'       => $this->verse,
            'text'        => $this->text,
        ];
    }
}

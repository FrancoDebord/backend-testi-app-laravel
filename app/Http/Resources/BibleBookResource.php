<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BibleBookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'number'        => $this->number,
            'name'          => $this->name,
            'abbreviation'  => $this->abbreviation,
            'testament'     => $this->testament,
            'chaptersCount' => $this->chapters_count,
            'translation'   => $this->translation,
        ];
    }
}

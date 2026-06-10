<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'slug'           => $this->slug,
            'icon'           => $this->icon,
            'color'          => $this->color,
            'testimonyCount' => $this->testimony_count,
            'displayOrder'   => $this->display_order,
            'isActive'       => $this->is_active,
        ];
    }
}

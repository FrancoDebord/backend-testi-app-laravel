<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Type de témoignage (catégorie). name : nom dans la langue de la requête (Accept-Language :
 * name_en en anglais s'il est renseigné, sinon le nom français) ; nameFr / nameEn : les deux noms
 * enregistrés (nameEn peut être null). Voir docs/fonctionnalites/categories-de-temoignages.md
 */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->resource->displayName(),
            'nameFr'         => $this->name,
            'nameEn'         => $this->name_en ?: null,
            'slug'           => $this->slug,
            'icon'           => $this->icon,
            'color'          => $this->color,
            'testimonyCount' => $this->testimony_count,
            'displayOrder'   => $this->display_order,
            'isActive'       => $this->is_active,
        ];
    }
}

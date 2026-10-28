<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Thème d'une semaine du verset du jour ; avec ses versets s'ils sont chargés (GET daily-verse/weeks/{year}/{week}).
 * Voir docs/fonctionnalites/verset-du-jour.md
 */
class ThemeSemaineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'year'      => $this->annee,
            'week'      => $this->numero_semaine,
            'theme'     => $this->thematique,
            'keyVerse'  => $this->verset_cle,
            'period'    => $this->periode,
            'startDate' => $this->date_debut?->toDateString(),
            'endDate'   => $this->date_fin?->toDateString(),
            'verses'    => $this->whenLoaded('versets', fn () => $this->versets->map(fn ($verse) => [
                'id'        => $verse->id,
                'date'      => $verse->scheduled_date?->toDateString(),
                'dayName'   => $verse->dayName(),
                'reference' => $verse->reference,
                'verseText' => $verse->verse_text,
                'version'   => $verse->version,
            ])->values()),
        ];
    }
}

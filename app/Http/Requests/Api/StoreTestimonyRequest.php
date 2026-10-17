<?php

namespace App\Http\Requests\Api;

use App\Enums\TestimonyType;
use App\Enums\TestimonyVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreTestimonyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // En modification (PUT), seuls les champs envoyés changent : renommer = { title } seul.
            'title'        => [Rule::requiredIf(fn () => $this->isMethod('post')), 'string', 'max:200'],
            'type'         => [Rule::requiredIf(fn () => $this->isMethod('post')), new Enum(TestimonyType::class)],
            // Obligatoire à la création d'un témoignage public ; facultative dans
            // le carnet privé (« autre » par défaut) et en modification.
            'category'     => [
                Rule::requiredIf(fn () => $this->isMethod('post')
                    && $this->input('visibility', 'public') !== 'private'
                    && $this->input('kind') !== \App\Enums\TestimonyKind::Conversion->value), // conversion : « conversion » par défaut
                'nullable', 'string',
            ],
            'body_text'    => ['nullable', 'string'],
            // Mentions « @ » choisies si le texte est envoyé lisible (docs/fonctionnalites/mentions.md).
            'mention_ids'  => ['nullable', 'array', 'max:20'],
            'mention_ids.*' => ['string'],
            'media_url'    => ['nullable', 'string'],
            // Lien YouTube : administrateurs seulement (docs/fonctionnalites/videos-youtube.md)
            'youtube_url'  => ['nullable', 'string', 'max:300'],
            // Accord pour montrer les preuves au public (docs/fonctionnalites/preuves.md)
            'proofs_public' => ['nullable', 'boolean'],
            'cover_url'    => ['nullable', 'string'],
            'duration'     => ['nullable', 'integer'],
            'bible_verse'  => ['nullable', 'string', 'max:500'],
            'verse_ref'    => ['nullable', 'string', 'max:100'],
            'tags'         => ['nullable', 'array'],
            'tags.*'       => ['string', 'max:30'],
            'visibility'   => ['nullable', new Enum(TestimonyVisibility::class)],
            // Témoignage officiel d'un événement (gestionnaires) : docs/fonctionnalites/evenements.md
            'event_id'     => ['nullable', 'uuid'],
            // Témoignage de l'accomplissement d'une parole prophétique (docs/fonctionnalites/paroles-prophetiques.md)
            'prophecy_id'     => ['nullable', 'uuid'],
            'prophecy_public' => ['nullable', 'boolean'],
            // Témoignage de conversion : kind, conversion_year, conversion_place, conversion_verse
            // (docs/fonctionnalites/temoignage-de-conversion.md)
            ...\App\Services\ConversionTestimonies::rules(),
        ];
    }

    public function messages(): array
    {
        return \App\Services\ConversionTestimonies::messages();
    }
}

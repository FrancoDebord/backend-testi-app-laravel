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
            'title'        => ['required', 'string', 'max:200'],
            'type'         => ['required', new Enum(TestimonyType::class)],
            // Obligatoire à la création d'un témoignage public ; facultative dans
            // le carnet privé (« autre » par défaut) et en modification.
            'category'     => [
                Rule::requiredIf(fn () => $this->isMethod('post')
                    && $this->input('visibility', 'public') !== 'private'),
                'nullable', 'string',
            ],
            'body_text'    => ['nullable', 'string'],
            'media_url'    => ['nullable', 'string'],
            'cover_url'    => ['nullable', 'string'],
            'duration'     => ['nullable', 'integer'],
            'bible_verse'  => ['nullable', 'string', 'max:500'],
            'verse_ref'    => ['nullable', 'string', 'max:100'],
            'tags'         => ['nullable', 'array'],
            'tags.*'       => ['string', 'max:30'],
            'visibility'   => ['nullable', new Enum(TestimonyVisibility::class)],
        ];
    }
}

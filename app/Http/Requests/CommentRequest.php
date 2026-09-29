<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Écriture ou modification d'un commentaire depuis le site web. */
class CommentRequest extends FormRequest
{
    public const MAX_LENGTH = 2000;

    /** Les droits (auteur du commentaire) sont vérifiés par CommentPolicy dans le contrôleur. */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['body' => trim((string) $this->input('body'))]);
    }

    public function rules(): array
    {
        return [
            'body'      => ['required', 'string', 'max:' . self::MAX_LENGTH],
            'parent_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Le commentaire est vide.',
            'body.max'      => 'Le commentaire ne doit pas dépasser 2 000 caractères.',
        ];
    }
}

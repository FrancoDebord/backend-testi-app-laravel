<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\VisibleLength;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'text'      => ['required', 'string', new VisibleLength(2000, __('comments.too_long'))],
            'parent_id' => ['nullable', 'string', 'exists:comments,id'],
            // Comptes choisis dans l'autocomplétion « @ » (docs/fonctionnalites/mentions.md)
            'mention_ids'   => ['nullable', 'array', 'max:20'],
            'mention_ids.*' => ['string', 'uuid'],
        ];
    }
}

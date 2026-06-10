<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'text'      => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'string', 'exists:comments,id'],
        ];
    }
}

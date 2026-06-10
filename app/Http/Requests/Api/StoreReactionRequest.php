<?php

namespace App\Http\Requests\Api;

use App\Enums\ReactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreReactionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(ReactionType::class)],
        ];
    }
}

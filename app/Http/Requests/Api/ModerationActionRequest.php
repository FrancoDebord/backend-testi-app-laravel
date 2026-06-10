<?php

namespace App\Http\Requests\Api;

use App\Enums\RejectionReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ModerationActionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'reason'         => ['nullable', new Enum(RejectionReason::class)],
            'moderator_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

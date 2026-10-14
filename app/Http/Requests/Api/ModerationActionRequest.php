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
            // Refus : motif obligatoire, code (reason) ou texte libre (rejection_reason), ou les deux
            // (docs/fonctionnalites/gestion-des-temoignages.md).
            'reason'           => ['nullable', 'required_without:rejection_reason', new Enum(RejectionReason::class)],
            'rejection_reason' => ['nullable', 'required_without:reason', 'string', 'max:1000'],
            'moderator_note'   => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required_without'           => 'Indiquez le motif du refus.',
            'rejection_reason.required_without' => 'Indiquez le motif du refus.',
        ];
    }
}

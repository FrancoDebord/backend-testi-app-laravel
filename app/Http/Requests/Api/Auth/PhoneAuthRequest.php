<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class PhoneAuthRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'firebase_token' => ['required', 'string'],
            'phone'          => ['required', 'string', 'max:20'],
            'first_name'     => ['nullable', 'string', 'max:50'],
            'last_name'      => ['nullable', 'string', 'max:50'],
            'country'        => ['nullable', 'string', 'max:100'],
        ];
    }
}

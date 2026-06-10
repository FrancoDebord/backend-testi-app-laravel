<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SocialAuthRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'in:google'],
            'id_token' => ['required', 'string'], // ID token fourni par le SDK Google Sign-In côté mobile
        ];
    }
}

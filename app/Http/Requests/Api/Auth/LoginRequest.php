<?php

namespace App\Http\Requests\Api\Auth;

use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Connexion par mot de passe : { email, password } ou { phone, phone_country, password }
 * (phone : numéro national tel que saisi, ou déjà au format « +229… »). Voir docs/fonctionnalites/telephone.md
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return self::baseRules();
    }

    public function messages(): array
    {
        return self::baseMessages();
    }

    /** Messages communs à l'API et au site. */
    public static function baseMessages(): array
    {
        return [
            'email.required_without' => __('account.login.email_required'),
            'phone.required_without' => __('account.login.email_required'),
            'phone_country.in'       => __('support.phone.country_unknown'),
        ];
    }

    /** Règles communes à l'API et au formulaire du site. */
    public static function baseRules(): array
    {
        return [
            'email'         => ['required_without:phone', 'nullable', 'email'],
            'phone'         => ['required_without:email', 'nullable', 'string', 'max:30'],
            'phone_country' => ['nullable', 'string', Rule::in(array_merge(Countries::codes(), array_map('strtoupper', Countries::codes())))],
            'password'      => ['required', 'string'],
        ];
    }
}

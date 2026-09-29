<?php

namespace App\Http\Requests\Api\Auth;

use App\Services\OrganizationAccounts;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /**
     * first_name / last_name / name envoyés par l'application sont ignorés :
     * seul display_name est enregistré (last_name est vide pour une organisation).
     */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:100'],
            'email'        => ['required', 'email', 'unique:users,email'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
            'country'      => ['nullable', 'string', 'max:100'],
        ]
        // Téléphone de contact (indicatif + numéro) : facultatif ici, pour ne pas bloquer les versions
        // de l'application qui ne l'envoient pas encore. docs/fonctionnalites/telephone.md
        + PhoneNumber::rules(false)
        + OrganizationAccounts::registerRules();
    }

    public function messages(): array
    {
        return PhoneNumber::MESSAGES;
    }

    public function attributes(): array
    {
        return [
            'organization_name'    => 'nom de l’organisation',
            'organization_type'    => 'type d’organisation',
            'organization_city'    => 'ville',
            'organization_website' => 'site internet',
        ];
    }
}

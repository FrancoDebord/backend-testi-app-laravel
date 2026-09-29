<?php

namespace App\Http\Controllers\Web;

use App\Enums\AccountType;
use App\Enums\OrganizationType;
use App\Enums\UserAccountStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\OrganizationAccounts;
use App\Support\Countries;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) return redirect()->route('home');
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            if ($user->status->value === 'banned') {
                Auth::logout();
                return back()->withErrors(['email' => 'Votre compte a été banni.']);
            }

            return redirect()->intended(route('home'));
        }

        return back()->withErrors(['email' => 'Identifiants incorrects.'])->onlyInput('email');
    }

    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) return redirect()->route('home');
        return view('auth.register', ['organizationTypes' => OrganizationType::cases()]);
    }

    /**
     * Inscription d'une personne ou d'une organisation, avec les mêmes règles
     * que l'API (docs/fonctionnalites/comptes-organisation.md). Pour une
     * organisation, le nom affiché est celui de l'organisation et le compte
     * est créé en attente de vérification.
     */
    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'display_name'          => 'required_unless:account_type,organization|nullable|string|max:100',
            'email'                 => 'required|email|unique:users,email',
            'password'              => 'required|string|min:8|confirmed',
            'country'               => ['nullable', 'string', 'max:100', Rule::in(Countries::all())],
        ] + PhoneNumber::rules('required_if:account_type,organization') + OrganizationAccounts::registerRules(), PhoneNumber::MESSAGES + [
            'country.in'                    => 'Choisissez un pays dans la liste.',
            'display_name.required_unless'  => 'Le champ nom complet est obligatoire.',
            'organization_name.required_if' => 'Le champ nom de l’organisation est obligatoire.',
        ], [
            'display_name'         => 'nom complet',
            'organization_name'    => 'nom de l’organisation',
            'organization_type'    => 'type d’organisation',
            'organization_city'    => 'ville',
            'organization_website' => 'site internet',
        ]);

        $organization   = OrganizationAccounts::registrationAttributes($data);
        $isOrganization = $organization['account_type'] === AccountType::Organization->value;

        // Téléphone de contact, non vérifié : il ne permet jamais de se connecter (docs/fonctionnalites/telephone.md).
        $phone = PhoneNumber::toE164($data['phone_country'] ?? null, $data['phone'] ?? null);

        $user = User::create([
            'display_name' => $isOrganization ? $organization['organization_name'] : $data['display_name'],
            'email'        => $data['email'],
            'password'     => $data['password'],
            'country'      => $data['country'] ?? null,
            'phone'        => $phone,
            'phone_country' => $phone ? $data['phone_country'] : null,
            'role'         => UserRole::Utilisateur->value,
            // Explicite, comme l'API : sans cela, le statut lu restait null.
            'status'       => UserAccountStatus::Active->value,
        ] + $organization); // organisation : en attente de vérification

        UserSetting::create(['user_id' => $user->id]);

        Auth::login($user);
        $request->session()->regenerate();

        if ($isOrganization) {
            return redirect()->route('profiles.show', $user->id)
                ->with('success', 'Compte créé. Votre organisation sera vérifiée par notre équipe.');
        }

        return redirect()->route('home');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);
        // In production: dispatch password reset email
        return back()->with('status', 'Si l\'adresse email existe, un lien sera envoyé.');
    }
}

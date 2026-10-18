@extends('layouts.guest')
@section('title', __('account.register.page_title'))

@section('content')
<div class="card p-6 sm:p-8">
    <h1 class="text-h3">{{ __('account.register.title') }}</h1>
    <p class="text-secondary mt-2">{{ __('account.register.intro') }}</p>

    @if($errors->any())
    <div class="alert-error mt-5" role="alert">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
        <p>{{ __('account.register.failed') }}</p>
    </div>
    @endif

    {{--
        Type de compte : sans JavaScript, les deux blocs restent visibles et le serveur
        ne tient compte que de celui du type choisi (required_unless / required_if).
        Avec JavaScript (resources/js/app.js, data-account-type-form), seul le bloc
        du type choisi est affiché et ses champs obligatoires reçoivent « required ».
    --}}
    @php($accountType = old('account_type') === 'organization' ? 'organization' : 'individual')
    <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4" data-loading-label="{{ __('account.register.loading') }}" data-account-type-form>
        @csrf
        <fieldset>
            <legend class="form-label">{{ __('account.register.account_type') }}</legend>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50 has-[:checked]:text-slate-900">
                    <input type="radio" name="account_type" value="individual" @checked($accountType === 'individual')>
                    <i class="fa-solid fa-user text-slate-400" aria-hidden="true"></i>{{ __('account.register.individual') }}
                </label>
                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50 has-[:checked]:text-slate-900">
                    <input type="radio" name="account_type" value="organization" @checked($accountType === 'organization')>
                    <i class="fa-solid fa-building text-slate-400" aria-hidden="true"></i>{{ __('account.register.organization') }}
                </label>
            </div>
            @error('account_type')<p class="form-error">{{ $message }}</p>@enderror
        </fieldset>

        <div data-account-section="individual">
            <label for="display_name" class="form-label">{{ __('account.register.display_name') }}</label>
            <input id="display_name" type="text" name="display_name" value="{{ old('display_name') }}" autocomplete="name"
                   class="form-input" placeholder="{{ __('account.register.display_name_placeholder') }}" maxlength="100" data-section-required>
            <p class="form-hint" data-account-nojs-hint>{{ __('account.register.individual_only') }}</p>
            @error('display_name')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div class="space-y-4" data-account-section="organization">
            <p class="form-hint" data-account-nojs-hint>{{ __('account.register.organization_only') }}</p>
            <div>
                <label for="organization_name" class="form-label">{{ __('account.register.organization_name') }}</label>
                <input id="organization_name" type="text" name="organization_name" value="{{ old('organization_name') }}" autocomplete="organization"
                       class="form-input" placeholder="{{ __('account.register.organization_name_placeholder') }}" maxlength="150" data-section-required>
                <p class="form-hint">{{ __('account.register.organization_name_hint') }}</p>
                @error('organization_name')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="min-w-0">
                    <label for="organization_type" class="form-label">{{ __('account.register.organization_type') }}</label>
                    <select id="organization_type" name="organization_type" class="form-input">
                        <option value="">{{ __('account.register.choose_type') }}</option>
                        @foreach($organizationTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('organization_type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('organization_type')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="min-w-0">
                    <label for="organization_city" class="form-label">{{ __('account.register.city') }}</label>
                    <input id="organization_city" type="text" name="organization_city" value="{{ old('organization_city') }}" autocomplete="address-level2"
                           class="form-input" placeholder="Cotonou" maxlength="100">
                    @error('organization_city')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label for="organization_website" class="form-label">{{ __('account.register.website') }}</label>
                <input id="organization_website" type="url" name="organization_website" value="{{ old('organization_website') }}" autocomplete="url"
                       class="form-input" placeholder="https://" maxlength="255">
                <p class="form-hint">{{ __('account.register.website_hint') }}</p>
                @error('organization_website')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="alert-info">
                <i class="fa-solid fa-circle-info mt-0.5 text-slate-400" aria-hidden="true"></i>
                <p>{{ __('account.register.verification_info') }}</p>
            </div>
        </div>
        <div>
            <label for="email" class="form-label">{{ __('account.register.email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                   class="form-input" placeholder="{{ __('account.fields.email_placeholder') }}">
            @error('email')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="country" class="form-label">{{ __('account.register.country') }}</label>
            @include('components.country-select', ['name' => 'country', 'id' => 'country', 'value' => old('country')])
            @error('country')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        @include('components.phone-input', [
            'phoneCountry' => old('phone_country'),
            'countryName'  => old('country'),
            'number'       => old('phone'),
            'required'     => 'organization',
            'follow'       => 'country',
        ])
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="password" class="form-label">{{ __('account.register.password') }}</label>
                <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" class="form-input">
                <p class="form-hint">{{ __('account.register.password_hint') }}</p>
                @error('password')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="form-label">{{ __('account.register.password_confirmation') }}</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="form-input">
            </div>
        </div>
        <button type="submit" class="btn-cta w-full">{{ __('account.register.submit') }}</button>
    </form>

    {{-- Créer son compte avec Google : même parcours que sur la page de connexion (docs/fonctionnalites/connexion-google.md). --}}
    @include('auth.partials.google-signin', ['googleText' => 'signup_with'])

    <p class="mt-8 border-t border-slate-200 pt-5 text-center text-sm text-slate-500">
        {{ __('account.register.already') }}
        <a href="{{ route('login') }}" class="font-semibold text-primary-600 hover:text-primary-700 hover:underline">{{ __('account.login.submit') }}</a>
    </p>
</div>

{{-- Pourquoi témoigner ? (docs/fonctionnalites/pourquoi-temoigner.md) --}}
<x-why-testify collapsible :action="false" class="mt-6" />
@endsection

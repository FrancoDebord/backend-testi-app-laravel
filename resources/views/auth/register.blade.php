@extends('layouts.guest')
@section('title', 'Inscription')

@section('content')
<div class="card p-6 sm:p-8">
    <h1 class="text-xl font-semibold text-primary-600">Créer un compte</h1>
    <p class="mt-1 text-sm text-slate-500">L'inscription est gratuite. Les champs marqués * sont obligatoires.</p>

    @if($errors->any())
    <div class="alert-error mt-5" role="alert">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
        <p>Le compte n'a pas pu être créé. Merci de corriger les champs signalés.</p>
    </div>
    @endif

    {{--
        Type de compte : sans JavaScript, les deux blocs restent visibles et le serveur
        ne tient compte que de celui du type choisi (required_unless / required_if).
        Avec JavaScript (resources/js/app.js, data-account-type-form), seul le bloc
        du type choisi est affiché et ses champs obligatoires reçoivent « required ».
    --}}
    @php($accountType = old('account_type') === 'organization' ? 'organization' : 'individual')
    <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4" data-loading-label="Création du compte…" data-account-type-form>
        @csrf
        <fieldset>
            <legend class="form-label">Type de compte *</legend>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50 has-[:checked]:text-slate-900">
                    <input type="radio" name="account_type" value="individual" @checked($accountType === 'individual')>
                    <i class="fa-solid fa-user text-slate-400" aria-hidden="true"></i>Je suis une personne
                </label>
                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50 has-[:checked]:text-slate-900">
                    <input type="radio" name="account_type" value="organization" @checked($accountType === 'organization')>
                    <i class="fa-solid fa-building text-slate-400" aria-hidden="true"></i>Je représente une organisation
                </label>
            </div>
            @error('account_type')<p class="form-error">{{ $message }}</p>@enderror
        </fieldset>

        <div data-account-section="individual">
            <label for="display_name" class="form-label">Nom complet *</label>
            <input id="display_name" type="text" name="display_name" value="{{ old('display_name') }}" autocomplete="name"
                   class="form-input" placeholder="Jean Dupont" maxlength="100" data-section-required>
            <p class="form-hint" data-account-nojs-hint>Pour une personne uniquement.</p>
            @error('display_name')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div class="space-y-4" data-account-section="organization">
            <p class="form-hint" data-account-nojs-hint>Pour une organisation uniquement : ces champs sont ignorés pour une personne.</p>
            <div>
                <label for="organization_name" class="form-label">Nom de l'organisation *</label>
                <input id="organization_name" type="text" name="organization_name" value="{{ old('organization_name') }}" autocomplete="organization"
                       class="form-input" placeholder="Église de la Grâce" maxlength="150" data-section-required>
                <p class="form-hint">Ce nom sera affiché sur votre profil et vos témoignages.</p>
                @error('organization_name')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="min-w-0">
                    <label for="organization_type" class="form-label">Type d'organisation</label>
                    <select id="organization_type" name="organization_type" class="form-input">
                        <option value="">Choisir un type</option>
                        @foreach($organizationTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('organization_type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('organization_type')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="min-w-0">
                    <label for="organization_city" class="form-label">Ville</label>
                    <input id="organization_city" type="text" name="organization_city" value="{{ old('organization_city') }}" autocomplete="address-level2"
                           class="form-input" placeholder="Cotonou" maxlength="100">
                    @error('organization_city')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label for="organization_website" class="form-label">Site internet</label>
                <input id="organization_website" type="url" name="organization_website" value="{{ old('organization_website') }}" autocomplete="url"
                       class="form-input" placeholder="https://" maxlength="255">
                <p class="form-hint">Facultatif. Adresse complète commençant par https://</p>
                @error('organization_website')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="alert-info">
                <i class="fa-solid fa-circle-info mt-0.5 text-slate-400" aria-hidden="true"></i>
                <p>Votre organisation sera vérifiée par notre équipe. La coche « vérifiée » apparaîtra ensuite sur votre profil et vos témoignages.</p>
            </div>
        </div>
        <div>
            <label for="email" class="form-label">Adresse e-mail *</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                   class="form-input" placeholder="vous@exemple.com">
            @error('email')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="country" class="form-label">Pays</label>
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
                <label for="password" class="form-label">Mot de passe *</label>
                <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" class="form-input">
                <p class="form-hint">8 caractères minimum.</p>
                @error('password')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="form-label">Confirmation *</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="form-input">
            </div>
        </div>
        <button type="submit" class="btn-cta w-full">Créer mon compte</button>
    </form>

    <p class="mt-6 border-t border-slate-100 pt-4 text-center text-sm text-slate-500">
        Déjà inscrit ?
        <a href="{{ route('login') }}" class="font-medium text-slate-900 hover:underline">Se connecter</a>
    </p>
</div>
@endsection

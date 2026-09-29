@extends('layouts.app')
@section('title', 'Modifier mon profil')
@php
    $header      = 'Modifier mon profil';
    $subheader   = 'Ces informations sont visibles sur votre profil public.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Mon profil', 'url' => route('profiles.show', Auth::id())],
        ['label' => 'Modifier'],
    ];
@endphp

@section('content')
<div class="mx-auto max-w-2xl">
    @if($errors->any())
    <div class="alert-error mb-4" role="alert">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
        <p>Le profil n'a pas pu être enregistré. Merci de corriger les champs signalés.</p>
    </div>
    @endif

    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="card space-y-5 p-5 sm:p-6"
          data-loading-label="Enregistrement…">
        @csrf @method('PUT')

        {{-- Photo de couverture : docs/fonctionnalites/photo-de-couverture.md (aperçu : data-cover-* dans app.js) --}}
        <div class="space-y-2">
            <label for="cover" class="form-label">Photo de couverture</label>
            <div class="relative h-24 overflow-hidden rounded-xl bg-gradient-to-r from-slate-200 via-slate-100 to-slate-200 sm:h-36">
                <img src="{{ $user->cover_url }}" alt="" class="h-full w-full object-cover" data-cover-preview @if(!$user->cover_url) hidden @endif>
            </div>
            <input id="cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="form-input" data-cover-input aria-describedby="cover-hint">
            <p id="cover-hint" class="form-hint">JPG, PNG ou WebP, 8 Mo au plus. Format large conseillé (1500 × 500 pixels).</p>
            @error('cover')<p class="form-error">{{ $message }}</p>@enderror
            @if($user->cover_url)
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remove_cover" value="1" class="rounded" data-cover-remove>
                Retirer la photo de couverture
            </label>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-4">
            @include('components.avatar', ['user' => $user, 'size' => 'lg'])
            <div class="min-w-0 flex-1 basis-56">
                <label for="avatar" class="form-label">{{ $user->isOrganization() ? 'Logo de l\'organisation' : 'Photo de profil' }}</label>
                <input id="avatar" type="file" name="avatar" accept="image/*" class="form-input">
                @error('avatar')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </div>

        @if($user->isOrganization())
        {{-- Organisation : le nom affiché est le nom de l'organisation (pas de « Nom complet »),
             comme dans l'application mobile. Voir docs/fonctionnalites/comptes-organisation.md --}}
        @php($verification = $user->verification_status)
        <section class="space-y-4 border-t border-slate-100 pt-5" aria-labelledby="organization-title">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="organization-title" class="card-title">Organisation</h2>
                @if($verification)
                <span class="{{ $verification->badgeClass() }}">{{ $verification->label() }}</span>
                @endif
            </div>

            @if($verification === \App\Enums\VerificationStatus::Rejected)
            <div class="alert-warning" role="status">
                <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
                <p class="min-w-0 break-words"><span class="font-semibold">Vérification refusée</span>@if($user->verification_note) : {{ rtrim($user->verification_note, " .") }}@endif. Corrigez les informations ci-dessous : la demande sera réexaminée par notre équipe.</p>
            </div>
            @endif

            <div>
                <label for="organization_name" class="form-label">Nom de l'organisation *</label>
                <input id="organization_name" type="text" name="organization_name" required maxlength="150"
                       value="{{ old('organization_name', $user->organization_name ?? $user->display_name) }}"
                       class="form-input" autocomplete="organization" placeholder="Église de la Grâce">
                <p class="form-hint">Nom affiché sur votre profil et vos témoignages.</p>
                @if($verification === \App\Enums\VerificationStatus::Verified)
                <p class="form-hint">Modifier le nom remettra votre organisation en attente de vérification.</p>
                @endif
                @error('organization_name')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="min-w-0">
                    <label for="organization_type" class="form-label">Type d'organisation</label>
                    <select id="organization_type" name="organization_type" class="form-input">
                        <option value="">Choisir…</option>
                        @foreach($organizationTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('organization_type', $user->organization_type?->value) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('organization_type')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="min-w-0">
                    <label for="organization_city" class="form-label">Ville</label>
                    <input id="organization_city" type="text" name="organization_city" maxlength="100"
                           value="{{ old('organization_city', $user->organization_city) }}"
                           class="form-input" autocomplete="address-level2" placeholder="Cotonou">
                    @error('organization_city')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="organization_website" class="form-label">Site internet</label>
                <input id="organization_website" type="url" name="organization_website" maxlength="255"
                       value="{{ old('organization_website', $user->organization_website) }}"
                       class="form-input" autocomplete="url" placeholder="https://www.exemple.org">
                <p class="form-hint">Adresse complète commençant par https:// ou http://.</p>
                @error('organization_website')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </section>

        <div class="border-t border-slate-100"></div>
        @else
        <div>
            <label for="display_name" class="form-label">Nom complet *</label>
            <input id="display_name" type="text" name="display_name" value="{{ old('display_name', $user->display_name) }}" required
                   class="form-input" autocomplete="name">
            @error('display_name')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        @endif

        <div>
            <label for="country" class="form-label">Pays</label>
            @include('components.country-select', ['name' => 'country', 'id' => 'country', 'value' => old('country', $user->country)])
            @error('country')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        @if($user->hasVerifiedPhone())
        {{-- Numéro confirmé par SMS : il sert à la connexion par téléphone, il se change depuis l'application. --}}
        <div>
            <p class="form-label">Téléphone</p>
            <p class="flex flex-wrap items-center gap-2 text-sm text-slate-900">
                <span class="tabular-nums">{{ \App\Support\PhoneNumber::display($user->phone, $user->phone_country) }}</span>
                <span class="badge-validated">Vérifié par SMS</span>
            </p>
            <p class="form-hint">Ce numéro sert à vous connecter depuis l'application : il se modifie depuis l'application.</p>
        </div>
        @else
        @include('components.phone-input', [
            'phoneCountry' => old('phone_country', $user->phone_country),
            'countryName'  => old('country', $user->country),
            'number'       => old('phone', \App\Support\PhoneNumber::national($user->phone, $user->phone_country)),
            'required'     => $user->isOrganization(),
            'follow'       => 'country',
        ])
        @endif

        <div>
            <label for="bio" class="form-label">Présentation</label>
            <textarea id="bio" name="bio" rows="4" maxlength="500" class="form-input resize-none"
                      placeholder="{{ $user->isOrganization() ? 'Présentez votre organisation en quelques mots…' : 'Parlez de vous en quelques mots…' }}">{{ old('bio', $user->bio) }}</textarea>
            <p class="form-hint">500 caractères maximum.</p>
            @error('bio')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-5">
            <a href="{{ route('profiles.show', Auth::id()) }}" class="btn-secondary">Annuler</a>
            <button type="submit" class="btn-primary">Enregistrer</button>
        </div>
    </form>
</div>
@endsection

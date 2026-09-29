@extends('layouts.app')
@section('title', 'Paramètres du compte')
@php
    $header      = 'Paramètres du compte';
    $subheader   = 'Confidentialité, notifications et affichage.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Paramètres'],
    ];
    $pushOptions = [
        ['push_comments', 'Nouveaux commentaires'],
        ['push_likes', 'Réactions (j’aime, prières…)'],
        ['push_prayers', 'Prières'],
        ['push_approval', 'Validation de mes témoignages'],
        ['push_new_followed', 'Nouveaux témoignages de mes abonnements'],
    ];
@endphp

@section('content')
<form method="POST" action="{{ route('profile.settings.update') }}" class="mx-auto max-w-2xl space-y-6" data-loading-label="Enregistrement…">
    @csrf @method('PUT')

    <section class="card">
        <h2 class="card-title border-b border-slate-100 px-5 py-4">Confidentialité</h2>
        <div class="divide-y divide-slate-100">
            <div class="flex items-center justify-between gap-4 px-5 py-4">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-900">Compte privé</p>
                    <p class="text-sm text-slate-500">Seuls vos abonnés voient vos témoignages.</p>
                </div>
                @include('components.switch', ['name' => 'is_private_account', 'checked' => (bool) $settings->is_private_account, 'label' => 'Compte privé'])
            </div>
            <div class="px-5 py-4">
                <p class="mb-2 text-sm font-medium text-slate-900">Qui peut commenter</p>
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    @foreach(['everyone' => 'Tout le monde', 'followers' => 'Mes abonnés', 'nobody' => 'Personne'] as $val => $label)
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="radio" name="comment_permission" value="{{ $val }}" @checked(($settings->comment_permission ?? 'everyone') === $val)>
                        {{ $label }}
                    </label>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="card">
        <h2 class="card-title border-b border-slate-100 px-5 py-4">Notifications sur mobile</h2>
        <div class="divide-y divide-slate-100">
            @foreach($pushOptions as [$key, $label])
            <div class="flex items-center justify-between gap-4 px-5 py-3">
                <span class="text-sm text-slate-700">{{ $label }}</span>
                @include('components.switch', ['name' => $key, 'checked' => (bool) ($settings->$key ?? true), 'label' => $label])
            </div>
            @endforeach
        </div>
    </section>

    <section class="card">
        <h2 class="card-title border-b border-slate-100 px-5 py-4">Apparence de l’application mobile</h2>
        <div class="px-5 py-4">
            <p class="mb-2 text-sm font-medium text-slate-900">Thème</p>
            <div class="flex flex-wrap gap-x-6 gap-y-2">
                @foreach(['light' => 'Clair', 'dark' => 'Sombre', 'system' => 'Selon le système'] as $val => $label)
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="radio" name="app_theme" value="{{ $val }}" @checked(($settings->app_theme ?? 'system') === $val)>
                    {{ $label }}
                </label>
                @endforeach
            </div>
        </div>
    </section>

    <div class="flex justify-end">
        <button type="submit" class="btn-primary">Enregistrer les paramètres</button>
    </div>
</form>
@endsection

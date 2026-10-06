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

    {{-- Pas de choix de thème (Clair / Sombre / Système) : l'application n'a pas de palette sombre.
         La colonne user_settings.app_theme reste, inutilisée. --}}

    <div class="flex justify-end">
        <button type="submit" class="btn-primary">Enregistrer les paramètres</button>
    </div>
</form>

{{-- ── Gestion déléguée des événements (docs/fonctionnalites/evenements.md) ─ --}}
{{-- Hors du formulaire des paramètres : chaque action a son propre formulaire. --}}
@if(Auth::user()->isOrganization())
@php $managersFull = $orgManagers->count() >= \App\Models\Event::MAX_ORGANIZATION_MANAGERS; @endphp
<section class="card mx-auto mt-6 max-w-2xl scroll-mt-24" id="gestionnaires" aria-labelledby="org-managers-title">
    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-100 px-5 py-4">
        <h2 id="org-managers-title" class="card-title">Gestionnaires de l'organisation</h2>
        <span class="text-xs text-slate-500">{{ \App\Models\Event::MAX_ORGANIZATION_MANAGERS }} au plus</span>
    </div>
    <div class="space-y-4 px-5 py-4">
        <p class="text-sm text-slate-600">Ils peuvent créer et gérer vos événements en votre nom, avec leur propre compte personnel. Ils ne peuvent pas modifier votre profil ni vos paramètres.</p>
        @unless(Auth::user()->isVerified())
        <div class="alert-warning">
            <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
            <p>Votre organisation n'est pas encore vérifiée : vos gestionnaires pourront créer des événements une fois la vérification faite.</p>
        </div>
        @endunless

        @if($orgManagers->isEmpty())
        <p class="text-sm text-slate-500">Aucun gestionnaire pour le moment.</p>
        @else
        <ul class="divide-y divide-slate-100">
            @foreach($orgManagers as $orgManager)
            <li class="flex items-center gap-3 py-2.5">
                @include('components.avatar', ['user' => $orgManager, 'size' => 'sm'])
                <span class="min-w-0 flex-1">
                    <a href="{{ route('profiles.show', $orgManager->id) }}" class="block truncate text-sm font-semibold text-slate-900 hover:underline">{{ $orgManager->display_name }}</a>
                    @if($orgManager->pivot?->created_at)
                    <span class="block text-xs text-slate-500">Depuis le {{ $orgManager->pivot->created_at->translatedFormat('j F Y') }}</span>
                    @endif
                </span>
                <form id="org-manager-remove-{{ $orgManager->id }}" method="POST" action="{{ route('profile.managers.destroy', $orgManager->id) }}" data-loading-label="Retrait…">
                    @csrf @method('DELETE')
                    <button type="button" class="btn-ghost btn-sm shrink-0"
                            onclick="openConfirmModal('org-manager-remove-{{ $orgManager->id }}', @js($orgManager->display_name . ' ne pourra plus créer ni gérer vos événements.'), 'Retirer le gestionnaire', 'Retirer', 'fa-user-minus')">
                        Retirer
                    </button>
                </form>
            </li>
            @endforeach
        </ul>
        @endif

        <div class="border-t border-slate-100 pt-4">
            @if($managersFull)
            <p class="text-sm text-slate-500">Deux gestionnaires au plus : retirez-en un pour en ajouter un autre.</p>
            @else
            @include('components.user-picker', [
                'id'      => 'org-managers',
                'label'   => 'Ajouter un gestionnaire',
                'addUrl'  => route('profile.managers.store'),
                'exclude' => [Auth::id(), ...$orgManagers->pluck('id')],
                'results' => $pickerResults,
                'anchor'  => 'gestionnaires',
            ])
            @endif
        </div>
    </div>
</section>
@elseif($managedOrgs->isNotEmpty())
<section class="card mx-auto mt-6 max-w-2xl scroll-mt-24" id="organisations-gerees" aria-labelledby="managed-orgs-title">
    <h2 id="managed-orgs-title" class="card-title border-b border-slate-100 px-5 py-4">Organisations que je gère</h2>
    <div class="px-5 py-4">
        <p class="text-sm text-slate-600">Vous pouvez créer et gérer les événements de ces organisations en leur nom.</p>
        <ul class="mt-3 divide-y divide-slate-100">
            @foreach($managedOrgs as $org)
            @php $orgName = $org->organization_name ?: $org->display_name; @endphp
            <li class="flex flex-wrap items-center gap-3 py-2.5">
                @include('components.avatar', ['user' => $org, 'size' => 'sm'])
                <span class="flex min-w-0 flex-1 items-center gap-1.5">
                    <a href="{{ route('profiles.show', $org->id) }}" class="truncate text-sm font-semibold text-slate-900 hover:underline">{{ $orgName }}</a>
                    @include('components.verified-badge', ['user' => $org])
                </span>
                <form id="managed-org-leave-{{ $org->id }}" method="POST" action="{{ route('profile.managed.leave', $org->id) }}" data-loading-label="Retrait…">
                    @csrf @method('DELETE')
                    <button type="button" class="btn-ghost btn-sm shrink-0"
                            onclick="openConfirmModal('managed-org-leave-{{ $org->id }}', @js('Vous ne pourrez plus créer ni gérer les événements de ' . $orgName . '.'), 'Ne plus gérer', 'Ne plus gérer', 'fa-user-minus')">
                        Ne plus gérer
                    </button>
                </form>
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif
@endsection

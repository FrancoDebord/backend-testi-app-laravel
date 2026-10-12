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

{{-- Localisation (classement de « Mon fil » par zone) : docs/fonctionnalites/localisation.md --}}
@include('profile.partials.location')

{{-- ── Administrateurs du compte (docs/fonctionnalites/administrateurs-de-compte.md) ─ --}}
{{-- Hors du formulaire des paramètres : chaque action a son propre formulaire. --}}
@php
    $maxManagers  = \App\Services\AccountDelegation::MAX_MANAGERS;
    $managersFull = $orgManagers->count() >= $maxManagers;
    $settingsUser = Auth::user();
@endphp
<section class="card mx-auto mt-6 max-w-2xl scroll-mt-24" id="administrateurs" aria-labelledby="account-managers-title">
    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-100 px-5 py-4">
        <h2 id="account-managers-title" class="card-title">Administrateurs du compte</h2>
        @unless($isStaff)
        <span class="text-xs text-slate-500">{{ $maxManagers }} personnes au plus</span>
        @endunless
    </div>
    <div class="space-y-4 px-5 py-4">
        @if($isStaff)
        <div class="alert-info">
            <i class="fa-solid fa-shield-halved mt-0.5" aria-hidden="true"></i>
            <p>Les comptes de l'équipe ne peuvent pas être confiés : les droits de modération et d'administration restent personnels.</p>
        </div>
        @else
        <p class="text-sm text-slate-600">Vous administrez votre compte. Vous pouvez nommer {{ $maxManagers }} personnes au plus qui publient et agissent en votre nom avec leur propre compte personnel : témoignages, événements, requêtes et sessions de prière, commentaires, réactions.</p>
        <ul class="space-y-1 text-sm text-slate-600">
            <li class="flex gap-2"><i class="fa-solid fa-lock mt-1 w-4 shrink-0 text-center text-primary-600" aria-hidden="true"></i><span>Elles ne peuvent ni changer vos identifiants de connexion, ni supprimer le compte.</span></li>
            <li class="flex gap-2"><i class="fa-solid fa-user-lock mt-1 w-4 shrink-0 text-center text-primary-600" aria-hidden="true"></i><span>Elles ne peuvent pas nommer d'autres administrateurs.</span></li>
            <li class="flex gap-2"><i class="fa-solid fa-book-open mt-1 w-4 shrink-0 text-center text-primary-600" aria-hidden="true"></i><span>Elles ne voient pas votre carnet privé.</span></li>
        </ul>
        @if($settingsUser->isOrganization() && !$settingsUser->isVerifiedOrganization())
        <div class="alert-warning">
            <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
            <p>Votre organisation n'est pas encore vérifiée : vos administrateurs pourront créer des événements une fois la vérification faite.</p>
        </div>
        @endif

        @if($orgManagers->isEmpty())
        <p class="text-sm text-slate-500">Aucun administrateur pour le moment.</p>
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
                <form id="account-manager-remove-{{ $orgManager->id }}" method="POST" action="{{ route('profile.managers.destroy', $orgManager->id) }}" data-loading-label="Retrait…">
                    @csrf @method('DELETE')
                    <button type="button" class="btn-ghost btn-sm shrink-0"
                            onclick="openConfirmModal('account-manager-remove-{{ $orgManager->id }}', @js($orgManager->display_name . ' ne pourra plus publier ni agir au nom de votre compte.'), @js("Retirer l'administrateur"), 'Retirer', 'fa-user-minus')">
                        Retirer
                    </button>
                </form>
            </li>
            @endforeach
        </ul>
        @endif

        <div class="border-t border-slate-100 pt-4">
            @if($managersFull)
            <p class="text-sm text-slate-500">{{ $maxManagers }} administrateurs au plus : retirez-en un pour en nommer un autre.</p>
            @else
            @include('components.user-picker', [
                'id'      => 'account-managers',
                'label'   => 'Ajouter un administrateur',
                'addUrl'  => route('profile.managers.store'),
                'exclude' => [Auth::id(), ...$orgManagers->pluck('id')],
                'results' => $pickerResults,
                'anchor'  => 'administrateurs',
            ])
            @endif
        </div>

        {{-- Journal : 20 dernières actions faites au nom du compte --}}
        <div class="scroll-mt-24 border-t border-slate-100 pt-4" id="journal-des-actions">
            <h3 class="text-sm font-semibold text-primary-700">Journal des actions</h3>
            @if($delegateLog->isEmpty())
            <p class="mt-1 text-sm text-slate-500">Aucune action faite en votre nom pour le moment.</p>
            @else
            <p class="mt-1 text-xs text-slate-500">Dernières actions faites en votre nom par vos administrateurs.</p>
            <ul class="mt-2 divide-y divide-slate-100">
                @foreach($delegateLog as $logEntry)
                <li class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5 py-2 text-sm">
                    <span class="min-w-0">
                        <span class="font-semibold text-slate-900">{{ $logEntry->actor_name }}</span>
                        <span class="text-slate-600">· {{ \App\Http\Controllers\Api\OrganizationManagerController::label($logEntry) }}</span>
                    </span>
                    @if($logEntry->created_at)
                    <time datetime="{{ $logEntry->created_at->toIso8601String() }}" title="{{ $logEntry->created_at->translatedFormat('j F Y à H:i') }}" class="shrink-0 text-xs text-slate-500">{{ $logEntry->created_at->diffForHumans() }}</time>
                    @endif
                </li>
                @endforeach
            </ul>
            @endif
        </div>
        @endif
    </div>
</section>

@if($managedOrgs->isNotEmpty())
<section class="card mx-auto mt-6 max-w-2xl scroll-mt-24" id="comptes-administres" aria-labelledby="managed-accounts-title">
    <h2 id="managed-accounts-title" class="card-title border-b border-slate-100 px-5 py-4">Comptes que j'administre</h2>
    <div class="px-5 py-4">
        <p class="text-sm text-slate-600">Ces comptes vous ont été confiés : vous pouvez publier et agir en leur nom. Choisissez le compte avant d'agir ; vous pouvez aussi en changer depuis le menu de votre profil, en haut de la page.</p>
        <ul class="mt-3 divide-y divide-slate-100">
            @foreach($managedOrgs as $org)
            @php
                $orgName = $org->organization_name ?: $org->display_name;
                $canAct  = \App\Services\AccountDelegation::canActFor($settingsUser, $org);
            @endphp
            <li class="flex flex-wrap items-center gap-3 py-2.5">
                @include('components.avatar', ['user' => $org, 'size' => 'sm'])
                <span class="flex min-w-0 flex-1 items-center gap-1.5">
                    <a href="{{ route('profiles.show', $org->id) }}" class="truncate text-sm font-semibold text-slate-900 hover:underline">{{ $orgName }}</a>
                    @include('components.verified-badge', ['user' => $org])
                </span>
                <span class="flex flex-wrap items-center gap-2">
                    @if($canAct)
                    <form method="POST" action="{{ route('accounts.switch', $org->id) }}" data-loading-label="Changement de compte…">
                        @csrf
                        <button type="submit" class="btn-secondary btn-sm shrink-0"><i class="fa-solid fa-right-left" aria-hidden="true"></i>Agir au nom de ce compte</button>
                    </form>
                    @else
                    <span class="text-xs text-slate-500">Compte indisponible</span>
                    @endif
                    <form id="managed-account-leave-{{ $org->id }}" method="POST" action="{{ route('profile.managed.leave', $org->id) }}" data-loading-label="Retrait…">
                        @csrf @method('DELETE')
                        <button type="button" class="btn-ghost btn-sm shrink-0"
                                onclick="openConfirmModal('managed-account-leave-{{ $org->id }}', @js('Vous ne pourrez plus publier ni agir au nom de ' . $orgName . '.'), 'Me retirer', 'Me retirer', 'fa-user-minus')">
                            Me retirer
                        </button>
                    </form>
                </span>
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif
@endsection

@extends('layouts.app')
@section('title', 'Utilisateurs')
@php
    $header      = 'Utilisateurs';
    $subheader   = 'Rôles et statuts des comptes.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Administration', 'url' => route('admin.dashboard')],
        ['label' => 'Utilisateurs'],
    ];
    $roles    = ['visiteur' => 'Visiteur', 'utilisateur' => 'Utilisateur', 'moderateur' => 'Modérateur', 'administrateur' => 'Administrateur'];
    $statuses = ['active' => 'Actif', 'suspended' => 'Suspendu', 'banned' => 'Banni'];
    $activeFilters = (request('role') ? 1 : 0) + (request('status') ? 1 : 0);
    // Onglets : docs/fonctionnalites/comptes-organisation.md
    // [libellé, libellé court affiché sous 640 px]
    $tabs = [
        'all'           => ['Tous les comptes', 'Tous'],
        'organizations' => ['Organisations', 'Organisations'],
        'pending'       => ['Organisations en attente', 'En attente'],
    ];
@endphp

@section('content')
<nav class="mb-6 overflow-x-auto border-b border-slate-200" aria-label="Type de compte">
    <ul class="flex gap-4 sm:gap-6">
        @foreach($tabs as $val => [$label, $shortLabel])
        <li>
            <a href="{{ route('admin.users.index', $val === 'all' ? [] : ['tab' => $val]) }}"
               class="{{ $tab === $val ? 'tab-active' : 'tab' }} whitespace-nowrap"
               @if($tab === $val) aria-current="page" @endif>
                <span class="hidden sm:inline">{{ $label }}</span><span class="sm:hidden">{{ $shortLabel }}</span>
                @if($val === 'pending' && $pendingOrganizations > 0)
                <span class="rounded-full bg-accent-500 px-1.5 text-[11px] leading-5 text-white">{{ $pendingOrganizations }}</span>
                @endif
            </a>
        </li>
        @endforeach
    </ul>
</nav>

<form method="GET" action="{{ route('admin.users.index') }}" class="mb-6 space-y-3" data-loading-inline data-loading-label="Filtrage…">
    @if($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
    <div class="flex flex-wrap gap-2">
        <label for="users-q" class="sr-only">Rechercher</label>
        <div class="relative min-w-0 flex-1 basis-full sm:basis-60">
            <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400"></i>
            <input id="users-q" type="search" name="q" value="{{ request('q') }}" class="form-input pl-9" placeholder="Nom, e-mail…">
        </div>
        <button type="button" class="btn-secondary md:hidden" data-toggle="users-filters" aria-controls="users-filters" aria-expanded="false">
            <i class="fa-solid fa-sliders"></i>Filtres
            @if($activeFilters)<span class="rounded-full bg-accent-500 px-1.5 text-[11px] leading-5 text-white">{{ $activeFilters }}</span>@endif
        </button>
        <button type="submit" class="btn-primary">Filtrer</button>
    </div>
    <div id="users-filters" class="hidden md:block">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:flex md:flex-wrap">
        <div class="md:w-52">
            <label for="users-role" class="form-label">Rôle</label>
            <select id="users-role" name="role" class="form-input">
                <option value="">Tous les rôles</option>
                @foreach($roles as $v => $l)
                <option value="{{ $v }}" @selected(request('role') === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:w-52">
            <label for="users-status" class="form-label">Statut</label>
            <select id="users-status" name="status" class="form-input">
                <option value="">Tous les statuts</option>
                @foreach($statuses as $v => $l)
                <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
    </div>
    </div>
</form>

@if($users->isEmpty())
    @include('components.empty-state', match (true) {
        $tab === 'pending' && !request()->anyFilled(['q', 'role', 'status']) => ['title' => 'Aucune organisation en attente', 'text' => 'Toutes les demandes de vérification ont été traitées.'],
        default => ['title' => 'Aucun utilisateur', 'text' => 'Aucun compte ne correspond à ces critères.'],
    })
@else
    {{-- Mobile : fiches --}}
    <ul class="space-y-3 md:hidden">
        @foreach($users as $user)
        <li class="card p-4">
            <div class="flex items-center gap-3">
                @include('components.avatar', ['user' => $user, 'size' => 'md'])
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium text-slate-900">{{ $user->display_name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                </div>
                <span class="{{ $user->status->badgeClass() }}">{{ $user->status->label() }}</span>
            </div>
            <dl class="mt-3 space-y-1 text-sm">
                @if($user->isOrganization())
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Organisation</dt><dd class="text-right text-slate-900">{{ $user->organization_type?->label() ?? 'Organisation' }}</dd></div>
                @if($user->verification_status)
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Vérification</dt><dd><span class="{{ $user->verification_status->badgeClass() }}">{{ $user->verification_status->label() }}</span></dd></div>
                @endif
                @endif
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Rôle</dt><dd class="text-slate-900">{{ $user->role->label() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Inscrit le</dt><dd class="text-slate-900">{{ $user->created_at->format('d/m/Y') }}</dd></div>
            </dl>
            <div class="mt-3 flex justify-end border-t border-slate-100 pt-3">
                <a href="{{ route('admin.users.show', $user->id) }}" class="action-btn-edit"><i class="fa-solid fa-pen"></i>Gérer</a>
            </div>
        </li>
        @endforeach
    </ul>

    {{-- Tablette et ordinateur : tableau --}}
    <div class="card hidden overflow-hidden md:block">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="table-th">Utilisateur</th>
                        <th class="table-th hidden xl:table-cell">E-mail</th>
                        <th class="table-th">Rôle</th>
                        <th class="table-th">Statut</th>
                        <th class="table-th hidden xl:table-cell">Inscrit le</th>
                        <th class="table-th text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    <tr class="hover:bg-slate-50">
                        <td class="table-td">
                            <div class="flex items-center gap-3">
                                @include('components.avatar', ['user' => $user, 'size' => 'sm'])
                                <div class="min-w-0">
                                    <p class="font-medium break-words text-slate-900">{{ $user->display_name }}</p>
                                    <p class="text-xs break-all text-slate-500 xl:hidden">{{ $user->email }}</p>
                                    @if($user->isOrganization())
                                    <p class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                        <span>{{ $user->organization_type?->label() ?? 'Organisation' }}@if($user->organization_city) · {{ $user->organization_city }}@endif</span>
                                        @if($user->verification_status)<span class="{{ $user->verification_status->badgeClass() }}">{{ $user->verification_status->label() }}</span>@endif
                                    </p>
                                    @elseif($user->country)<p class="text-xs text-slate-500">{{ $user->country }}</p>@endif
                                </div>
                            </div>
                        </td>
                        <td class="table-td hidden text-slate-500 xl:table-cell">{{ $user->email }}</td>
                        <td class="table-td whitespace-nowrap">{{ $user->role->label() }}</td>
                        <td class="table-td"><span class="{{ $user->status->badgeClass() }}">{{ $user->status->label() }}</span></td>
                        <td class="table-td hidden whitespace-nowrap text-slate-500 xl:table-cell">{{ $user->created_at->format('d/m/Y') }}</td>
                        <td class="table-td">
                            <div class="flex justify-end">
                                <a href="{{ route('admin.users.show', $user->id) }}" class="action-btn-edit"><i class="fa-solid fa-pen"></i>Gérer</a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $users->withQueryString()->links() }}</div>
@endif
@endsection

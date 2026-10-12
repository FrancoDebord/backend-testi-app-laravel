@extends('layouts.app')
@section('title', 'Vérification des comptes')
@php
    // docs/fonctionnalites/verification-des-comptes.md
    $header      = 'Vérification des comptes';
    $subheader   = 'Demandes de badge « Compte vérifié » des comptes personnels.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Administration', 'url' => route('admin.dashboard')],
        ['label' => 'Vérification des comptes'],
    ];
    $tabs = [
        'pending'  => 'En attente',
        'approved' => 'Approuvées',
        'rejected' => 'Refusées',
        'revoked'  => 'Retirées',
        'all'      => 'Toutes',
    ];
@endphp

@section('content')
<nav class="mb-6 overflow-x-auto border-b border-slate-200" aria-label="Statut des demandes">
    <ul class="flex gap-4 sm:gap-6">
        @foreach($tabs as $val => $label)
        <li>
            <a href="{{ route('admin.verifications.index', $val === 'pending' ? [] : ['status' => $val]) }}"
               class="{{ $tab === $val ? 'tab-active' : 'tab' }} whitespace-nowrap"
               @if($tab === $val) aria-current="page" @endif>
                {{ $label }}
                @if($val === 'pending' && $pendingCount > 0)
                <span class="rounded-full bg-accent-500 px-1.5 text-[11px] leading-5 text-white">{{ $pendingCount }}</span>
                @endif
            </a>
        </li>
        @endforeach
    </ul>
</nav>

<p class="mb-4 text-sm text-slate-600">Les organisations se vérifient depuis <a href="{{ route('admin.users.index', ['tab' => 'pending']) }}" class="font-semibold text-primary-600 hover:underline">Utilisateurs › Organisations en attente</a>.</p>

@if($requests->isEmpty())
    @include('components.empty-state', ['title' => 'Aucune demande', 'text' => $tab === 'pending' ? 'Aucune demande de vérification à examiner.' : 'Aucune demande dans cette catégorie.'])
@else
<ul class="card divide-y divide-slate-100">
    @foreach($requests as $item)
    <li>
        <a href="{{ route('admin.verifications.show', $item->id) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 sm:px-5">
            @if($item->user)
            @include('components.avatar', ['user' => $item->user, 'size' => 'sm'])
            @endif
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold text-slate-900">{{ $item->full_name }}</span>
                <span class="block truncate text-xs text-slate-500">{{ $item->roleLabel() }} · {{ $item->church }}</span>
                <span class="block text-xs text-slate-400">{{ $item->created_at->translatedFormat('j M Y à H:i') }} · {{ count($item->documents ?? []) }} pièce(s)</span>
            </span>
            <span class="{{ $item->statusBadgeClass() }} shrink-0">{{ $item->statusLabel() }}</span>
        </a>
    </li>
    @endforeach
</ul>
<div class="mt-6">{{ $requests->links() }}</div>
@endif
@endsection

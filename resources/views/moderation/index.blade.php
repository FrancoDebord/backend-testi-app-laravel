@extends('layouts.app')
@section('title', 'Modération')
@php
    $header      = 'File de modération';
    $subheader   = 'Relisez les témoignages soumis avant leur publication.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Modération'],
    ];
    $currentStatus = request('status', 'pending');
    $statCards = [
        ['En attente',              $stats['pending'],        'bg-amber-500'],
        ['Approuvés aujourd’hui',   $stats['approvedToday'],  'bg-emerald-500'],
        ['Rejetés aujourd’hui',     $stats['rejectedToday'],  'bg-red-500'],
        ['Total ce mois',           $stats['totalThisMonth'], 'bg-slate-400'],
    ];
    $tabs = ['pending' => 'En attente', 'approved' => 'Approuvés', 'rejected' => 'Rejetés', 'all' => 'Tous'];
@endphp

@section('content')
<div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach($statCards as [$label, $value, $dot])
    <div class="card p-4">
        <p class="flex items-center gap-2 text-sm text-slate-500"><span class="h-2 w-2 shrink-0 rounded-full {{ $dot }}"></span>{{ $label }}</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($value, 0, ',', ' ') }}</p>
    </div>
    @endforeach
</div>

<nav class="mb-6 overflow-x-auto border-b border-slate-200" aria-label="Filtrer par statut">
    <ul class="flex gap-6">
        @foreach($tabs as $val => $label)
        <li>
            <a href="{{ route('moderation.index', ['status' => $val]) }}"
               class="{{ $currentStatus === $val ? 'tab-active' : 'tab' }}"
               @if($currentStatus === $val) aria-current="page" @endif>{{ $label }}</a>
        </li>
        @endforeach
    </ul>
</nav>

@if($items->isEmpty())
    @include('components.empty-state', ['title' => 'Aucun élément', 'text' => 'Aucun témoignage ne correspond à ce statut.'])
@else
    {{-- Formulaires d'approbation (partagés par le tableau et les fiches mobiles) --}}
    @foreach($items as $item)
        @if($item->status->value === 'pending')
        <form id="approve-{{ $item->id }}" method="POST" action="{{ route('moderation.approve', $item->id) }}" data-loading-label="Publication…" hidden>@csrf</form>
        @endif
    @endforeach

    {{-- Mobile : fiches --}}
    <ul class="space-y-3 md:hidden">
        @foreach($items as $item)
        <li class="card p-4">
            <div class="flex items-start justify-between gap-3">
                <p class="min-w-0 font-medium break-words text-slate-900">{{ $item->title }}</p>
                <span class="{{ $item->status->badgeClass() }}">{{ $item->status->label() }}</span>
            </div>
            <dl class="mt-3 space-y-1 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Auteur</dt><dd class="truncate text-slate-900">{{ $item->user->display_name }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Type</dt><dd class="text-slate-900">{{ $item->type->label() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Soumis le</dt><dd class="text-slate-900">{{ $item->created_at->format('d/m/Y') }}</dd></div>
            </dl>
            <div class="mt-3 flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-3">
                <a href="{{ route('moderation.show', $item->id) }}" class="action-btn-view"><i class="fa-regular fa-eye"></i>Examiner</a>
                @if($item->status->value === 'pending')
                <button type="button" class="action-btn-success"
                        onclick="openConfirmModal('approve-{{ $item->id }}', @js('« ' . Str::limit($item->title, 80) . ' » sera publié immédiatement.'), 'Approuver ce témoignage', 'Approuver et publier', 'fa-check')">
                    <i class="fa-solid fa-check"></i>Approuver
                </button>
                @endif
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
                        <th class="table-th">Témoignage</th>
                        <th class="table-th">Auteur</th>
                        <th class="table-th">Type</th>
                        <th class="table-th">Statut</th>
                        <th class="table-th">Soumis le</th>
                        <th class="table-th text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    <tr class="hover:bg-slate-50">
                        <td class="table-td max-w-xs">
                            <p class="truncate font-medium text-slate-900">{{ $item->title }}</p>
                            @if($item->body_text)<p class="truncate text-xs text-slate-500">{{ $item->body_plain }}</p>@endif
                        </td>
                        <td class="table-td whitespace-nowrap">{{ $item->user->display_name }}</td>
                        <td class="table-td">{{ $item->type->label() }}</td>
                        <td class="table-td"><span class="{{ $item->status->badgeClass() }}">{{ $item->status->label() }}</span></td>
                        <td class="table-td whitespace-nowrap text-slate-500">{{ $item->created_at->format('d/m/Y') }}</td>
                        <td class="table-td">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('moderation.show', $item->id) }}" class="action-btn-view"><i class="fa-regular fa-eye"></i>Examiner</a>
                                @if($item->status->value === 'pending')
                                <button type="button" class="action-btn-success" title="Approuver et publier" aria-label="Approuver et publier"
                                        onclick="openConfirmModal('approve-{{ $item->id }}', @js('« ' . Str::limit($item->title, 80) . ' » sera publié immédiatement.'), 'Approuver ce témoignage', 'Approuver et publier', 'fa-check')">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $items->withQueryString()->links() }}</div>
@endif
@endsection

@extends('layouts.app')
@section('title', 'Requêtes de prière signalées')
@php
    $header      = 'Requêtes de prière signalées';
    $subheader   = 'Publiées sans relecture : retirez celles qui ne respectent pas la charte. Au 3e signalement, une requête est retirée automatiquement en attendant votre décision.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Modération', 'url' => route('moderation.index')],
        ['label' => 'Requêtes de prière'],
    ];
@endphp

@section('content')
<div class="mb-5 flex gap-2" role="group" aria-label="Filtre">
    <a href="{{ route('prayer.moderation.index') }}" class="{{ $filter === 'reported' ? 'chip-active' : 'chip' }}">Signalées</a>
    <a href="{{ route('prayer.moderation.index', ['filtre' => 'retirees']) }}" class="{{ $filter === 'hidden' ? 'chip-active' : 'chip' }}">Retirées</a>
</div>

@if($items->isEmpty())
    @include('components.empty-state', ['title' => $filter === 'hidden' ? 'Aucune requête retirée' : 'Aucun signalement à traiter'])
@else
<ul class="mb-6 space-y-4">
    @foreach($items as $item)
    <li class="card p-5">
        <div class="flex flex-wrap items-center gap-2">
            <p class="mr-auto text-sm font-semibold text-slate-900">
                {{ $item->user?->display_name }} @if($item->is_anonymous)<span class="badge-neutral">anonyme</span>@endif
                <span class="text-xs font-normal text-slate-500">· {{ $item->created_at?->diffForHumans() }}</span>
            </p>
            @if($item->isHidden())<span class="badge-rejected">Retirée</span>@endif
            <span class="badge-orange">{{ $item->pending_reports_count }} signalement{{ $item->pending_reports_count > 1 ? 's' : '' }} à traiter</span>
        </div>
        <p class="mt-3 text-sm break-words whitespace-pre-line text-slate-800">{{ Str::limit($item->body, 600) }}</p>
        @if($item->isHidden() && $item->hidden_reason)<p class="mt-2 text-xs text-slate-500">Motif : {{ $item->hidden_reason }}</p>@endif

        @if($item->reports->isNotEmpty())
        <ul class="mt-3 space-y-1 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
            @foreach($item->reports as $report)
            <li class="{{ $report->reviewed_at ? 'opacity-60' : '' }}">
                <span class="font-semibold">{{ $reasons[$report->reason] ?? $report->reason }}</span>
                — {{ $report->reporter?->display_name }}@if($report->comment) : « {{ $report->comment }} »@endif
            </li>
            @endforeach
        </ul>
        @endif

        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('prayer.requests.show', $item->id) }}" class="btn-ghost btn-sm"><i class="fa-regular fa-eye" aria-hidden="true"></i>Voir</a>
            @if($item->isHidden())
            <form method="POST" action="{{ route('prayer.moderation.restore', $item->id) }}" data-loading-label="Rétablissement…">
                @csrf
                <button type="submit" class="btn-secondary btn-sm"><i class="fa-solid fa-eye" aria-hidden="true"></i>Rétablir</button>
            </form>
            @else
            <form method="POST" action="{{ route('prayer.moderation.restore', $item->id) }}" data-loading-label="Enregistrement…">
                @csrf
                <button type="submit" class="btn-secondary btn-sm"><i class="fa-solid fa-check" aria-hidden="true"></i>Garder (classer les signalements)</button>
            </form>
            <form method="POST" action="{{ route('prayer.moderation.hide', $item->id) }}" class="flex gap-2" data-loading-label="Retrait…">
                @csrf
                <label for="reason-{{ $item->id }}" class="sr-only">Motif</label>
                <input id="reason-{{ $item->id }}" type="text" name="reason" maxlength="300" class="form-input py-1.5 text-sm" placeholder="Motif (facultatif)">
                <button type="submit" class="btn-secondary btn-sm text-error-700"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i>Retirer</button>
            </form>
            @endif
            <form method="POST" action="{{ route('prayer.requests.destroy', $item->id) }}" data-loading-label="Suppression…">
                @csrf @method('DELETE')
                <button type="submit" class="btn-ghost btn-sm text-error-700"><i class="fa-solid fa-trash" aria-hidden="true"></i>Supprimer</button>
            </form>
        </div>
    </li>
    @endforeach
</ul>
{{ $items->links() }}
@endif
@endsection

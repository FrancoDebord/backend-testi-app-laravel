@extends('layouts.app')
@section('title', 'Mes témoignages')
@php
    $header      = 'Mes témoignages';
    $subheader   = 'Suivez l’état de vos témoignages : publiés, en relecture ou refusés.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Mes témoignages'],
    ];
    $tabs = [
        'all'      => ['Tous', null],
        'approved' => ['Publiés', 'bg-emerald-500'],
        'pending'  => ['En attente', 'bg-amber-500'],
        'rejected' => ['Refusés', 'bg-red-500'],
        'draft'    => ['Brouillons', 'bg-slate-400'],
    ];
    $currentStatus = $status ?: 'all';
@endphp

@section('headerActions')
    <a href="{{ route('publish') }}" class="btn-cta"><i class="fa-solid fa-plus"></i>Nouveau témoignage</a>
@endsection

@section('content')
<nav class="mb-6 overflow-x-auto border-b border-slate-200" aria-label="Filtrer par statut">
    <ul class="flex gap-6">
        @foreach($tabs as $val => [$label, $dot])
        <li>
            <a href="{{ route('testimonies.mine', ['status' => $val]) }}"
               class="{{ $currentStatus === $val ? 'tab-active' : 'tab' }}"
               @if($currentStatus === $val) aria-current="page" @endif>
                @if($dot)<span class="h-2 w-2 rounded-full {{ $dot }}"></span>@endif
                {{ $label }}
            </a>
        </li>
        @endforeach
    </ul>
</nav>

@if($testimonies->isEmpty())
    @include('components.empty-state', [
        'title' => 'Aucun témoignage ici',
        'text' => $currentStatus === 'all' ? "Vous n'avez pas encore publié de témoignage." : "Aucun de vos témoignages n'a ce statut.",
        'actionUrl' => route('publish'),
        'actionLabel' => 'Publier un témoignage',
    ])
@else
    <div class="mb-3 flex justify-end">@include('components.layout-toggle')</div>
    <div class="mb-8">
        @include('components.testimony-list', ['items' => $testimonies, 'routeName' => 'testimonies.show'])
    </div>
    {{ $testimonies->withQueryString()->links() }}
@endif
@endsection

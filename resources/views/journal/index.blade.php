@extends('layouts.app')
@section('title', 'Carnet privé')
@php
    $header      = 'Carnet privé';
    $subheader   = 'Gardez pour vous ce que Dieu a fait, et partagez-le quand vous le souhaitez.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Carnet privé'],
    ];
    $types    = \App\Http\Controllers\Web\JournalController::TYPES;
    $filtered = $type || $q !== '';
@endphp

@section('headerActions')
    <a href="{{ route('publish', ['visibility' => 'private']) }}" class="btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>Nouvelle entrée</a>
@endsection

@section('content')
@include('journal.partials.tabs', ['current' => 'testimonies'])

<div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
    <form method="GET" action="{{ route('journal.index') }}" role="search" class="flex w-full max-w-xl gap-2" data-loading-inline data-loading-label="Recherche…">
        @if($type)<input type="hidden" name="type" value="{{ $type }}">@endif
        <label for="journal-q" class="sr-only">Rechercher dans mon carnet</label>
        <div class="relative min-w-0 flex-1">
            <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
            <input id="journal-q" type="search" name="q" value="{{ $q }}" class="form-input pl-9" placeholder="Titre ou texte…">
        </div>
        <button type="submit" class="btn-secondary" aria-label="Rechercher"><i class="fa-solid fa-magnifying-glass sm:hidden" aria-hidden="true"></i><span class="hidden sm:inline" aria-hidden="true">Rechercher</span></button>
    </form>

    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="Format">
        <a href="{{ route('journal.index', array_filter(['q' => $q ?: null])) }}" class="{{ !$type ? 'chip-active' : 'chip' }}" @if(!$type) aria-current="true" @endif>Tous</a>
        @foreach($types as $value => $label)
        <a href="{{ route('journal.index', array_filter(['type' => $value, 'q' => $q ?: null])) }}"
           class="{{ $type === $value ? 'chip-active' : 'chip' }}" @if($type === $value) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </div>
</div>

@if($entries->isEmpty())
    @include('components.empty-state', [
        'title'         => $filtered ? 'Aucun résultat' : 'Votre carnet est vide',
        'text'          => $filtered
            ? "Essayez d'autres mots-clés ou retirez le filtre."
            : "Notez ici ce que Dieu fait dans votre vie, en texte, en audio ou en vidéo. Choisissez « Privé » au moment de publier : l'entrée reste dans votre carnet.",
        'actionUrl'     => $filtered ? route('journal.index') : route('publish', ['visibility' => 'private']),
        'actionLabel'   => $filtered ? 'Effacer les filtres' : 'Écrire dans mon carnet',
        'actionPrimary' => !$filtered,
    ])
    @if(!$filtered && $prophecyCount === 0)
    <p class="mt-4 text-center text-sm text-slate-500">
        Vous avez reçu une parole prophétique ? <a href="{{ route('prophecies.create') }}" class="font-semibold text-primary-600 hover:underline">Gardez-la dans votre carnet</a>.
    </p>
    @endif
@else
    <div class="mb-3 flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500">{{ $entries->total() }} {{ $entries->total() > 1 ? 'entrées' : 'entrée' }}</p>
        @include('components.layout-toggle')
    </div>
    <div class="mb-8">
        @include('components.testimony-list', ['items' => $entries, 'routeName' => 'testimonies.show'])
    </div>
    {{ $entries->links() }}
@endif
@endsection

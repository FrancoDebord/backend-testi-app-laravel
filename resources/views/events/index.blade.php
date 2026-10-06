@extends('layouts.app')
@section('title', 'Événements')
@php
    $header      = 'Événements chrétiens';
    $subheader   = 'Croisades, conférences, camps, concerts de louange : participez et partagez ce que Dieu y a fait.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Événements'],
    ];
    // Paramètres conservés d'un filtre à l'autre.
    $params = array_filter(['onglet' => $tab !== 'upcoming' ? $tab : null, 'type' => $type, 'q' => $q !== '' ? $q : null]);
    $types  = \App\Enums\EventType::options();
@endphp

@if($canCreate)
@section('headerActions')
    <a href="{{ route('events.create') }}" class="btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>Créer un événement</a>
@endsection
@endif

@section('content')
<form method="GET" action="{{ route('events.index') }}" role="search" class="mb-5 flex max-w-2xl gap-2" data-loading-inline data-loading-label="Recherche…">
    @if($tab !== 'upcoming')<input type="hidden" name="onglet" value="{{ $tab }}">@endif
    @if($type)<input type="hidden" name="type" value="{{ $type }}">@endif
    <label for="events-q" class="sr-only">Rechercher un événement</label>
    <div class="relative min-w-0 flex-1">
        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
        <input id="events-q" type="search" name="q" value="{{ $q }}" class="form-input pl-9" placeholder="Titre, ville, lieu…">
    </div>
    <button type="submit" class="btn-secondary" aria-label="Rechercher"><i class="fa-solid fa-magnifying-glass sm:hidden" aria-hidden="true"></i><span class="hidden sm:inline" aria-hidden="true">Rechercher</span></button>
</form>

<nav aria-label="Événements" class="mb-4 border-b border-slate-200">
    <ul class="-mb-px flex gap-5 overflow-x-auto">
        @foreach($tabs as $key => $label)
        @php $active = $tab === $key; @endphp
        <li>
            <a href="{{ route('events.index', array_merge($params, ['onglet' => $key === 'upcoming' ? null : $key])) }}"
               class="{{ $active ? 'tab-active' : 'tab' }}" @if($active) aria-current="page" @endif>{{ $label }}</a>
        </li>
        @endforeach
    </ul>
</nav>

<div class="-mx-4 mb-6 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="Type d'événement">
    <a href="{{ route('events.index', array_merge($params, ['type' => null])) }}" class="{{ !$type ? 'chip-active' : 'chip' }}" @if(!$type) aria-current="true" @endif>Tous</a>
    @foreach($types as $value => $label)
    <a href="{{ route('events.index', array_merge($params, ['type' => $value])) }}"
       class="{{ $type === $value ? 'chip-active' : 'chip' }}" @if($type === $value) aria-current="true" @endif>{{ $label }}</a>
    @endforeach
</div>

@if($events->isEmpty())
    @php
        $filtered = $type || $q !== '';
        $emptyText = match ($tab) {
            'past'  => 'Les événements terminés apparaîtront ici.',
            'mine'  => "Vous n'avez pas encore créé d'événement.",
            'going' => "Vous n'avez répondu « Je participe » à aucun événement.",
            default => 'Aucun événement annoncé pour le moment.',
        };
    @endphp
    @include('components.empty-state', [
        'title'       => $filtered ? 'Aucun résultat' : 'Aucun événement',
        'text'        => $filtered ? "Essayez d'autres mots-clés ou retirez le filtre." : $emptyText,
        'actionUrl'   => $filtered ? route('events.index', array_filter(['onglet' => $tab !== 'upcoming' ? $tab : null])) : ($tab === 'mine' && $canCreate ? route('events.create') : null),
        'actionLabel' => $filtered ? 'Effacer les filtres' : 'Créer un événement',
    ])
@else
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:gap-5">
        @foreach($events as $event)
            @include('events.partials.card', ['event' => $event, 'showStatus' => $tab === 'mine'])
        @endforeach
    </div>
    {{ $events->links() }}
@endif
@endsection

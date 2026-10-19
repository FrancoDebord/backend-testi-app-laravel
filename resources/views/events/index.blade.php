@extends('layouts.app')
@section('title', __('layout.nav.events'))
@php
    $header      = __('events.index.header');
    $subheader   = __('events.index.subheader');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.events')],
    ];
    // Paramètres conservés d'un filtre à l'autre.
    $params = array_filter(['onglet' => $tab !== 'upcoming' ? $tab : null, 'type' => $type, 'q' => $q !== '' ? $q : null]);
    // Types actifs (table event_types) ; le type filtré reste affiché s'il a été désactivé.
    $types  = \App\Models\EventType::cached()->filter(fn ($t) => $t->is_active || $t->slug === $type);
@endphp

@if($canCreate)
@section('headerActions')
    <a href="{{ route('events.create') }}" class="btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>{{ __('events.index.create') }}</a>
@endsection
@endif

@section('content')
<form method="GET" action="{{ route('events.index') }}" role="search" class="mb-5 flex max-w-2xl gap-2" data-loading-inline data-loading-label="{{ __('events_past.searching') }}">
    @if($tab !== 'upcoming')<input type="hidden" name="onglet" value="{{ $tab }}">@endif
    @if($type)<input type="hidden" name="type" value="{{ $type }}">@endif
    <label for="events-q" class="sr-only">{{ __('events.index.search_label') }}</label>
    <div class="relative min-w-0 flex-1">
        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
        <input id="events-q" type="search" name="q" value="{{ $q }}" data-search-autocomplete="events" autocomplete="off" class="form-input pl-9" placeholder="{{ __('events_past.search_placeholder') }}">
    </div>
    <button type="submit" class="btn-secondary" aria-label="{{ __('events_past.search') }}"><i class="fa-solid fa-magnifying-glass sm:hidden" aria-hidden="true"></i><span class="hidden sm:inline" aria-hidden="true">{{ __('events_past.search') }}</span></button>
</form>

<nav aria-label="{{ __('layout.nav.events') }}" class="mb-4 border-b border-slate-200">
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

<div class="-mx-4 mb-6 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="{{ __('events_past.type_filter') }}">
    <a href="{{ route('events.index', array_merge($params, ['type' => null])) }}" class="{{ !$type ? 'chip-active' : 'chip' }}" @if(!$type) aria-current="true" @endif>{{ __('events_past.all') }}</a>
    @foreach($types as $t)
    <a href="{{ route('events.index', array_merge($params, ['type' => $t->slug])) }}"
       class="{{ $type === $t->slug ? 'chip-active' : 'chip' }}" @if($type === $t->slug) aria-current="true" @endif><i class="fa-solid {{ $t->faIcon() }}" aria-hidden="true"></i>{{ $t->localizedName() }}</a>
    @endforeach
</div>

@if($events->isEmpty())
    @php
        $filtered = $type || $q !== '';
        $emptyText = match ($tab) {
            'past'  => __('events_past.empty_text'),
            'mine'  => __('events.index.empty_mine'),
            'going' => __('events.index.empty_going'),
            default => __('events.index.empty_upcoming'),
        };
    @endphp
    @include('components.empty-state', [
        'title'       => $filtered ? __('events_past.no_result') : __('events.index.empty_title'),
        'text'        => $filtered ? __('events_past.no_result_text') : $emptyText,
        'actionUrl'   => $filtered ? route('events.index', array_filter(['onglet' => $tab !== 'upcoming' ? $tab : null])) : ($tab === 'mine' && $canCreate ? route('events.create') : null),
        'actionLabel' => $filtered ? __('events_past.clear_filters') : __('events.index.create'),
    ])
@else
    <div class="mb-3 flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500">{{ trans_choice('events.index.count', $events->total(), ['count' => \App\Support\Locales::number($events->total())]) }}</p>
        @include('events.partials.layout-toggle')
    </div>
    @if($compact)
    <ul class="card mb-8 divide-y divide-slate-100 overflow-hidden">
        @foreach($events as $event)
            @include('events.partials.row', ['event' => $event, 'showStatus' => $tab === 'mine'])
        @endforeach
    </ul>
    @else
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:gap-5">
        @foreach($events as $event)
            @include('events.partials.card', ['event' => $event, 'showStatus' => $tab === 'mine'])
        @endforeach
    </div>
    @endif
    {{ $events->links() }}
@endif
@endsection

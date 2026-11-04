@extends('layouts.app')
@php
    // Recherche globale : un onglet par rubrique, nombre de résultats sur chaque onglet. docs/fonctionnalites/recherche.md
    $header      = $ready ? __('search.page.title_for', ['q' => $q]) : __('search.page.title');
    $subheader   = __('search.page.subtitle');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('search.page.title')],
    ];
    $icons = [
        'temoignages' => 'fa-solid fa-comment-dots',
        'comptes'     => 'fa-solid fa-users',
        'prieres'     => 'fa-solid fa-hands-praying',
        'evenements'  => 'fa-solid fa-calendar-days',
        'sessions'    => 'fa-solid fa-person-praying',
    ];
@endphp
@section('title', $header)

@section('content')
<form method="GET" action="{{ route('search') }}" role="search" class="mb-5 flex max-w-2xl gap-2" data-loading-inline data-loading-label="{{ __('search.page.searching') }}">
    @if(request()->filled('onglet'))<input type="hidden" name="onglet" value="{{ $tab }}">@endif
    <label for="search-q" class="sr-only">{{ __('search.page.label') }}</label>
    <div class="relative min-w-0 flex-1">
        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
        <input id="search-q" type="search" name="q" value="{{ $q }}" maxlength="100" autocomplete="off"
               data-search-autocomplete="all" data-search-recent-key="explore"
               class="form-input pl-9" placeholder="{{ __('search.page.placeholder') }}">
    </div>
    <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass sm:hidden" aria-hidden="true"></i><span class="max-sm:sr-only">{{ __('search.page.submit') }}</span></button>
</form>

@if(!$ready)
    @include('components.empty-state', [
        'title' => $q === '' ? __('search.page.start') : __('search.page.too_short', ['min' => \App\Services\GlobalSearch::MIN_LENGTH]),
        'text'  => __('search.page.start_text'),
    ])
@else
    <nav class="mb-6 flex gap-6 overflow-x-auto border-b border-slate-200" aria-label="{{ __('search.page.tabs_label') }}">
        @foreach(\App\Services\GlobalSearch::TABS as $key)
        <a href="{{ route('search', ['q' => $q, 'onglet' => $key]) }}" class="{{ $tab === $key ? 'tab-active' : 'tab' }}" @if($tab === $key) aria-current="page" @endif>
            <i class="{{ $icons[$key] }}" aria-hidden="true"></i>{{ __('search.page.tabs.' . $key) }}
            <span class="{{ $counts[$key] > 0 ? 'badge-blue' : 'badge-neutral' }}">{{ $counts[$key] > 999 ? '999+' : $counts[$key] }}</span>
        </a>
        @endforeach
    </nav>

    @if(!$results)
        @include('components.empty-state', ['title' => __('search.page.empty'), 'text' => __('search.page.empty_text')])
    @else
        @switch($tab)
            @case('comptes')
                <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($results as $account)
                        @include('community.partials.card', ['account' => $account])
                    @endforeach
                </div>
                @break
            @case('prieres')
                <div class="mb-8 grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-5">
                    @foreach($results as $prayerRequest)
                        @include('prayer.partials.feed-card', ['request' => $prayerRequest])
                    @endforeach
                </div>
                @break
            @case('evenements')
                <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:gap-5">
                    @foreach($results as $event)
                        @include('events.partials.card', ['event' => $event, 'showStatus' => false])
                    @endforeach
                </div>
                @break
            @case('sessions')
                <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 xl:gap-5">
                    @foreach($results as $session)
                        @include('prayer.partials.session-card', ['session' => $session])
                    @endforeach
                </div>
                @break
            @default
                <div class="mb-8">
                    @include('components.testimony-list', ['items' => $results, 'routeName' => 'testimonies.show', 'listId' => null, 'gridClass' => null, 'tab' => null])
                </div>
        @endswitch
        {{ $results->links() }}
    @endif
@endif
@endsection

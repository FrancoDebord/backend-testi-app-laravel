@extends('layouts.app')
@section('title', __('events_past.title'))
@php
    $header      = __('events_past.title');
    $subheader   = __('events_past.subtitle');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('events_past.events'), 'url' => route('events.index')],
        ['label' => __('events_past.title')],
    ];
    $params = array_filter(['type' => $type, 'q' => $q !== '' ? $q : null]);
    // Types actifs (table event_types) ; le type filtré reste affiché s'il a été désactivé.
    $types  = \App\Models\EventType::cached()->filter(fn ($t) => $t->is_active || $t->slug === $type);
@endphp

@section('headerActions')
    <a href="{{ route('events.index') }}" class="btn-secondary"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i>{{ __('events_past.upcoming') }}</a>
@endsection

@section('content')
<form method="GET" action="{{ route('events.past') }}" role="search" class="mb-5 flex max-w-2xl gap-2" data-loading-inline data-loading-label="{{ __('events_past.searching') }}">
    @if($type)<input type="hidden" name="type" value="{{ $type }}">@endif
    <label for="past-events-q" class="sr-only">{{ __('events_past.search_label') }}</label>
    <div class="relative min-w-0 flex-1">
        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
        <input id="past-events-q" type="search" name="q" value="{{ $q }}" autocomplete="off" class="form-input pl-9" placeholder="{{ __('events_past.search_placeholder') }}">
    </div>
    <button type="submit" class="btn-secondary" aria-label="{{ __('events_past.search') }}"><i class="fa-solid fa-magnifying-glass sm:hidden" aria-hidden="true"></i><span class="hidden sm:inline" aria-hidden="true">{{ __('events_past.search') }}</span></button>
</form>

<div class="-mx-4 mb-6 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="{{ __('events_past.type_filter') }}">
    <a href="{{ route('events.past', array_merge($params, ['type' => null])) }}" class="{{ !$type ? 'chip-active' : 'chip' }}" @if(!$type) aria-current="true" @endif>{{ __('events_past.all') }}</a>
    @foreach($types as $t)
    <a href="{{ route('events.past', array_merge($params, ['type' => $t->slug])) }}"
       class="{{ $type === $t->slug ? 'chip-active' : 'chip' }}" @if($type === $t->slug) aria-current="true" @endif><i class="fa-solid {{ $t->faIcon() }}" aria-hidden="true"></i>{{ $t->localizedName() }}</a>
    @endforeach
</div>

@if($events->isEmpty())
    @php $filtered = $type || $q !== ''; @endphp
    @include('components.empty-state', [
        'title'       => $filtered ? __('events_past.no_result') : __('events_past.empty_title'),
        'text'        => $filtered ? __('events_past.no_result_text') : __('events_past.empty_text'),
        'actionUrl'   => $filtered ? route('events.past') : null,
        'actionLabel' => __('events_past.clear_filters'),
    ])
@else
    <p class="mb-3 text-sm text-slate-500">{{ trans_choice('events_past.count', $events->total(), ['count' => \App\Support\Locales::number($events->total())]) }}</p>
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:gap-5">
        @foreach($events as $event)
            @include('events.partials.card', ['event' => $event, 'showStatus' => false, 'showTestimonies' => true])
        @endforeach
    </div>
    {{ $events->links() }}
@endif
@endsection

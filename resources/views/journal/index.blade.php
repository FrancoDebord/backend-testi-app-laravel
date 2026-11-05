@extends('layouts.app')
@section('title', __('layout.nav.journal'))
@php
    $header      = __('layout.nav.journal');
    $subheader   = __('journal.index.subheader');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.journal')],
    ];
    $types    = \App\Http\Controllers\Web\JournalController::types();
    $filtered = $type || $q !== '';
@endphp

@section('headerActions')
    <a href="{{ route('publish', ['visibility' => 'private']) }}" class="btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>{{ __('journal.index.new_entry') }}</a>
@endsection

@section('content')
@include('journal.partials.tabs', ['current' => 'testimonies'])

<div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
    <form method="GET" action="{{ route('journal.index') }}" role="search" class="flex w-full max-w-xl gap-2" data-loading-inline data-loading-label="{{ __('journal.index.searching') }}">
        @if($type)<input type="hidden" name="type" value="{{ $type }}">@endif
        <label for="journal-q" class="sr-only">{{ __('journal.index.search_label') }}</label>
        <div class="relative min-w-0 flex-1">
            <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
            <input id="journal-q" type="search" name="q" value="{{ $q }}" data-search-autocomplete="local" data-search-recent-key="journal" autocomplete="off" class="form-input pl-9" placeholder="{{ __('journal.index.search_placeholder') }}">
        </div>
        <button type="submit" class="btn-secondary" aria-label="{{ __('journal.index.search') }}"><i class="fa-solid fa-magnifying-glass sm:hidden" aria-hidden="true"></i><span class="hidden sm:inline" aria-hidden="true">{{ __('journal.index.search') }}</span></button>
    </form>

    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="{{ __('journal.index.format') }}">
        <a href="{{ route('journal.index', array_filter(['q' => $q ?: null])) }}" class="{{ !$type ? 'chip-active' : 'chip' }}" @if(!$type) aria-current="true" @endif>{{ __('journal.index.all') }}</a>
        @foreach($types as $value => $label)
        <a href="{{ route('journal.index', array_filter(['type' => $value, 'q' => $q ?: null])) }}"
           class="{{ $type === $value ? 'chip-active' : 'chip' }}" @if($type === $value) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </div>
</div>

@if($entries->isEmpty())
    @include('components.empty-state', [
        'title'         => $filtered ? __('journal.index.no_results') : __('journal.index.empty_title'),
        'text'          => $filtered
            ? __('journal.index.no_results_text')
            : __('journal.index.empty_text'),
        'actionUrl'     => $filtered ? route('journal.index') : route('publish', ['visibility' => 'private']),
        'actionLabel'   => $filtered ? __('journal.index.clear_filters') : __('journal.index.write'),
        'actionPrimary' => !$filtered,
    ])
    @if(!$filtered && $prophecyCount === 0)
    <p class="mt-4 text-center text-sm text-slate-500">
        {{ __('journal.index.prophecy_question') }} <a href="{{ route('prophecies.create') }}" class="font-semibold text-primary-600 hover:underline">{{ __('journal.index.prophecy_link') }}</a>.
    </p>
    @endif
@else
    <div class="mb-3 flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500">{{ trans_choice('journal.index.count', $entries->total()) }}</p>
        @include('components.layout-toggle')
    </div>
    <div class="mb-8">
        @include('components.testimony-list', ['items' => $entries, 'routeName' => 'testimonies.show'])
    </div>
    {{ $entries->links() }}
@endif
@endsection

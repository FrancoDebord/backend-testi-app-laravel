@extends('layouts.app')
@section('title', __('layout.nav.explore'))
@php
    $header      = __('discover.explore.title');
    $subheader   = __('discover.explore.subheader');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.explore')],
    ];
    // Paramètres conservés d'un filtre à l'autre (mêmes paramètres d'URL qu'avant).
    $params = array_filter(['q' => $q, 'type' => $type !== 'all' ? $type : null, 'sort' => $sort !== 'recent' ? $sort : null, 'category' => $category]);
    $types  = ['all' => __('discover.explore.types.all'), 'video' => __('discover.home.types.video'), 'audio' => __('discover.home.types.audio'), 'text' => __('discover.home.types.text')];
    $sorts  = ['recent' => __('discover.explore.sorts.recent'), 'popular' => __('discover.explore.sorts.popular'), 'recommended' => __('discover.explore.sorts.recommended')];
@endphp

@section('content')
<form method="GET" action="{{ route('explore') }}" role="search" class="mb-5 flex max-w-2xl gap-2" data-loading-inline data-loading-label="{{ __('discover.explore.searching') }}">
    @if($type && $type !== 'all')<input type="hidden" name="type" value="{{ $type }}">@endif
    @if($sort && $sort !== 'recent')<input type="hidden" name="sort" value="{{ $sort }}">@endif
    @if($category)<input type="hidden" name="category" value="{{ $category }}">@endif
    <label for="explore-q" class="sr-only">{{ __('discover.explore.search') }}</label>
    <div class="relative min-w-0 flex-1">
        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
        <input id="explore-q" type="search" name="q" value="{{ $q }}" data-search-autocomplete="all" data-search-recent-key="explore" class="form-input pl-9" placeholder="{{ __('discover.explore.placeholder') }}">
    </div>
    <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass sm:hidden" aria-hidden="true"></i><span class="max-sm:sr-only">{{ __('discover.explore.search') }}</span></button>
</form>

{{-- Type (onglets), tri et catégories (pastilles) --}}
<nav aria-label="{{ __('discover.explore.type_label') }}" class="mb-4 border-b border-slate-200">
    <ul class="-mb-px flex gap-5 overflow-x-auto">
        @foreach($types as $key => $label)
        @php $active = ($type ?: 'all') === $key; @endphp
        <li>
            <a href="{{ route('explore', array_merge($params, ['type' => $key === 'all' ? null : $key])) }}"
               class="{{ $active ? 'tab-active' : 'tab' }}" @if($active) aria-current="page" @endif>{{ $label }}</a>
        </li>
        @endforeach
    </ul>
</nav>

<div class="mb-6 space-y-3">
    <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('discover.explore.sort_label') }}">
        @foreach($sorts as $key => $label)
        @php $active = ($sort ?: 'recent') === $key; @endphp
        <a href="{{ route('explore', array_merge($params, ['sort' => $key === 'recent' ? null : $key])) }}"
           class="{{ $active ? 'chip-active' : 'chip' }}" @if($active) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </div>
    @if($categories->isNotEmpty())
    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="{{ __('discover.explore.categories') }}">
        <a href="{{ route('explore', array_merge($params, ['category' => null])) }}" class="{{ !$category ? 'chip-active' : 'chip' }}">{{ __('discover.explore.all_categories') }}</a>
        @foreach($categories as $cat)
        <a href="{{ route('explore', array_merge($params, ['category' => $cat->slug])) }}"
           class="{{ $category === $cat->slug ? 'chip-active' : 'chip' }}" @if($category === $cat->slug) aria-current="true" @endif>{{ $cat->displayName() }}</a>
        @endforeach
    </div>
    @endif
</div>

@if($q)
<p class="mb-4 text-sm text-slate-600" role="status">{{ trans_choice('discover.explore.results', $results->total()) }} <span class="font-semibold text-slate-900">{{ __('prophecies.quoted', ['text' => $q]) }}</span></p>
@endif

@if($results->isEmpty())
    @include('components.empty-state', [
        'title' => __('discover.explore.empty_title'),
        'text' => __('discover.explore.empty_text'),
        'actionUrl' => ($q || $category || ($type && $type !== 'all')) ? route('explore') : null,
        'actionLabel' => __('discover.explore.clear_filters'),
    ])
@else
    <div class="mb-3 flex justify-end">@include('components.layout-toggle')</div>
    <div class="mb-8">
        @include('components.testimony-list', ['items' => $results, 'routeName' => 'testimonies.show', 'encourage' => true])
    </div>
    {{ $results->withQueryString()->links() }}
@endif
@endsection

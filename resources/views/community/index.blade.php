@extends('layouts.app')
@section('title', __('community.index.title'))
@php
    $header      = __('community.index.title');
    $subheader   = __('community.index.subheader');
    $breadcrumbs = [
        ['label' => __('profile.breadcrumb.home'), 'url' => route('home')],
        ['label' => __('community.index.title')],
    ];
    $empty = match ($tab) {
        'people'        => [__('community.index.empty_people'), __('community.index.empty_people_text')],
        'organizations' => [__('community.index.empty_organizations'), __('community.index.empty_organizations_text')],
        default         => [__('community.index.empty_all'), __('community.index.empty_all_text')],
    };
@endphp

@section('content')
<form method="GET" action="{{ route('community.index') }}" role="search" class="mb-5 flex max-w-2xl gap-2" data-loading-inline data-loading-label="{{ __('js.app.searching') }}">
    @if($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
    <label for="community-q" class="sr-only">{{ __('community.index.search_label') }}</label>
    <div class="relative min-w-0 flex-1">
        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
        <input id="community-q" type="search" name="q" value="{{ $q }}" data-search-autocomplete="users" data-search-recent-key="community" maxlength="100" autocomplete="off"
               class="form-input pl-9" placeholder="{{ $tab === 'people' ? __('community.index.placeholder_people') : __('community.search.placeholder') }}">
    </div>
    <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass sm:hidden" aria-hidden="true"></i><span class="max-sm:sr-only">{{ __('layout.search') }}</span></button>
</form>

{{-- Suggestions personnalisées du type de l'onglet (première page, sans recherche) ─ docs/fonctionnalites/abonnements.md --}}
@if($q === '' && (int) request('page', 1) <= 1)
    @include('community.partials.suggested', ['suggestedTitle' => __('community.index.suggested_title'), 'suggestedMore' => false, 'suggestedType' => $tab])
@endif

<nav aria-label="{{ __('community.index.tabs_aria') }}" class="mb-6 border-b border-slate-200">
    <ul class="-mb-px flex gap-5 overflow-x-auto">
        @foreach(\App\Services\CommunityDirectory::tabs() as $key => $label)
        <li>
            <a href="{{ route('community.index', array_filter(['tab' => $key === 'all' ? null : $key, 'q' => $q ?: null])) }}"
               class="{{ $tab === $key ? 'tab-active' : 'tab' }}" @if($tab === $key) aria-current="page" @endif>
                <i class="fa-solid {{ match ($key) { 'people' => 'fa-user', 'organizations' => 'fa-church', default => 'fa-users' } }} text-slate-400" aria-hidden="true"></i>{{ $label }}
            </a>
        </li>
        @endforeach
    </ul>
</nav>

@if($q !== '')
<p class="mb-4 text-sm text-slate-600" role="status">
    {{ trans_choice('community.search.results', $accounts->total(), ['count' => $accounts->total()]) }} <span class="font-semibold text-slate-900">{{ __('community.search.quoted', ['q' => $q]) }}</span>
</p>
@endif

@if($accounts->isEmpty())
    @include('components.empty-state', [
        'title'       => $q !== '' ? __('community.index.no_match') : $empty[0],
        'text'        => $q !== '' ? __('community.search.retry') : $empty[1],
        'actionUrl'   => $q !== '' ? route('community.index', array_filter(['tab' => $tab !== 'all' ? $tab : null])) : null,
        'actionLabel' => __('community.search.clear'),
    ])
@else
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($accounts as $account)
            @include('community.partials.card', ['account' => $account])
        @endforeach
    </div>
    {{ $accounts->links() }}
@endif
@endsection

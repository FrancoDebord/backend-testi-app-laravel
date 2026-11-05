@extends('layouts.app')
@section('title', __('community.following_page.title'))
@php
    // « Mes abonnements » : comptes suivis (docs/fonctionnalites/abonnements.md).
    // Se désabonner laisse la carte en place (bouton « Suivre ») pour pouvoir revenir sur son choix.
    $header      = __('community.following_page.title');
    $subheader   = __('community.following_page.subheader');
    $breadcrumbs = [
        ['label' => __('profile.breadcrumb.home'), 'url' => route('home')],
        ['label' => __('profile.breadcrumb.my_profile'), 'url' => route('profiles.show', auth()->id())],
        ['label' => __('community.following_page.title')],
    ];
@endphp

@section('content')
@include('community.partials.follow-tabs', ['active' => 'following'])

@if($q !== '' || $accounts->total() > 0)
<form method="GET" action="{{ route('profile.following') }}" role="search" class="mb-5 flex max-w-2xl gap-2" data-loading-inline data-loading-label="{{ __('js.app.searching') }}">
    <label for="following-q" class="sr-only">{{ __('community.following_page.search_label') }}</label>
    <div class="relative min-w-0 flex-1">
        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
        <input id="following-q" type="search" name="q" value="{{ $q }}" data-search-autocomplete="users" data-search-recent-key="following" maxlength="100" autocomplete="off"
               class="form-input pl-9" placeholder="{{ __('community.search.placeholder') }}">
    </div>
    <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass sm:hidden" aria-hidden="true"></i><span class="max-sm:sr-only">{{ __('layout.search') }}</span></button>
</form>

<p class="mb-4 text-sm text-slate-600" role="status">
    @if($q !== '')
        {{ trans_choice('community.search.results', $accounts->total(), ['count' => $accounts->total()]) }} <span class="font-semibold text-slate-900">{{ __('community.search.quoted', ['q' => $q]) }}</span>
    @else
        {!! trans_choice('community.following_page.count', $accounts->total(), ['count' => '<span class="font-semibold text-slate-900">' . e(\App\Support\Locales::number($accounts->total())) . '</span>']) !!}
    @endif
</p>
@endif

@if($accounts->isEmpty())
    @include('components.empty-state', [
        'title'       => $q !== '' ? __('community.following_page.no_match') : __('community.following_page.empty_title'),
        'text'        => $q !== '' ? __('community.search.retry') : __('community.following_page.empty_text'),
        'actionUrl'   => $q !== '' ? route('profile.following') : route('community.index'),
        'actionLabel' => $q !== '' ? __('community.search.clear') : __('community.search.discover'),
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

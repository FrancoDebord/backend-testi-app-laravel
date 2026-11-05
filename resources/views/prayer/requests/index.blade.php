@extends('layouts.app')
@section('title', __('prayer.requests.title'))
@php
    $header      = __('prayer.requests.title');
    $subheader   = __('prayer.requests.subheader');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('prayer.requests.title')],
    ];
    $tabs = ['all' => __('prayer.requests.tab_all'), 'mine' => __('prayer.requests.tab_mine')];
    $tabParam = $tab === 'mine' ? ['onglet' => 'miennes'] : [];
@endphp

@section('headerActions')
    @auth
    <a href="{{ route('prayer.requests.create') }}" class="btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>{{ __('prayer.requests.share') }}</a>
    @endauth
    <a href="{{ route('prayer.sessions.index') }}" class="btn-secondary"><i class="fa-solid fa-people-group" aria-hidden="true"></i>{{ __('layout.nav.prayer_sessions') }}</a>
@endsection

@section('content')
<aside class="card-insight mb-5 flex gap-4 p-5" aria-label="{{ __('prayer.requests.verse_label') }}">
    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-sun-500 shadow-soft" aria-hidden="true"><i class="fa-solid fa-book-bible"></i></span>
    <div class="min-w-0">
        <blockquote class="text-[15px] leading-relaxed break-words text-slate-900 italic">{{ __('prayer_focus.modal.verse_text') }}</blockquote>
        <p class="mt-1 text-sm font-semibold text-primary-700">{{ __('prayer_focus.modal.verse_ref') }}</p>
    </div>
</aside>

@auth
<nav aria-label="{{ __('prayer.requests.tabs_label') }}" class="mb-4 border-b border-slate-200">
    <ul class="-mb-px flex gap-5 overflow-x-auto">
        @foreach($tabs as $key => $label)
        @php $active = $tab === $key; @endphp
        <li>
            <a href="{{ route('prayer.requests.index', array_filter(['onglet' => $key === 'mine' ? 'miennes' : null, 'statut' => $status ? 'exaucees' : null])) }}"
               class="{{ $active ? 'tab-active' : 'tab' }}" @if($active) aria-current="page" @endif>{{ $label }}</a>
        </li>
        @endforeach
    </ul>
</nav>
@endauth

<div class="mb-5 flex gap-2" role="group" aria-label="{{ __('prayer.requests.status_label') }}">
    <a href="{{ route('prayer.requests.index', $tabParam) }}" class="{{ !$status ? 'chip-active' : 'chip' }}" @if(!$status) aria-current="true" @endif>{{ __('prayer.requests.filter_all') }}</a>
    <a href="{{ route('prayer.requests.index', array_merge($tabParam, ['statut' => 'exaucees'])) }}" class="{{ $status ? 'chip-active' : 'chip' }}" @if($status) aria-current="true" @endif>
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ __('prayer.requests.filter_answered') }}
    </a>
</div>

@if($items->isEmpty())
    @include('components.empty-state', [
        'title'         => $tab === 'mine' ? __('prayer.requests.empty_mine') : ($status ? __('prayer.requests.empty_answered') : __('prayer.requests.empty_all')),
        'text'          => __('prayer.requests.empty_text'),
        'actionUrl'     => Auth::check() ? route('prayer.requests.create') : route('login'),
        'actionLabel'   => Auth::check() ? __('prayer.requests.share') : __('prayer.requests.empty_login'),
        'actionPrimary' => true,
    ])
@else
    <div class="mb-8 grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-5">
        @foreach($items as $prayerRequest)
            @include('prayer.partials.feed-card', ['request' => $prayerRequest])
        @endforeach
    </div>
    {{ $items->links() }}
@endif
@endsection

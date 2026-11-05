@extends('layouts.app')
@section('title', __('prayer.sessions.title'))
@php
    $header      = __('prayer.sessions.title');
    $subheader   = __('prayer.sessions.subheader');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('prayer.sessions.title')],
    ];
    $tabs = [
        'upcoming' => __('prayer.sessions.tabs.upcoming'),
        'joined'   => __('prayer.sessions.tabs.joined'),
        'mine'     => __('prayer.sessions.tabs.mine'),
        'past'     => __('prayer.sessions.tabs.past'),
    ];
    if (!Auth::check()) unset($tabs['joined'], $tabs['mine']);
@endphp

@section('headerActions')
    @auth
    <a href="{{ route('prayer.sessions.create') }}" class="btn-primary"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>{{ __('prayer.sessions.schedule') }}</a>
    @endauth
    <a href="{{ route('prayer.requests.index') }}" class="btn-secondary"><i class="fa-solid fa-hands-praying" aria-hidden="true"></i>{{ __('layout.nav.prayer_requests') }}</a>
@endsection

@section('content')
<nav aria-label="{{ __('prayer.sessions.tabs_label') }}" class="mb-5 border-b border-slate-200">
    <ul class="-mb-px flex gap-5 overflow-x-auto">
        @foreach($tabs as $key => $label)
        @php $active = $tab === $key; @endphp
        <li>
            <a href="{{ route('prayer.sessions.index', $key === 'upcoming' ? [] : ['onglet' => $key]) }}"
               class="{{ $active ? 'tab-active' : 'tab' }}" @if($active) aria-current="page" @endif>{{ $label }}</a>
        </li>
        @endforeach
    </ul>
</nav>

@if($items->isEmpty())
    @include('components.empty-state', [
        'title'         => match ($tab) {
            'joined' => __('prayer.sessions.empty_joined'),
            'mine'   => __('prayer.sessions.empty_mine'),
            'past'   => __('prayer.sessions.empty_past'),
            default  => __('prayer.sessions.empty_upcoming'),
        },
        'text'          => __('prayer.sessions.empty_text'),
        'actionUrl'     => Auth::check() ? route('prayer.sessions.create') : null,
        'actionLabel'   => __('prayer.sessions.schedule'),
        'actionPrimary' => true,
    ])
@else
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 xl:gap-5">
        @foreach($items as $session)
            @include('prayer.partials.session-card', ['session' => $session])
        @endforeach
    </div>
    {{ $items->links() }}
@endif
@endsection

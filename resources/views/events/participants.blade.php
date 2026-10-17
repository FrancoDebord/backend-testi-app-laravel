@extends('layouts.app')
@section('title', __('events.participants.title'))
@php
    $going       = $status === \App\Models\EventParticipation::GOING;
    $header      = __('events.participants.title');
    $subheader   = $event->title;
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.events'), 'url' => route('events.index')],
        ['label' => Str::limit($event->title, 40), 'url' => route('events.show', $event->id)],
        ['label' => __('events.participants.title')],
    ];
@endphp

@section('headerActions')
    <a href="{{ route('events.show', $event->id) }}" class="btn-secondary"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>{{ __('events.participants.back') }}</a>
@endsection

@section('content')
<nav aria-label="{{ __('events.participants.answers') }}" class="mb-4 border-b border-slate-200">
    <ul class="-mb-px flex gap-5 overflow-x-auto">
        <li><a href="{{ route('events.participants', $event->id) }}" class="{{ $going ? 'tab-active' : 'tab' }}" @if($going) aria-current="page" @endif>{{ __('events.going') }} <span class="text-xs text-slate-500">{{ $event->going_count }}</span></a></li>
        <li><a href="{{ route('events.participants', [$event->id, 'statut' => 'non']) }}" class="{{ !$going ? 'tab-active' : 'tab' }}" @if(!$going) aria-current="page" @endif>{{ __('events.not_going') }} <span class="text-xs text-slate-500">{{ $event->not_going_count }}</span></a></li>
    </ul>
</nav>

@if($items->isEmpty())
    @include('components.empty-state', [
        'title' => __('events.participants.empty_title'),
        'text'  => $going ? __('events.participants.empty_going') : __('events.participants.empty_not_going'),
    ])
@else
<ul class="card mb-6 divide-y divide-slate-100">
    @foreach($items as $participation)
    <li class="flex items-center gap-3 px-4 py-3 sm:px-5">
        @include('components.avatar', ['user' => $participation->user, 'size' => 'sm'])
        <div class="min-w-0 flex-1">
            @if($participation->user)
            <a href="{{ route('profiles.show', $participation->user_id) }}" class="flex min-w-0 items-center gap-1.5 text-sm font-semibold text-slate-900 hover:text-primary-600 hover:underline">
                <span class="truncate">{{ $participation->user->display_name }}</span>
                @include('components.verified-badge', ['user' => $participation->user])
            </a>
            @else
            <p class="text-sm text-slate-500">{{ __('events.deleted_account') }}</p>
            @endif
        </div>
        <time class="shrink-0 text-xs text-slate-500" datetime="{{ $participation->updated_at?->toIso8601String() }}">{{ $participation->updated_at?->diffForHumans() }}</time>
    </li>
    @endforeach
</ul>
{{ $items->links() }}
@endif
@endsection

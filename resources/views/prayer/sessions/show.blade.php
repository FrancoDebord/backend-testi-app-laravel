@extends('layouts.app')
@php
    $viewer      = Auth::user();
    $phase       = $session->phase();
    $isHost      = $session->isHost($viewer);
    $registered  = $session->isRegistered($viewer);
    $header      = $session->title;
    $subheader   = __('prayer.sessions.show.subheader', ['date' => $session->starts_at->translatedFormat('l j F Y, H:i'), 'duration' => $session->duration_minutes, 'host' => $session->host?->display_name]);
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.prayer_sessions'), 'url' => route('prayer.sessions.index')],
        ['label' => Str::limit($session->title, 40)],
    ];
    $badge = match ($phase) {
        'live'      => 'badge-live',
        'ended'     => 'badge-neutral',
        'cancelled' => 'badge-rejected',
        default     => 'badge-blue',
    };
@endphp
@section('title', $header)

@section('headerActions')
    @if($session->canBeEditedBy($viewer))
    <a href="{{ route('prayer.sessions.edit', $session->id) }}" class="btn-secondary"><i class="fa-solid fa-pen" aria-hidden="true"></i>{{ __('prayer.common.edit') }}</a>
    @endif
    @if($session->canBeDeletedBy($viewer))
    <button type="button" class="btn-ghost text-error-700"
            onclick="openConfirmModal('session-delete-form', @js(__('prayer.sessions.show.delete_confirm')), @js(__('prayer.sessions.show.delete_title')), @js(__('prayer.common.delete')), 'fa-trash')">
        <i class="fa-solid fa-trash" aria-hidden="true"></i><span class="sr-only sm:not-sr-only">{{ __('prayer.common.delete') }}</span>
    </button>
    @endif
@endsection

@section('content')
@if($session->canBeDeletedBy($viewer))
<form id="session-delete-form" method="POST" action="{{ route('prayer.sessions.destroy', $session->id) }}" data-loading-label="{{ __('prayer.common.deleting') }}" hidden>
    @csrf @method('DELETE')
</form>
@endif

<div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <div class="min-w-0 space-y-6">
        <section class="card p-5 sm:p-6" aria-labelledby="session-about">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="session-about" class="card-title mr-auto">{{ __('prayer.sessions.show.about') }}</h2>
                <span class="{{ $badge }}">{{ $session->phaseLabel() }}</span>
                <span class="badge-neutral">{{ \App\Models\PrayerSession::visibilityLabels()[$session->visibility] }}</span>
            </div>
            @if(filled($session->description))
            <p class="mt-4 text-[15px] leading-relaxed break-words whitespace-pre-line text-slate-800">{{ \App\Support\Linkify::html($session->description) }}</p>
            <x-link-preview :text="$session->description" />
            @endif
            @if(!empty($session->topics))
            <h3 class="mt-5 text-sm font-semibold text-slate-900">{{ __('prayer.sessions.show.topics') }}</h3>
            <ul class="mt-2 space-y-1.5">
                @foreach($session->topics as $topic)
                <li class="flex gap-2 text-sm break-words text-slate-700"><i class="fa-solid fa-hands-praying mt-1 text-primary-600" aria-hidden="true"></i><span>{{ $topic }}</span></li>
                @endforeach
            </ul>
            @endif
            @if($session->event)
            <a href="{{ route('events.show', $session->event_id) }}" class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-primary-700 hover:underline">
                <i class="fa-regular fa-calendar" aria-hidden="true"></i>{{ $session->event->title }}
            </a>
            @endif
        </section>

        @if($isHost || ($viewer?->canModerate()))
        <section class="card p-5 sm:p-6" aria-labelledby="participants-title">
            <h2 id="participants-title" class="card-title">{{ __('prayer.sessions.show.participants') }} <span class="tabular-nums text-slate-500">({{ $session->participant_count }})</span></h2>
            @if($participants->isEmpty())
            <p class="mt-2 text-sm text-slate-500">{{ __('prayer.sessions.show.no_participants') }}</p>
            @else
            <ul class="mt-3 flex flex-wrap gap-3">
                @foreach($participants as $participant)
                <li class="flex items-center gap-2 text-sm text-slate-700">
                    @include('components.avatar', ['user' => $participant->user, 'size' => 'xs'])
                    {{ $participant->user?->display_name }}
                </li>
                @endforeach
            </ul>
            @endif
        </section>
        @endif
    </div>

    <aside class="min-w-0 space-y-6">
        <section class="card p-5" aria-labelledby="room-title">
            <h2 id="room-title" class="card-title">{{ __('prayer.sessions.show.room') }}</h2>
            <p class="mt-1 text-sm text-slate-600">
                <span class="font-semibold text-slate-900">{{ $session->participant_count }}</span> {{ trans_choice('prayer.sessions.card.registered_count', $session->participant_count) }}
            </p>

            @if($phase === 'cancelled')
                <p class="mt-3 text-sm text-slate-500">{{ __('prayer.sessions.show.cancelled') }}</p>
            @elseif($isHost)
                @if(!$configured)
                    <p class="alert-warning mt-3 text-sm"><i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i><span>{{ __('prayer.sessions.show.not_configured') }}</span></p>
                @elseif($session->canBeStartedBy($viewer))
                    <form method="POST" action="{{ route('prayer.sessions.open', $session->id) }}" class="mt-4" data-loading-label="{{ __('prayer.sessions.show.opening') }}">
                        @csrf
                        <button type="submit" class="btn-cta w-full"><i class="fa-solid fa-tower-broadcast" aria-hidden="true"></i>{{ $session->activeLive() ? __('prayer.sessions.show.room_return') : __('prayer.sessions.show.room_open') }}</button>
                    </form>
                    <p class="form-hint mt-2">{{ __('prayer.sessions.show.host_hint') }}</p>
                @elseif($phase === 'upcoming')
                    <p class="mt-3 text-sm text-slate-500">{{ __('prayer.sessions.show.open_from', ['date' => $session->starts_at->copy()->subMinutes(\App\Models\PrayerSession::OPEN_BEFORE_MINUTES)->translatedFormat(__('prayer.sessions.show.open_from_format'))]) }}</p>
                @else
                    <p class="mt-3 text-sm text-slate-500">{{ __('prayer.sessions.show.ended') }}</p>
                @endif
                @if($phase !== 'ended')
                <form method="POST" action="{{ route('prayer.sessions.cancel', $session->id) }}" class="mt-2 text-center" data-loading-label="{{ __('prayer.sessions.show.cancelling') }}">
                    @csrf
                    <button type="submit" class="btn-ghost btn-sm">{{ __('prayer.sessions.show.cancel') }}</button>
                </form>
                @endif
            @else
                @if($live)
                    <a href="{{ route('prayer.sessions.room', $session->id) }}" class="btn-cta mt-4 w-full"><i class="fa-solid fa-door-open" aria-hidden="true"></i>{{ __('prayer.sessions.show.join_room') }}</a>
                    <p class="form-hint mt-2">{{ __('prayer.sessions.show.room_hint') }}</p>
                @elseif($phase === 'ended')
                    <p class="mt-3 text-sm text-slate-500">{{ __('prayer.sessions.show.ended') }}</p>
                @else
                    <p class="mt-3 text-sm text-slate-500">{{ __('prayer.sessions.show.opens_later') }} {{ $registered ? __('prayer.sessions.show.will_notify') : '' }}</p>
                @endif

                @if($phase !== 'ended')
                    @if(!$viewer)
                    <a href="{{ route('login') }}" class="btn-secondary mt-3 w-full"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>{{ __('prayer.sessions.show.login_to_join') }}</a>
                    @elseif($registered)
                    <form method="POST" action="{{ route('prayer.sessions.leave', $session->id) }}" class="mt-3" data-loading-label="{{ __('prayer.common.updating') }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-primary w-full" aria-pressed="true"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ __('prayer.sessions.show.i_will_be_there') }}</button>
                    </form>
                    <p class="form-hint mt-1 text-center">{{ __('prayer.sessions.show.leave_hint') }}</p>
                    @else
                    <form method="POST" action="{{ route('prayer.sessions.join', $session->id) }}" class="mt-3" data-loading-label="{{ __('prayer.sessions.show.registering') }}">
                        @csrf
                        <button type="submit" class="btn-secondary w-full"><i class="fa-regular fa-bell" aria-hidden="true"></i>{{ __('prayer.sessions.show.i_will_be_there') }}</button>
                    </form>
                    @endif
                @endif
            @endif

            @if($viewer?->isAdmin() && !$isHost && $live)
            <p class="form-hint mt-3">{{ __('prayer.sessions.show.admin_hint', ['button' => __('lives.show.end')]) }}</p>
            @endif
        </section>
    </aside>
</div>
@endsection

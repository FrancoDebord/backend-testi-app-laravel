@extends('layouts.app')
@section('title', $live->title)
@php
    $header      = $live->title;
    $subheader   = __('lives.show.by', ['name' => $live->host->display_name]);
    $breadcrumbs = [
        ['label' => __('lives.home'), 'url' => route('home')],
        ['label' => __('lives.lives'), 'url' => route('lives.index')],
        ['label' => Str::limit($live->title, 40)],
    ];
    $canModerate = $live->canBeModeratedBy(Auth::user());
    $reactionDefs = [
        ['like', 'fa-thumbs-up', __('lives.show.react_like')],
        ['pray', 'fa-hands-praying', __('lives.show.react_pray')],
        ['amen', 'fa-hands-clapping', __('lives.show.react_amen')],
        ['worship', 'fa-hands', __('lives.show.react_worship')],
        ['fire', 'fa-fire', __('lives.show.react_fire')],
    ];
@endphp

{{-- Couper le direct : le diffuseur ou un administrateur (jamais un modérateur). --}}
@if($live->canBeEndedBy(auth()->user()) && $live->isActive())
@section('headerActions')
    <form id="live-end-form" data-no-loading>
        <button type="button" class="btn-secondary text-red-700"
                onclick="openConfirmModal('live-end-form', {{ Js::from(__('lives.show.end_confirm')) }}, {{ Js::from(__('lives.show.end')) }}, {{ Js::from(__('lives.show.end')) }}, 'fa-stop')">
            <i class="fa-solid fa-stop"></i>{{ __('lives.show.end') }}
        </button>
    </form>
@endsection
@endif

@section('content')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]" data-live-viewer>

    <div class="min-w-0 space-y-4">
        <div class="relative aspect-video overflow-hidden rounded-xl bg-slate-900">
            <video class="h-full w-full object-contain" autoplay playsinline data-live-video></video>
            <div class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-6 text-center text-sm text-slate-200" data-live-overlay>
                @if(!$configured)
                    {{ __('lives.show.unavailable') }}
                @elseif($live->status->value === 'ended')
                    {{ __('lives.show.ended') }}
                @else
                    {{ __('lives.show.connecting') }}
                @endif
            </div>
            <div class="absolute top-3 left-3 flex flex-wrap items-center gap-2">
                <span class="{{ $live->status->badgeClass() }}" data-live-status>{{ $live->status->label() }}</span>
                <span class="rounded-full bg-slate-900/70 px-2 py-0.5 text-xs font-medium text-white" data-live-timer hidden>00:00</span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-900/70 px-2 py-0.5 text-xs font-medium text-white" data-live-rec title="{{ __('lives.rec_title') }}" @if($live->recording_status !== 'recording') hidden @endif><span class="h-2 w-2 rounded-full bg-red-500"></span>REC</span>
            </div>
            {{-- Qui regarde : liste visible de tous (lives/partials/viewers-modal) --}}
            <button type="button" class="absolute top-3 right-3 rounded-full bg-slate-900/70 px-2.5 py-1 text-xs font-medium text-white hover:bg-slate-900/90 focus-visible:outline-2 focus-visible:outline-white"
                    data-live-viewers-open aria-haspopup="dialog" title="{{ __('lives.see_viewers') }}">
                <i class="fa-regular fa-eye mr-1" aria-hidden="true"></i><span data-live-viewers>0</span><span class="sr-only">{{ __('lives.viewers_sr') }}</span>
            </button>
            @include('lives.partials.stage-pip')
            <button type="button" class="btn-secondary btn-sm absolute bottom-3 left-3" data-live-unmute hidden>
                <i class="fa-solid fa-volume-high"></i>{{ __('lives.show.unmute') }}
            </button>
        </div>

        {{-- Intervenant à l'antenne (la personne elle-même) --}}
        <div class="card flex flex-wrap items-center gap-2 p-3" data-stage-self hidden>
            <span class="badge-live">{{ __('lives.show.on_stage') }}</span>
            <button type="button" class="chip-active" data-stage-self-toggle="microphone" aria-pressed="true" title="{{ __('lives.show.mute_my_mic') }}">
                <i class="fa-solid fa-microphone" aria-hidden="true"></i><span class="hidden sm:inline" aria-hidden="true">{{ __('lives.microphone') }}</span>
            </button>
            <button type="button" class="chip" data-stage-self-toggle="camera" aria-pressed="false" title="{{ __('lives.show.enable_my_camera') }}">
                <i class="fa-solid fa-video-slash" aria-hidden="true"></i><span class="hidden sm:inline" aria-hidden="true">{{ __('lives.camera') }}</span>
            </button>
            <button type="button" class="btn-secondary btn-sm ml-auto" data-stage-self-leave><i class="fa-solid fa-phone-slash" aria-hidden="true"></i>{{ __('lives.show.leave_stage') }}</button>
        </div>

        <div class="card flex flex-wrap items-center gap-2 p-3" aria-label="{{ __('lives.show.reactions') }}">
            @foreach($reactionDefs as [$type, $icon, $label])
            <button type="button" class="chip" data-live-react="{{ $type }}" title="{{ $label }}" @disabled(!$live->isActive())>
                <i class="fa-solid {{ $icon }}"></i><span class="hidden sm:inline">{{ $label }}</span>
                <span class="text-xs text-slate-500" data-live-react-count="{{ $type }}">{{ $live->{$type . '_count'} }}</span>
            </button>
            @endforeach
        </div>

        <section class="card p-5">
            <div class="flex items-center gap-3">
                @include('components.avatar', ['user' => $live->host, 'size' => 'md'])
                <div class="min-w-0">
                    <p class="truncate font-semibold text-slate-900">{{ $live->host->display_name }}</p>
                    <p class="text-xs text-slate-500">{{ $live->host->role->label() }}@if($live->category_slug) · {{ $live->category_slug }}@endif</p>
                </div>
            </div>
            @if($live->event?->isVisibleTo(Auth::user()))
            <p class="mt-3 text-sm text-slate-700">
                <i class="fa-solid fa-calendar-days mr-1 text-primary-600" aria-hidden="true"></i>{{ __('lives.show.event_live') }}
                <a href="{{ route('events.show', $live->event_id) }}" class="font-semibold text-primary-600 hover:underline">{{ $live->event->title }}</a>
            </p>
            @endif
            @if($live->description)
            <p class="mt-3 text-sm break-words whitespace-pre-line text-slate-700">{{ $live->description }}</p>
            @endif

            @if($live->record)
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <p class="text-xs text-slate-500">
                    <i class="fa-regular fa-circle-dot mr-1 text-slate-400"></i>
                    @if($live->isActive())
                        {{ __('lives.show.recorded_active') }}
                    @else
                        {{ $live->recordingLabel() }}
                    @endif
                </p>
                @if($live->testimony && $live->testimony->status->value === 'approved')
                    <a href="{{ route('testimonies.show', $live->testimony_id) }}" class="btn-secondary btn-sm"><i class="fa-solid fa-play"></i>{{ __('lives.show.see_replay') }}</a>
                @elseif($live->testimony && $live->canBeModeratedBy(Auth::user()))
                    <a href="{{ route('moderation.show', $live->testimony_id) }}" class="btn-secondary btn-sm"><i class="fa-solid fa-shield-halved"></i>{{ __('lives.show.review_video') }}</a>
                @endif
            </div>
            @endif
        </section>
    </div>

    <div class="min-w-0 space-y-6">
        @include('lives.partials.stage')
        @include('lives.partials.comments')
    </div>
</div>

@include('lives.partials.viewers-modal')
@include('lives.partials.config', ['mode' => 'viewer'])
@endsection

@push('scripts')
@vite('resources/js/live.js')
@endpush

@extends('layouts.app')
@section('title', __('lives.studio.title', ['title' => $live->title]))
@php
    $header      = $live->title;
    $subheader   = __('lives.studio.subheader');
    $breadcrumbs = [
        ['label' => __('lives.home'), 'url' => route('home')],
        ['label' => __('lives.lives'), 'url' => route('lives.index')],
        ['label' => __('lives.studio.breadcrumb')],
    ];
@endphp

@section('headerActions')
    <a href="{{ route('lives.show', $live->id) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm"><i class="fa-solid fa-arrow-up-right-from-square"></i>{{ __('lives.studio.viewer_page') }}</a>
@endsection

@section('content')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]" data-live-studio>

    <div class="min-w-0 space-y-4">
        {{-- Vidéo --}}
        <div class="relative aspect-[3/4] overflow-hidden rounded-xl bg-slate-900 sm:aspect-video">
            <video class="h-full w-full object-cover" autoplay muted playsinline data-live-video></video>
            <div class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-slate-200" data-live-overlay>
                {{ __('lives.studio.preparing') }}
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
        </div>

        @if($live->usesExternalCamera())
        {{-- Caméra IP / encodeur : informations de connexion (visibles du seul diffuseur) --}}
        <section class="card p-4 sm:p-5" aria-labelledby="camera-setup-title" data-live-camera-panel>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="camera-setup-title" class="card-title flex items-center gap-2"><i class="fa-solid fa-video text-primary-600" aria-hidden="true"></i>{{ __('lives.studio.ip_camera') }}</h2>
                <span class="badge-pending" data-live-camera-state>{{ __('lives.studio.waiting_stream') }}</span>
            </div>
            @if($live->source === 'rtmp')
            <p class="mt-1 text-sm text-slate-600">{!! __('lives.studio.rtmp_intro') !!}</p>
            <dl class="mt-3 space-y-3">
                @foreach([[__('lives.studio.server_url'), $live->ingress_url, 'camera-url'], [__('lives.studio.stream_key'), $live->ingress_stream_key, 'camera-key']] as [$label, $value, $fieldId])
                <div>
                    <dt class="form-label" id="{{ $fieldId }}-label">{{ $label }}</dt>
                    <dd class="flex gap-2">
                        <input id="{{ $fieldId }}" type="{{ $fieldId === 'camera-key' ? 'password' : 'text' }}" value="{{ $value }}" readonly class="form-input font-mono text-xs" aria-labelledby="{{ $fieldId }}-label">
                        @if($fieldId === 'camera-key')
                        <button type="button" class="btn-secondary btn-sm shrink-0" data-reveal="{{ $fieldId }}" aria-label="{{ __('lives.studio.show_key') }}"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                        @endif
                        <button type="button" class="btn-soft btn-sm shrink-0" data-copy="{{ $fieldId }}"><i class="fa-regular fa-copy" aria-hidden="true"></i>{{ __('lives.studio.copy') }}</button>
                    </dd>
                </div>
                @endforeach
            </dl>
            <p class="form-hint mt-2">{{ __('lives.studio.key_hint') }}</p>
            @else
            <p class="mt-1 text-sm text-slate-600">{{ __('lives.studio.url_intro') }}</p>
            @endif
            <p class="mt-3 text-xs text-slate-500">{!! __('lives.studio.check_preview', ['action' => e(__('lives.studio.go_live'))]) !!}</p>
        </section>
        @endif

        {{-- Commandes --}}
        <div class="card flex flex-wrap items-center gap-2 p-3">
            <button type="button" class="btn-primary" data-live-start disabled>
                <i class="fa-solid fa-tower-broadcast"></i><span>{{ __('lives.studio.go_live') }}</span>
            </button>
            <button type="button" class="chip-active" data-live-toggle="microphone" aria-pressed="true" title="{{ __('lives.studio.mute_mic') }}">
                <i class="fa-solid fa-microphone"></i><span class="hidden sm:inline">{{ __('lives.microphone') }}</span>
            </button>
            <button type="button" class="chip-active" data-live-toggle="camera" aria-pressed="true" title="{{ __('lives.studio.mute_camera') }}">
                <i class="fa-solid fa-video"></i><span class="hidden sm:inline">{{ __('lives.camera') }}</span>
            </button>
            <button type="button" class="chip" data-live-switch-camera title="{{ __('lives.studio.switch_camera') }}">
                <i class="fa-solid fa-camera-rotate"></i><span class="hidden sm:inline">{{ __('lives.studio.flip') }}</span>
            </button>
            <form id="live-end-form" class="ml-auto" data-no-loading>
                <button type="button" class="btn-secondary text-red-700"
                        onclick="openConfirmModal('live-end-form', {{ Js::from(__('lives.studio.end_confirm')) }}, {{ Js::from(__('lives.studio.end_title')) }}, {{ Js::from(__('lives.studio.end')) }}, 'fa-stop')">
                    <i class="fa-solid fa-stop"></i>{{ __('lives.studio.end') }}
                </button>
            </form>
        </div>

        {{-- État technique --}}
        <dl class="card grid grid-cols-1 divide-y divide-slate-100 text-sm sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            <div class="flex justify-between gap-4 px-4 py-3 sm:block">
                <dt class="text-slate-500">{{ __('lives.studio.connection') }}</dt>
                <dd class="flex items-center gap-2 text-slate-900"><span class="h-2 w-2 rounded-full bg-slate-400" data-live-conn-dot></span><span data-live-conn>{{ __('lives.studio.not_connected') }}</span></dd>
            </div>
            <div class="flex justify-between gap-4 px-4 py-3 sm:block">
                <dt class="text-slate-500">{{ __('lives.studio.network') }}</dt>
                <dd class="flex items-center gap-2 text-slate-900"><span class="h-2 w-2 rounded-full bg-slate-400" data-live-quality-dot></span><span data-live-quality>—</span></dd>
            </div>
            <div class="flex justify-between gap-4 px-4 py-3 sm:block">
                <dt class="text-slate-500">{{ __('lives.studio.reactions') }}</dt>
                <dd class="text-slate-900" data-live-reaction-summary>—</dd>
            </div>
        </dl>

        <div class="alert-info">
            <i class="fa-solid fa-circle-info mt-0.5 text-slate-400"></i>
            <p>{{ __('lives.studio.stay', ['minutes' => intdiv(config('livekit.empty_timeout'), 60)]) }}</p>
        </div>
    </div>

    <div class="min-w-0 space-y-6">
        @include('lives.partials.stage')
        @include('lives.partials.comments')
    </div>
</div>

@include('lives.partials.viewers-modal')
@include('lives.partials.config', ['mode' => 'studio'])
@endsection

@push('scripts')
@vite('resources/js/live.js')
@endpush

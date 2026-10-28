@extends('layouts.app')
@section('title', $live->title)
{{-- Direct public indexable (docs/fonctionnalites/referencement.md) --}}
@section('robots', $live->isVisibleTo(null) ? \App\Support\Seo::INDEX : 'noindex, nofollow')
@section('page_title', \App\Support\Seo::title((string) $live->title, $live->isActive() ? 'Direct' : 'Replay'))

{{-- Aperçu de partage (WhatsApp, Facebook…) : docs/fonctionnalites/apercu-de-partage.md --}}
@push('meta')
    <x-share-meta :meta="\App\Support\ShareMeta::live($live)" />
    @if($live->isVisibleTo(null))
    {{ \App\Support\StructuredData::script(\App\Support\StructuredData::live($live)) }}
    @endif
@endpush
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
        @if($replay ?? null)
        {{-- Direct terminé : bilan comme l'écran de fin de l'application (lives/partials/ended-summary) --}}
        @include('lives.partials.ended-summary')
        @else
        @if($live->isYoutube())
        {{-- Direct YouTube : lecteur YouTube, aucune connexion LiveKit (live.js, mode « youtube »). docs/fonctionnalites/lives.md --}}
        <div class="space-y-2" data-live-youtube>
            @include('lives.partials.youtube-player', ['youtube' => $live->youtube()])
            <div class="flex flex-wrap items-center gap-2">
                <span class="{{ $live->status->badgeClass() }}" data-live-status>{{ $live->status->label() }}</span>
                <span class="rounded-full bg-slate-900/80 px-2 py-0.5 text-xs font-medium text-white" data-live-timer hidden>00:00</span>
                {{-- Qui regarde : spectateurs comptés par le signal de présence (heartbeat) --}}
                <button type="button" class="rounded-full bg-slate-900/80 px-2.5 py-1 text-xs font-medium text-white hover:bg-slate-900 focus-visible:outline-2 focus-visible:outline-primary-500"
                        data-live-viewers-open aria-haspopup="dialog" title="{{ __('lives.see_viewers') }}">
                    <i class="fa-regular fa-eye mr-1" aria-hidden="true"></i><span data-live-viewers>0</span><span class="sr-only">{{ __('lives.viewers_sr') }}</span>
                </button>
                <span class="ml-auto flex items-center gap-2 text-xs text-slate-500">
                    <i class="fa-brands fa-youtube text-error-500" aria-hidden="true"></i>{{ __('lives.show.youtube_hosted') }} ·
                    <a href="{{ $live->youtube()['url'] ?? '#' }}" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:underline" data-live-youtube-open>{{ __('lives.show.youtube_open') }}</a>
                </span>
            </div>
        </div>
        @else
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
        @endif

        <div class="card flex flex-wrap items-center gap-2 p-3" aria-label="{{ __('lives.show.reactions') }}">
            @foreach($reactionDefs as [$type, $icon, $label])
            <button type="button" class="chip" data-live-react="{{ $type }}" title="{{ $label }}" @disabled(!$live->isActive())>
                <i class="fa-solid {{ $icon }}"></i><span class="hidden sm:inline">{{ $label }}</span>
                <span class="text-xs text-slate-500" data-live-react-count="{{ $type }}">{{ $live->{$type . '_count'} }}</span>
            </button>
            @endforeach
        </div>
        @endif

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
                @if($replay ?? null)
                    {{-- Boutons « Voir la rediffusion » / « Relire la vidéo » : dans le bilan ci-dessus. --}}
                @elseif($live->testimony && $live->testimony->status->value === 'approved')
                    <a href="{{ route('testimonies.show', $live->testimony_id) }}" class="btn-secondary btn-sm"><i class="fa-solid fa-play"></i>{{ __('lives.show.see_replay') }}</a>
                @elseif($live->testimony && $live->canBeModeratedBy(Auth::user()))
                    <a href="{{ route('moderation.show', $live->testimony_id) }}" class="btn-secondary btn-sm"><i class="fa-solid fa-shield-halved"></i>{{ __('lives.show.review_video') }}</a>
                @endif
            </div>
            @endif
        </section>

        {{-- Direct terminé : témoignages publiés sur ce direct (docs/fonctionnalites/lives.md) --}}
        @if($replay ?? null)
        <section aria-labelledby="live-testimonies-title" data-live-testimonies>
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 id="live-testimonies-title" class="text-base font-semibold text-slate-900">{{ trans_choice('lives_replay.testimonies.count', $replay['testimoniesCount'], ['count' => $replay['testimoniesCount']]) }}</h2>
            </div>
            @if($replay['testimonies']->isEmpty())
                <p class="card p-5 text-sm text-slate-500">{{ __('lives_replay.testimonies.empty') }}</p>
            @else
                @include('components.testimony-list', ['items' => $replay['testimonies'], 'routeName' => 'testimonies.show', 'gridClass' => 'grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3'])
            @endif
        </section>
        @endif
    </div>

    <div class="min-w-0 space-y-6">
        @if($replay ?? null)
            {{-- Revoir la discussion (rendu serveur, sans temps réel) ; « Témoigner sur ce direct » est dans le bilan. --}}
            @include('lives.partials.replay')
        @else
            {{-- Direct YouTube : pas d'intervenants (aucune salle LiveKit) --}}
            @unless($live->isYoutube())
            @include('lives.partials.stage')
            @endunless
            @include('lives.partials.comments')
        @endif
    </div>
</div>

@unless($replay ?? null)
@include('lives.partials.viewers-modal')
@endunless
@include('lives.partials.config', ['mode' => ($replay ?? null) ? 'replay' : ($live->isYoutube() ? 'youtube' : 'viewer')])
@endsection

@push('scripts')
@vite('resources/js/live.js')
@endpush

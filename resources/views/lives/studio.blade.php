@extends('layouts.app')
@section('title', 'Studio — ' . $live->title)
@php
    $header      = $live->title;
    $subheader   = 'Studio de diffusion · seul vous voyez cette page.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Directs', 'url' => route('lives.index')],
        ['label' => 'Studio'],
    ];
@endphp

@section('headerActions')
    <a href="{{ route('lives.show', $live->id) }}" target="_blank" rel="noopener" class="btn-ghost btn-sm"><i class="fa-solid fa-arrow-up-right-from-square"></i>Page des spectateurs</a>
@endsection

@section('content')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]" data-live-studio>

    <div class="min-w-0 space-y-4">
        {{-- Vidéo --}}
        <div class="relative aspect-[3/4] overflow-hidden rounded-xl bg-slate-900 sm:aspect-video">
            <video class="h-full w-full object-cover" autoplay muted playsinline data-live-video></video>
            <div class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-slate-200" data-live-overlay>
                Préparation de la caméra…
            </div>
            <div class="absolute top-3 left-3 flex flex-wrap items-center gap-2">
                <span class="{{ $live->status->badgeClass() }}" data-live-status>{{ $live->status->label() }}</span>
                <span class="rounded-full bg-slate-900/70 px-2 py-0.5 text-xs font-medium text-white" data-live-timer hidden>00:00</span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-900/70 px-2 py-0.5 text-xs font-medium text-white" data-live-rec title="Ce direct est enregistré" @if($live->recording_status !== 'recording') hidden @endif><span class="h-2 w-2 rounded-full bg-red-500"></span>REC</span>
            </div>
            {{-- Qui regarde : liste visible de tous (lives/partials/viewers-modal) --}}
            <button type="button" class="absolute top-3 right-3 rounded-full bg-slate-900/70 px-2.5 py-1 text-xs font-medium text-white hover:bg-slate-900/90 focus-visible:outline-2 focus-visible:outline-white"
                    data-live-viewers-open aria-haspopup="dialog" title="Voir qui regarde">
                <i class="fa-regular fa-eye mr-1" aria-hidden="true"></i><span data-live-viewers>0</span><span class="sr-only"> spectateurs : voir la liste</span>
            </button>
            @include('lives.partials.stage-pip')
        </div>

        @if($live->usesExternalCamera())
        {{-- Caméra IP / encodeur : informations de connexion (visibles du seul diffuseur) --}}
        <section class="card p-4 sm:p-5" aria-labelledby="camera-setup-title" data-live-camera-panel>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="camera-setup-title" class="card-title flex items-center gap-2"><i class="fa-solid fa-video text-primary-600" aria-hidden="true"></i>Caméra IP</h2>
                <span class="badge-pending" data-live-camera-state>En attente du flux</span>
            </div>
            @if($live->source === 'rtmp')
            <p class="mt-1 text-sm text-slate-600">Dans les réglages de la caméra (ou d'OBS, vMix, d'un boîtier d'encodage), choisissez une diffusion <strong>RTMP</strong> et saisissez :</p>
            <dl class="mt-3 space-y-3">
                @foreach([['Adresse du serveur (URL)', $live->ingress_url, 'camera-url'], ['Clé de diffusion', $live->ingress_stream_key, 'camera-key']] as [$label, $value, $fieldId])
                <div>
                    <dt class="form-label" id="{{ $fieldId }}-label">{{ $label }}</dt>
                    <dd class="flex gap-2">
                        <input id="{{ $fieldId }}" type="{{ $fieldId === 'camera-key' ? 'password' : 'text' }}" value="{{ $value }}" readonly class="form-input font-mono text-xs" aria-labelledby="{{ $fieldId }}-label">
                        @if($fieldId === 'camera-key')
                        <button type="button" class="btn-secondary btn-sm shrink-0" data-reveal="{{ $fieldId }}" aria-label="Afficher la clé"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                        @endif
                        <button type="button" class="btn-soft btn-sm shrink-0" data-copy="{{ $fieldId }}"><i class="fa-regular fa-copy" aria-hidden="true"></i>Copier</button>
                    </dd>
                </div>
                @endforeach
            </dl>
            <p class="form-hint mt-2">Ne partagez pas la clé : elle permet de diffuser dans ce direct. Caméra seulement compatible RTSP : utilisez OBS ou un relais (voir la documentation).</p>
            @else
            <p class="mt-1 text-sm text-slate-600">Le service vidéo lit le flux de la caméra à l'adresse indiquée à la création du direct. L'aperçu apparaît ci-dessus dès que le flux est reçu.</p>
            @endif
            <p class="mt-3 text-xs text-slate-500">L'image et le son de la caméra s'affichent dans l'aperçu ; vérifiez-les puis cliquez sur <strong>Passer à l'antenne</strong>.</p>
        </section>
        @endif

        {{-- Commandes --}}
        <div class="card flex flex-wrap items-center gap-2 p-3">
            <button type="button" class="btn-primary" data-live-start disabled>
                <i class="fa-solid fa-tower-broadcast"></i><span>Passer à l'antenne</span>
            </button>
            <button type="button" class="chip-active" data-live-toggle="microphone" aria-pressed="true" title="Couper le micro">
                <i class="fa-solid fa-microphone"></i><span class="hidden sm:inline">Micro</span>
            </button>
            <button type="button" class="chip-active" data-live-toggle="camera" aria-pressed="true" title="Couper la caméra">
                <i class="fa-solid fa-video"></i><span class="hidden sm:inline">Caméra</span>
            </button>
            <button type="button" class="chip" data-live-switch-camera title="Changer de caméra">
                <i class="fa-solid fa-camera-rotate"></i><span class="hidden sm:inline">Retourner</span>
            </button>
            <form id="live-end-form" class="ml-auto" data-no-loading>
                <button type="button" class="btn-secondary text-red-700"
                        onclick="openConfirmModal('live-end-form', 'Le direct s\'arrêtera pour tous les spectateurs. Cette action est définitive.', 'Terminer le direct', 'Terminer', 'fa-stop')">
                    <i class="fa-solid fa-stop"></i>Terminer
                </button>
            </form>
        </div>

        {{-- État technique --}}
        <dl class="card grid grid-cols-1 divide-y divide-slate-100 text-sm sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            <div class="flex justify-between gap-4 px-4 py-3 sm:block">
                <dt class="text-slate-500">Connexion au service</dt>
                <dd class="flex items-center gap-2 text-slate-900"><span class="h-2 w-2 rounded-full bg-slate-400" data-live-conn-dot></span><span data-live-conn>Non connecté</span></dd>
            </div>
            <div class="flex justify-between gap-4 px-4 py-3 sm:block">
                <dt class="text-slate-500">Qualité du réseau</dt>
                <dd class="flex items-center gap-2 text-slate-900"><span class="h-2 w-2 rounded-full bg-slate-400" data-live-quality-dot></span><span data-live-quality>—</span></dd>
            </div>
            <div class="flex justify-between gap-4 px-4 py-3 sm:block">
                <dt class="text-slate-500">Réactions</dt>
                <dd class="text-slate-900" data-live-reaction-summary>—</dd>
            </div>
        </dl>

        <div class="alert-info">
            <i class="fa-solid fa-circle-info mt-0.5 text-slate-400"></i>
            <p>Restez sur cette page pendant le direct. Si la connexion est coupée, revenez-y : le direct reprend automatiquement pendant {{ intdiv(config('livekit.empty_timeout'), 60) }} minutes.</p>
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

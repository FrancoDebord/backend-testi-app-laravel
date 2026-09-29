@extends('layouts.app')
@section('title', 'Lancer un direct')
@php
    $header      = 'Lancer un direct';
    $subheader   = 'Vérifiez la caméra et le micro, puis donnez un titre à votre direct.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Directs', 'url' => route('lives.index')],
        ['label' => 'Lancer un direct'],
    ];
    $checks = [
        'secure'  => 'Connexion sécurisée (HTTPS)',
        'browser' => 'Navigateur compatible avec la vidéo en direct',
        'network' => 'Connexion Internet',
        'camera'  => 'Caméra autorisée et disponible',
        'micro'   => 'Micro autorisé et disponible',
    ];
@endphp

@section('content')
@if(!$configured)
<div class="alert-warning mb-6" role="status">
    <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
    <p>Le service vidéo n'est pas encore configuré sur ce serveur. Renseignez <code>LIVEKIT_URL</code>, <code>LIVEKIT_API_KEY</code> et <code>LIVEKIT_API_SECRET</code> dans le fichier <code>.env</code>.</p>
</div>
@endif

@if($errors->any())
<div class="alert-error mb-6" role="alert">
    <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
    <p>{{ $errors->first() }}</p>
</div>
@endif

<form method="POST" action="{{ route('lives.store') }}" id="live-create-form" data-loading-label="Création du direct…" data-online-only
      class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    @csrf

    {{-- ── Vérifications ──────────────────────────────────────────────── --}}
    <section class="card min-w-0 p-5 sm:p-6" data-live-preflight>
        <h2 class="card-title mb-4">Vérifications</h2>

        <div class="relative mb-4 aspect-video overflow-hidden rounded-lg bg-slate-900">
            <video class="h-full w-full -scale-x-100 object-cover" autoplay muted playsinline data-preflight-video></video>
            <p class="absolute inset-0 flex items-center justify-center p-4 text-center text-sm text-slate-300" data-preflight-placeholder>
                Aperçu de la caméra
            </p>
        </div>

        <div class="mb-4">
            <p class="mb-1 text-xs text-slate-500">Niveau du micro : parlez pour vérifier</p>
            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full w-0 rounded-full bg-emerald-500 transition-[width] duration-100" data-preflight-level></div>
            </div>
        </div>

        <ul class="divide-y divide-slate-100 text-sm">
            @foreach($checks as $key => $label)
            <li class="flex items-start gap-3 py-2" data-check="{{ $key }}">
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-slate-400" data-check-dot></span>
                <div class="min-w-0 flex-1">
                    <p class="text-slate-900">{{ $label }}</p>
                    <p class="text-xs text-slate-500" data-check-detail>Vérification en attente…</p>
                </div>
            </li>
            @endforeach
            <li class="flex items-start gap-3 py-2" data-check="battery" data-optional>
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-slate-400" data-check-dot></span>
                <div class="min-w-0 flex-1">
                    <p class="text-slate-900">Batterie (conseil)</p>
                    <p class="text-xs text-slate-500" data-check-detail>Information non disponible sur ce navigateur.</p>
                </div>
            </li>
        </ul>

        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" class="btn-secondary" data-preflight-run><i class="fa-solid fa-rotate-right"></i>Relancer les vérifications</button>
            <button type="button" class="btn-ghost" data-preflight-switch hidden><i class="fa-solid fa-camera-rotate"></i>Changer de caméra</button>
        </div>

        <input type="checkbox" name="checks_passed" value="1" class="sr-only" tabindex="-1" aria-hidden="true" data-required-check data-preflight-ok>

        <div class="alert-info mt-4">
            <i class="fa-solid fa-circle-info mt-0.5 text-slate-400"></i>
            <p>Pendant le direct : gardez le téléphone branché, l'écran allumé, et restez sur cette page. Une connexion Wi-Fi ou 4G stable est recommandée.</p>
        </div>
    </section>

    {{-- ── Informations ───────────────────────────────────────────────── --}}
    <section class="card min-w-0 space-y-4 p-5 sm:p-6">
        <h2 class="card-title">Le direct</h2>
        <div>
            <label for="title" class="form-label">Titre *</label>
            <input id="title" type="text" name="title" value="{{ old('title') }}" required maxlength="150" class="form-input"
                   placeholder="Ex. : Témoignage de guérison — soirée de louange">
            @error('title')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="description" class="form-label">Présentation (facultatif)</label>
            <textarea id="description" name="description" rows="3" maxlength="1000" class="form-input">{{ old('description') }}</textarea>
        </div>
        <div>
            <label for="category_slug" class="form-label">Catégorie (facultatif)</label>
            <select id="category_slug" name="category_slug" class="form-input">
                <option value="">Aucune</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->slug }}" @selected(old('category_slug') === $cat->slug)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 px-4 py-3">
            <div>
                <p class="text-sm font-medium text-slate-900">Autoriser les commentaires</p>
                <p class="text-xs text-slate-500">Vous et les modérateurs pourrez masquer un commentaire ou exclure une personne.</p>
            </div>
            @include('components.switch', ['name' => 'comments_enabled', 'checked' => (bool) old('comments_enabled', true), 'label' => 'Autoriser les commentaires', 'sendFalse' => true])
        </div>

        <div class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 px-4 py-3">
            <div>
                <p class="text-sm font-medium text-slate-900">Enregistrer le direct</p>
                @if($recordingConfigured)
                <p class="text-xs text-slate-500">La vidéo deviendra un témoignage, publié après relecture par la modération. Seule votre image et votre voix sont enregistrées.</p>
                @else
                <p class="text-xs text-slate-500">Indisponible : le stockage des vidéos n'est pas encore configuré sur ce serveur.</p>
                @endif
            </div>
            @if($recordingConfigured)
                @include('components.switch', ['name' => 'record', 'checked' => (bool) old('record', true), 'label' => 'Enregistrer le direct', 'sendFalse' => true])
            @else
                <span class="badge-draft">Indisponible</span>
            @endif
        </div>

        <div class="border-t border-slate-100 pt-4">
            <p class="mb-3 text-xs text-slate-500">Le direct ne sera visible qu'une fois votre caméra en ligne, dans le studio.</p>
            <div class="flex flex-wrap justify-end gap-2">
                <a href="{{ route('lives.index') }}" class="btn-secondary">Annuler</a>
                <button type="submit" class="btn-primary" data-submit-guard disabled @disabled(!$configured)>
                    <i class="fa-solid fa-video"></i>Ouvrir le studio
                </button>
            </div>
        </div>
    </section>
</form>
@endsection

@push('scripts')
@vite('resources/js/live.js')
@endpush

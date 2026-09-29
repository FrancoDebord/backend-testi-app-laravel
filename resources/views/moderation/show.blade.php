@extends('layouts.app')
@section('title', 'Relecture — ' . $testimony->title)
@php
    $header      = $testimony->title;
    $subheader   = 'Relecture du témoignage avant publication.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Modération', 'url' => route('moderation.index')],
        ['label' => Str::limit($testimony->title, 40)],
    ];
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    {{-- ── Contenu à relire ───────────────────────────────────────────── --}}
    <div class="min-w-0 space-y-6 lg:col-span-2">
        <section class="card p-5 sm:p-6">
            <h2 class="card-title mb-3">Informations</h2>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2"><dt class="text-slate-500">Statut</dt><dd><span class="{{ $testimony->status->badgeClass() }}">{{ $testimony->status->label() }}</span></dd></div>
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2"><dt class="text-slate-500">Auteur</dt><dd class="text-slate-900">{{ $testimony->user->display_name }}</dd></div>
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2"><dt class="text-slate-500">Pays</dt><dd class="text-slate-900">{{ $testimony->user->country ?? 'Non renseigné' }}</dd></div>
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2"><dt class="text-slate-500">Soumis le</dt><dd class="text-slate-900">{{ $testimony->created_at->format('d/m/Y à H:i') }}</dd></div>
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2"><dt class="text-slate-500">Type</dt><dd class="text-slate-900">{{ $testimony->type->label() }}</dd></div>
                <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2"><dt class="text-slate-500">Catégorie</dt><dd class="text-slate-900">{{ $testimony->category_slug ?: '—' }}</dd></div>
            </dl>
        </section>

        <section class="card overflow-hidden">
            @if($testimony->cover_url)
            <img src="{{ $testimony->cover_url }}" alt="" class="max-h-72 w-full object-cover">
            @endif
            <div class="p-5 sm:p-6">
                <h2 class="card-title mb-3">Contenu</h2>

                @if($testimony->media_url && $testimony->type->value === 'audio')
                <div class="mb-5 rounded-lg border border-slate-200 bg-slate-50 p-3"><audio controls class="w-full"><source src="{{ $testimony->media_url }}"></audio></div>
                @elseif($testimony->media_url && $testimony->type->value === 'video')
                <div class="mb-5 overflow-hidden rounded-lg bg-black"><video controls class="aspect-video w-full"><source src="{{ $testimony->media_url }}"></video></div>
                @endif

                @if($testimony->body_text)
                <div class="text-[15px] leading-relaxed break-words whitespace-pre-line text-slate-800 [&_strong]:font-semibold [&_strong]:text-slate-900">{{ $testimony->body_html }}</div>
                @else
                <p class="text-sm text-slate-500">Aucun texte.</p>
                @endif

                @if($testimony->bible_verse)
                <blockquote class="mt-6 border-l-2 border-slate-300 pl-4">
                    <p class="text-slate-700 italic">« {{ $testimony->bible_verse }} »</p>
                    @if($testimony->bible_ref)<p class="mt-1 text-sm font-medium text-slate-500">{{ $testimony->bible_ref }}</p>@endif
                </blockquote>
                @endif
            </div>
        </section>

        @if($testimony->moderationLogs->isNotEmpty())
        <section class="card p-5 sm:p-6">
            <h2 class="card-title mb-3">Historique de modération</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach($testimony->moderationLogs->sortByDesc('created_at') as $log)
                <li class="py-2">
                    <p class="text-slate-900">
                        <span class="font-medium">{{ $log->action }}</span>
                        @if($log->moderator) par {{ $log->moderator->display_name }}@endif
                        @if($log->rejection_reason) — {{ $log->rejection_reason->label() }}@endif
                    </p>
                    @if($log->moderator_note)<p class="text-slate-600">« {{ $log->moderator_note }} »</p>@endif
                    <p class="text-xs text-slate-400">{{ $log->created_at->diffForHumans() }}</p>
                </li>
                @endforeach
            </ul>
        </section>
        @endif
    </div>

    {{-- ── Décision ──────────────────────────────────────────────────── --}}
    <aside class="min-w-0 space-y-6">
        @if($testimony->status->value === 'pending')
        <section class="card p-5">
            <h2 class="card-title mb-3">Approuver</h2>
            <p class="mb-4 text-sm text-slate-600">Le témoignage sera publié et visible selon la visibilité choisie par l’auteur.</p>
            <form id="approve-form" method="POST" action="{{ route('moderation.approve', $testimony->id) }}" data-loading-label="Publication…">
                @csrf
                <button type="button" class="btn-primary w-full"
                        onclick="openConfirmModal('approve-form', 'Le témoignage sera publié immédiatement.', 'Approuver ce témoignage', 'Approuver et publier', 'fa-check')">
                    Approuver et publier
                </button>
            </form>
        </section>

        <section class="card p-5">
            <h2 class="card-title mb-3">Rejeter</h2>
            <form id="reject-form" method="POST" action="{{ route('moderation.reject', $testimony->id) }}" class="space-y-4" data-loading-label="Enregistrement du rejet…">
                @csrf
                <div>
                    <label for="reason" class="form-label">Motif *</label>
                    <select id="reason" name="reason" required class="form-input">
                        @foreach($reasons as $r)
                        <option value="{{ $r->value }}" @selected(old('reason') === $r->value)>{{ $r->label() }}</option>
                        @endforeach
                    </select>
                    @error('reason')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="moderator_note" class="form-label">Message pour l’auteur (facultatif)</label>
                    <textarea id="moderator_note" name="moderator_note" rows="3" class="form-input resize-none"
                              placeholder="Expliquez ce qui doit être corrigé…">{{ old('moderator_note') }}</textarea>
                    @error('moderator_note')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <button type="button" class="btn-secondary w-full text-red-700"
                        onclick="openConfirmModal('reject-form', 'L’auteur sera informé du rejet et du motif choisi.', 'Rejeter ce témoignage', 'Confirmer le rejet', 'fa-xmark')">
                    Rejeter le témoignage
                </button>
            </form>
        </section>
        @else
        <div class="alert-info" role="status">
            <i class="fa-solid fa-circle-info mt-0.5 text-slate-400"></i>
            <p>Ce témoignage a déjà été traité ({{ $testimony->status->label() }}).</p>
        </div>
        @endif

        <a href="{{ route('moderation.index') }}" class="btn-ghost w-full"><i class="fa-solid fa-arrow-left"></i>Retour à la file</a>
    </aside>
</div>
@endsection

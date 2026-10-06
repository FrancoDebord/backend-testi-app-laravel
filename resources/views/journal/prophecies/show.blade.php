@extends('layouts.app')
@php
    $fulfilled   = $prophecy->isFulfilled();
    $overdue     = !$fulfilled && $prophecy->due_on && $prophecy->due_on->isPast() && !$prophecy->due_on->isToday();
    $fmtDate     = fn ($d) => $d?->translatedFormat('j F Y');
    $audioLength = $prophecy->audio_duration ? sprintf('%d:%02d', intdiv($prophecy->audio_duration, 60), $prophecy->audio_duration % 60) : null;
    $testimony   = $prophecy->testimony_id ? $prophecy->testimony()->first() : null;
    $verse       = config('encouragements.prophecy_verses.0');
    $weekdays    = \App\Http\Controllers\Web\ProphecyController::WEEKDAYS;
    $header      = $prophecy->title ?: 'Parole du ' . $fmtDate($prophecy->received_on);
    $subheader   = 'Visible de vous seul' . ($prophecy->is_public ? ', sauf avec le témoignage publié.' : '.');
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Carnet privé', 'url' => route('journal.index')],
        ['label' => 'Paroles prophétiques', 'url' => route('prophecies.index', $fulfilled ? ['statut' => 'fulfilled'] : [])],
        ['label' => Str::limit($header, 40)],
    ];
@endphp
@section('title', $header)

@section('headerActions')
    <button type="button" class="btn-secondary" data-proclaim-open><i class="fa-solid fa-bullhorn" aria-hidden="true"></i>Proclamer</button>
    <a href="{{ route('prophecies.edit', $prophecy->id) }}" class="btn-secondary"><i class="fa-solid fa-pen" aria-hidden="true"></i>Modifier</a>
    <button type="button" class="btn-ghost text-error-700"
            onclick="openConfirmModal('prophecy-delete-form', 'La parole et son journal de prière seront supprimés de votre carnet.{{ $testimony ? ' Le témoignage publié reste en ligne.' : '' }}', 'Supprimer la parole', 'Supprimer', 'fa-trash')">
        <i class="fa-solid fa-trash" aria-hidden="true"></i><span class="sr-only sm:not-sr-only">Supprimer</span>
    </button>
@endsection

@section('content')
<form id="prophecy-delete-form" method="POST" action="{{ route('prophecies.destroy', $prophecy->id) }}" data-loading-label="Suppression…" hidden>
    @csrf @method('DELETE')
</form>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <div class="min-w-0 space-y-6">

        {{-- ── La parole ─────────────────────────────────────────────── --}}
        <section class="card p-5 sm:p-6" aria-labelledby="prophecy-word-title">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="prophecy-word-title" class="card-title mr-auto">La parole</h2>
                @if($fulfilled)
                <span class="badge-validated"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Accomplie</span>
                @else
                <span class="badge-pending">En attente</span>
                @endif
                @if($overdue)
                <span class="badge-orange">Échéance passée</span>
                @endif
            </div>

            <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div class="min-w-0"><dt class="text-xs text-slate-500">Reçue le</dt><dd class="font-medium text-slate-900">{{ $fmtDate($prophecy->received_on) }}</dd></div>
                @if(filled($prophecy->given_by))
                <div class="min-w-0"><dt class="text-xs text-slate-500">Donnée par</dt><dd class="font-medium break-words text-slate-900">{{ $prophecy->given_by }}</dd></div>
                @endif
                @if($prophecy->due_on)
                <div class="min-w-0"><dt class="text-xs text-slate-500">Échéance annoncée</dt><dd class="font-medium {{ $overdue ? 'text-accent-700' : 'text-slate-900' }}">{{ $fmtDate($prophecy->due_on) }}</dd></div>
                @endif
                @if($fulfilled && $prophecy->fulfilled_on)
                <div class="min-w-0"><dt class="text-xs text-slate-500">Accomplie le</dt><dd class="font-semibold text-success-700"><i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i>{{ $fmtDate($prophecy->fulfilled_on) }}</dd></div>
                @endif
            </dl>

            @if(filled($prophecy->body_text))
            <blockquote class="mt-4 border-l-2 border-sun-400 pl-4 text-[15px] leading-relaxed break-words whitespace-pre-line text-slate-800">{{ $prophecy->body_text }}</blockquote>
            @endif
            @if($prophecy->audio_url)
            <div class="mt-4">
                <p class="mb-1 text-xs text-slate-500">Parole enregistrée @if($audioLength)<span class="tabular-nums">({{ $audioLength }})</span>@endif</p>
                <audio controls preload="metadata" src="{{ $prophecy->audio_url }}" class="w-full" aria-label="Écouter la parole prophétique"></audio>
            </div>
            @endif
        </section>

        {{-- ── Accomplissement ───────────────────────────────────────── --}}
        <section class="card p-5 sm:p-6" aria-labelledby="fulfil-title">
            @if($testimony)
                <h2 id="fulfil-title" class="card-title">Votre témoignage</h2>
                <p class="mt-1 text-sm text-slate-500">
                    @if($prophecy->is_public)
                        La parole est montrée avec ce témoignage, une fois celui-ci publié par la modération.
                    @else
                        La parole reste privée : elle n'est pas montrée avec le témoignage.
                    @endif
                </p>
                <a href="{{ route('testimonies.show', $testimony->id) }}" class="mt-4 flex items-center gap-3 rounded-lg border border-slate-200 p-3 hover:border-primary-200 hover:bg-primary-50">
                    <i class="fa-solid fa-pen-to-square text-primary-600" aria-hidden="true"></i>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-slate-900">{{ $testimony->title }}</span>
                        <span class="block text-xs text-slate-500">{{ $testimony->isInJournal() ? 'Dans votre carnet privé' : $testimony->status->label() }}</span>
                    </span>
                    <i class="fa-solid fa-chevron-right text-xs text-slate-400" aria-hidden="true"></i>
                </a>
            @elseif($fulfilled)
                <h2 id="fulfil-title" class="card-title">Gloire à Dieu !</h2>
                <p class="mt-1 text-sm text-slate-500">Racontez comment cette parole s'est accomplie : votre témoignage peut fortifier quelqu'un. Vous pourrez publier la parole avec lui.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('publish', ['prophecy' => $prophecy->id]) }}" class="btn-cta"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>Témoigner</a>
                    <form method="POST" action="{{ route('prophecies.reopen', $prophecy->id) }}" data-loading-label="Enregistrement…">
                        @csrf
                        <button type="submit" class="btn-ghost"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i>Remettre en attente</button>
                    </form>
                </div>
            @else
                <h2 id="fulfil-title" class="card-title">Elle s'est accomplie ?</h2>
                <p class="mt-1 text-sm text-slate-500">Témoignez de son accomplissement (la parole peut être publiée avec votre témoignage), ou marquez-la simplement accomplie : vous pourrez témoigner plus tard.</p>
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <a href="{{ route('publish', ['prophecy' => $prophecy->id]) }}" class="btn-cta"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>Témoigner</a>
                    <form method="POST" action="{{ route('prophecies.fulfill', $prophecy->id) }}" class="flex flex-col gap-2 sm:flex-row sm:items-end" data-loading-label="Enregistrement…">
                        @csrf
                        <div>
                            <label for="fulfilled_on" class="form-label">Accomplie le</label>
                            <input id="fulfilled_on" type="date" name="fulfilled_on" value="{{ old('fulfilled_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-input" @error('fulfilled_on') aria-invalid="true" @enderror>
                        </div>
                        <button type="submit" class="btn-secondary"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Marquer accomplie</button>
                    </form>
                </div>
                @error('fulfilled_on')<p class="form-error">{{ $message }}</p>@enderror
            @endif
        </section>

        {{-- ── Journal de prière ─────────────────────────────────────── --}}
        <section class="card p-5 sm:p-6" aria-labelledby="prayers-title">
            <h2 id="prayers-title" class="card-title">Journal de prière <span class="font-normal text-slate-500">· {{ $prophecy->prayer_count }}</span></h2>
            @if($prophecy->last_prayed_at)
            <p class="mt-1 text-sm text-slate-500">Dernière prière le {{ $prophecy->last_prayed_at->translatedFormat('j F Y') }}.</p>
            @endif

            @unless($fulfilled)
            <form method="POST" action="{{ route('prophecies.pray', $prophecy->id) }}" class="mt-4 space-y-2" data-loading-label="Enregistrement de la prière…">
                @csrf
                <label for="prayer-note" class="form-label">Note (facultatif)</label>
                <textarea id="prayer-note" name="note" rows="2" maxlength="1000" class="form-input" placeholder="Ce que vous avez demandé, un verset reçu…" @error('note') aria-invalid="true" @enderror>{{ old('note') }}</textarea>
                @error('note')<p class="form-error">{{ $message }}</p>@enderror
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-hands-praying" aria-hidden="true"></i>J'ai prié</button>
                </div>
            </form>
            @endunless

            @if($prayers->isEmpty())
            <p class="mt-4 text-sm text-slate-500">{{ $fulfilled ? 'Aucune prière enregistrée pour cette parole.' : 'Après avoir prié pour cette parole, enregistrez-le ici : vous garderez la trace de votre persévérance.' }}</p>
            @else
            <ul class="mt-5 divide-y divide-slate-100 border-t border-slate-100">
                @foreach($prayers as $prayer)
                <li class="flex items-start gap-3 py-3">
                    <i class="fa-solid fa-hands-praying mt-1 text-sm text-primary-600" aria-hidden="true"></i>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-900">{{ $prayer->prayed_at?->translatedFormat('l j F Y à H:i') }}</p>
                        @if(filled($prayer->note))
                        <p class="mt-0.5 text-sm break-words whitespace-pre-line text-slate-600">{{ $prayer->note }}</p>
                        @endif
                    </div>
                    <form id="prayer-delete-{{ $prayer->id }}" method="POST" action="{{ route('prophecies.prayers.destroy', [$prophecy->id, $prayer->id]) }}" data-loading-label="Retrait…" hidden>
                        @csrf @method('DELETE')
                    </form>
                    <button type="button" class="action-btn-delete" aria-label="Retirer cette prière du journal" title="Retirer"
                            onclick="openConfirmModal('prayer-delete-{{ $prayer->id }}', 'Cette prière sera retirée du journal.', 'Retirer la prière', 'Retirer', 'fa-trash')">
                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                    </button>
                </li>
                @endforeach
            </ul>
            <div class="mt-3">{{ $prayers->links() }}</div>
            @endif
        </section>
    </div>

    {{-- ── Colonne : verset et rappel ───────────────────────────────── --}}
    <aside class="min-w-0 space-y-6">
        @if($verse && !$fulfilled)
        <div class="card-insight p-5">
            <blockquote class="text-[15px] leading-relaxed break-words text-slate-900 italic">« {{ $verse['text'] }} »</blockquote>
            <p class="mt-1 text-sm font-semibold text-primary-700">{{ $verse['ref'] }}</p>
        </div>
        @endif

        <section class="card p-5" aria-labelledby="reminder-title">
            <h2 id="reminder-title" class="card-title">Rappel de prière</h2>
            @if($prophecy->reminder_frequency)
            <p class="mt-2 text-sm text-slate-900">
                <i class="fa-regular fa-bell mr-1 text-primary-600" aria-hidden="true"></i>
                {{ $prophecy->reminder_frequency === 'weekly' ? 'Chaque ' . Str::lower($weekdays[$prophecy->reminder_weekday] ?? '') : 'Chaque jour' }}
                à {{ $prophecy->reminder_time }}
            </p>
            <p class="mt-1 text-xs text-slate-500">{{ $fulfilled ? 'Plus de rappel : la parole est accomplie.' : "Envoyé par l'application TestiApp sur votre téléphone." }}</p>
            @else
            <p class="mt-2 text-sm text-slate-500">Aucun rappel. Programmez-en un pour penser à prier ; il sera envoyé par l'application sur votre téléphone.</p>
            @endif
            <a href="{{ route('prophecies.edit', $prophecy->id) }}#reminder-title" class="btn-secondary btn-sm mt-3">{{ $prophecy->reminder_frequency ? 'Modifier le rappel' : 'Programmer un rappel' }}</a>
        </section>
    </aside>
</div>

{{-- ── Proclamer : lecture en plein écran ─────────────────────────── --}}
<dialog id="proclaim-dialog" class="m-0 h-full max-h-none w-full max-w-none bg-white p-0 backdrop:bg-slate-900/60" aria-labelledby="proclaim-title">
    <div class="flex h-full flex-col">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 sm:px-6">
            <h2 id="proclaim-title" class="text-sm font-semibold text-primary-600"><i class="fa-solid fa-bullhorn mr-2" aria-hidden="true"></i>Proclamer la parole</h2>
            <button type="button" class="btn-ghost btn-sm" data-proclaim-close aria-label="Fermer"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="flex-1 overflow-y-auto px-4 py-8 sm:px-10">
            <div class="mx-auto max-w-3xl">
                @if(filled($prophecy->title))
                <p class="text-lg font-bold break-words text-primary-700 sm:text-xl">{{ $prophecy->title }}</p>
                @endif
                @if(filled($prophecy->body_text))
                <p class="mt-4 text-2xl leading-relaxed font-medium break-words whitespace-pre-line text-slate-900 sm:text-3xl">{{ $prophecy->body_text }}</p>
                @endif
                @if($prophecy->audio_url)
                <audio controls preload="none" src="{{ $prophecy->audio_url }}" class="mt-6 w-full" aria-label="Écouter la parole prophétique"></audio>
                @endif
                <p class="mt-6 text-sm text-slate-500">
                    Reçue le {{ $fmtDate($prophecy->received_on) }}@if(filled($prophecy->given_by)) · {{ $prophecy->given_by }}@endif
                </p>
                @if($verse)
                <div class="card-insight mt-8 p-5">
                    <blockquote class="text-base leading-relaxed break-words text-slate-900 italic">« {{ $verse['text'] }} »</blockquote>
                    <p class="mt-1 text-sm font-semibold text-primary-700">{{ $verse['ref'] }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
(function () {
    const dialog = document.getElementById('proclaim-dialog');
    document.querySelectorAll('[data-proclaim-open]').forEach(function (btn) {
        btn.addEventListener('click', function () { dialog.showModal(); });
    });
    dialog.querySelectorAll('[data-proclaim-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { dialog.close(); });
    });
    // L'audio s'arrête à la fermeture.
    dialog.addEventListener('close', function () {
        dialog.querySelectorAll('audio').forEach(function (a) { a.pause(); });
    });
})();
</script>
@endpush

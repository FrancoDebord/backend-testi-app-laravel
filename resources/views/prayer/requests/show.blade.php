@extends('layouts.app')
@php
    $isAuthor    = $prayer->isAuthor($user);
    $moderator   = (bool) $user?->canModerate();
    $author      = $prayer->revealsAuthorTo($user) ? $prayer->user : null;
    $hasPrayed   = $prayer->hasPrayed($user);
    $header      = 'Requête de prière';
    $subheader   = ($author ? $author->display_name : 'Anonyme') . ' · ' . $prayer->created_at?->translatedFormat('j F Y');
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Requêtes de prière', 'url' => route('prayer.requests.index')],
        ['label' => Str::limit($prayer->body, 40)],
    ];
@endphp
@section('title', $header)

@section('headerActions')
    @if($prayer->canBeDeletedBy($user))
    <button type="button" class="btn-ghost text-error-700"
            onclick="openConfirmModal('prayer-delete-form', 'La requête et ses messages d\'encouragement seront supprimés.', 'Supprimer la requête', 'Supprimer', 'fa-trash')">
        <i class="fa-solid fa-trash" aria-hidden="true"></i><span class="sr-only sm:not-sr-only">Supprimer</span>
    </button>
    @endif
@endsection

@section('content')
@if($prayer->canBeDeletedBy($user))
<form id="prayer-delete-form" method="POST" action="{{ route('prayer.requests.destroy', $prayer->id) }}" data-loading-label="Suppression…" hidden>
    @csrf @method('DELETE')
</form>
@endif

<div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <div class="min-w-0 space-y-6">

        {{-- ── La requête ───────────────────────────────────────────── --}}
        <section class="card p-5 sm:p-6" aria-labelledby="prayer-title">
            <h2 id="prayer-title" class="sr-only">La requête</h2>
            <div class="flex items-start gap-3">
                @if($author)
                    @include('components.avatar', ['user' => $author, 'size' => 'md'])
                @else
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-500" aria-hidden="true"><i class="fa-solid fa-user-secret"></i></span>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-900">
                        @if($author)
                            <a href="{{ route('profiles.show', $author->id) }}" class="hover:underline">{{ $author->display_name }}</a>
                            @include('components.verified-badge', ['user' => $author])
                        @else Anonyme @endif
                    </p>
                    <p class="text-xs text-slate-500">{{ $prayer->created_at?->diffForHumans() }} · {{ \App\Models\PrayerRequest::VISIBILITY_LABELS[$prayer->visibility] }}@if($prayer->is_anonymous) · anonyme @endif</p>
                </div>
                @if($prayer->isAnswered())
                <span class="badge-validated shrink-0"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Exaucée</span>
                @endif
            </div>

            @if($prayer->isHidden())
            <p class="alert-warning mt-4 text-sm"><i class="fa-solid fa-eye-slash mt-0.5" aria-hidden="true"></i><span>{{ $prayer->hidden_reason ?: 'Retirée par la modération.' }} Elle n'est plus visible des autres.</span></p>
            @endif

            <p class="mt-4 text-[15px] leading-relaxed break-words whitespace-pre-line text-slate-800">{{ $prayer->body }}</p>

            @if($prayer->event)
            <a href="{{ route('events.show', $prayer->event_id) }}" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary-700 hover:underline">
                <i class="fa-regular fa-calendar" aria-hidden="true"></i>{{ $prayer->event->title }}
            </a>
            @endif

            @if($prayer->isAnswered())
            <div class="card-insight mt-4 p-4">
                <p class="text-sm font-semibold text-success-700"><i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i>Exaucée le {{ $prayer->answered_at?->translatedFormat('j F Y') }}</p>
                @if(filled($prayer->answer_note))<p class="mt-1 text-sm break-words whitespace-pre-line text-slate-700">{{ $prayer->answer_note }}</p>@endif
                @if($prayer->testimony_id)
                <a href="{{ route('testimonies.show', $prayer->testimony_id) }}" class="mt-2 inline-flex text-sm font-medium text-primary-700 hover:underline">Lire le témoignage</a>
                @endif
            </div>
            @endif

            <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                @if($user && !$prayer->isHidden())
                <form method="POST" action="{{ route('prayer.requests.pray', $prayer->id) }}" data-loading-label="Enregistrement…">
                    @csrf
                    <button type="submit" class="{{ $hasPrayed ? 'btn-primary' : 'btn-secondary' }}" aria-pressed="{{ $hasPrayed ? 'true' : 'false' }}">
                        <i class="fa-solid fa-hands-praying" aria-hidden="true"></i>{{ $hasPrayed ? 'Je prie pour vous' : 'Je prie' }}
                    </button>
                </form>
                @elseif(!$user)
                <a href="{{ route('login') }}" class="btn-secondary"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>Se connecter pour prier</a>
                @endif
                <span class="text-sm text-slate-600"><span class="font-semibold tabular-nums text-slate-900">{{ $prayer->prayer_count }}</span> {{ $prayer->prayer_count > 1 ? 'personnes prient' : 'personne prie' }}</span>
            </div>
        </section>

        {{-- ── Encouragements ───────────────────────────────────────── --}}
        <section id="encouragements" class="card scroll-mt-24 p-5 sm:p-6" aria-labelledby="messages-title">
            <h2 id="messages-title" class="card-title">Encouragements <span class="tabular-nums text-slate-500">({{ $prayer->message_count }})</span></h2>

            @if($messages->isEmpty())
            <p class="mt-2 text-sm text-slate-500">Aucun message pour le moment. Un verset, une parole d'encouragement peuvent fortifier.</p>
            @else
            <ul class="mt-4 space-y-4">
                @foreach($messages as $message)
                <li class="flex gap-3">
                    @include('components.avatar', ['user' => $message->user, 'size' => 'sm'])
                    <div class="min-w-0 flex-1 rounded-lg bg-slate-50 p-3">
                        <p class="text-xs text-slate-500"><span class="font-semibold text-slate-900">{{ $message->user?->display_name }}</span> · {{ $message->created_at?->diffForHumans() }}</p>
                        <p class="mt-1 text-sm break-words whitespace-pre-line text-slate-800">{{ $message->body }}</p>
                        @if($message->bible_reference)
                        <p class="mt-1 text-xs font-semibold text-primary-700"><i class="fa-solid fa-book-bible mr-1" aria-hidden="true"></i>{{ $message->bible_reference }}</p>
                        @endif
                    </div>
                    @if($message->canBeDeletedBy($user, $prayer))
                    <form method="POST" action="{{ route('prayer.requests.messages.destroy', [$prayer->id, $message->id]) }}" data-loading-label="Suppression…">
                        @csrf @method('DELETE')
                        <button type="submit" class="action-btn-delete" title="Supprimer le message" aria-label="Supprimer le message"><i class="fa-solid fa-trash"></i></button>
                    </form>
                    @endif
                </li>
                @endforeach
            </ul>
            {{ $messages->links() }}
            @endif

            @if($prayer->acceptsMessagesFrom($user))
            <form method="POST" action="{{ route('prayer.requests.messages.store', $prayer->id) }}" class="mt-5 space-y-3 border-t border-slate-100 pt-4" data-loading-label="Envoi…">
                @csrf
                <div>
                    <label for="message" class="form-label">Votre message d'encouragement</label>
                    <textarea id="message" name="message" rows="3" maxlength="1000" required class="form-input" placeholder="Je prie avec vous…">{{ old('message') }}</textarea>
                    @error('message')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="sm:w-1/2">
                    <label for="bible_reference" class="form-label">Verset (facultatif)</label>
                    <input id="bible_reference" type="text" name="bible_reference" maxlength="100" value="{{ old('bible_reference') }}" class="form-input" placeholder="Ex. : Philippiens 4:6-7">
                </div>
                <div class="flex justify-end"><button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>Envoyer</button></div>
            </form>
            @elseif(!$user)
            <p class="mt-4 text-sm text-slate-500"><a href="{{ route('login') }}" class="font-medium text-primary-700 hover:underline">Connectez-vous</a> pour laisser un encouragement.</p>
            @endif
        </section>
    </div>

    {{-- ── Colonne latérale ─────────────────────────────────────────── --}}
    <aside class="min-w-0 space-y-6">
        @if($isAuthor)
        <section class="card p-5" aria-labelledby="answer-title">
            <h2 id="answer-title" class="card-title">Exaucement</h2>
            @if($prayer->isAnswered())
                <p class="mt-1 text-sm text-slate-600">Gloire à Dieu ! Racontez ce qu'il a fait pour encourager les autres.</p>
                <a href="{{ route('publish') }}" class="btn-cta mt-4 w-full"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>Témoigner</a>
                @unless($prayer->testimony_id)
                <form method="POST" action="{{ route('prayer.requests.reopen', $prayer->id) }}" class="mt-2 text-center" data-loading-label="Mise à jour…">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-ghost btn-sm">Remettre en attente</button>
                </form>
                @endunless
            @else
                <p class="mt-1 text-sm text-slate-600">Dieu a répondu ? Marquez la requête exaucée.</p>
                <form method="POST" action="{{ route('prayer.requests.answered', $prayer->id) }}" class="mt-3 space-y-3" data-loading-label="Enregistrement…">
                    @csrf
                    <label for="note" class="sr-only">Ce que Dieu a fait (facultatif)</label>
                    <textarea id="note" name="note" rows="3" maxlength="1000" class="form-input" placeholder="Ce que Dieu a fait (facultatif)"></textarea>
                    <button type="submit" class="btn-primary w-full"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Elle est exaucée</button>
                </form>
            @endif
        </section>
        @endif

        @if($moderator && !$isAuthor)
        <section class="card p-5" aria-labelledby="moderate-title">
            <h2 id="moderate-title" class="card-title">Modération</h2>
            <p class="mt-1 text-sm text-slate-600">{{ $prayer->report_count }} signalement{{ $prayer->report_count > 1 ? 's' : '' }}.</p>
            @if($prayer->isHidden())
            <form method="POST" action="{{ route('prayer.moderation.restore', $prayer->id) }}" class="mt-3" data-loading-label="Rétablissement…">
                @csrf
                <button type="submit" class="btn-secondary w-full"><i class="fa-solid fa-eye" aria-hidden="true"></i>Rétablir</button>
            </form>
            @else
            <form method="POST" action="{{ route('prayer.moderation.hide', $prayer->id) }}" class="mt-3 space-y-2" data-loading-label="Retrait…">
                @csrf
                <label for="hide-reason" class="sr-only">Motif du retrait</label>
                <input id="hide-reason" type="text" name="reason" maxlength="300" class="form-input" placeholder="Motif (montré à l'auteur)">
                <button type="submit" class="btn-secondary w-full text-error-700"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i>Retirer la requête</button>
            </form>
            @endif
        </section>
        @endif

        @if($user && !$isAuthor && !$prayer->isHidden())
        <details class="card p-5">
            <summary class="cursor-pointer text-sm font-medium text-slate-700"><i class="fa-regular fa-flag mr-1" aria-hidden="true"></i>Signaler cette requête</summary>
            <form method="POST" action="{{ route('prayer.requests.report', $prayer->id) }}" class="mt-3 space-y-3" data-loading-label="Envoi…">
                @csrf
                <label for="reason" class="form-label">Raison</label>
                <select id="reason" name="reason" required class="form-input">
                    @foreach($reasons as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <label for="report-comment" class="sr-only">Précisions</label>
                <textarea id="report-comment" name="comment" rows="2" maxlength="500" class="form-input" placeholder="Précisions (facultatif)"></textarea>
                <button type="submit" class="btn-secondary w-full">Envoyer le signalement</button>
            </form>
        </details>
        @endif
    </aside>
</div>
@endsection

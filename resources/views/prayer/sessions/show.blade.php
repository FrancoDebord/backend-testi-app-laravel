@extends('layouts.app')
@php
    $viewer      = Auth::user();
    $phase       = $session->phase();
    $isHost      = $session->isHost($viewer);
    $registered  = $session->isRegistered($viewer);
    $header      = $session->title;
    $subheader   = $session->starts_at->translatedFormat('l j F Y, H:i') . ' · ' . $session->duration_minutes . ' min · animée par ' . $session->host?->display_name;
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Sessions de prière', 'url' => route('prayer.sessions.index')],
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
    <a href="{{ route('prayer.sessions.edit', $session->id) }}" class="btn-secondary"><i class="fa-solid fa-pen" aria-hidden="true"></i>Modifier</a>
    @endif
    @if($session->canBeDeletedBy($viewer))
    <button type="button" class="btn-ghost text-error-700"
            onclick="openConfirmModal('session-delete-form', 'La session sera supprimée et sa salle fermée si elle est ouverte.', 'Supprimer la session', 'Supprimer', 'fa-trash')">
        <i class="fa-solid fa-trash" aria-hidden="true"></i><span class="sr-only sm:not-sr-only">Supprimer</span>
    </button>
    @endif
@endsection

@section('content')
@if($session->canBeDeletedBy($viewer))
<form id="session-delete-form" method="POST" action="{{ route('prayer.sessions.destroy', $session->id) }}" data-loading-label="Suppression…" hidden>
    @csrf @method('DELETE')
</form>
@endif

<div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <div class="min-w-0 space-y-6">
        <section class="card p-5 sm:p-6" aria-labelledby="session-about">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="session-about" class="card-title mr-auto">La session</h2>
                <span class="{{ $badge }}">{{ $session->phaseLabel() }}</span>
                <span class="badge-neutral">{{ \App\Models\PrayerSession::VISIBILITY_LABELS[$session->visibility] }}</span>
            </div>
            @if(filled($session->description))
            <p class="mt-4 text-[15px] leading-relaxed break-words whitespace-pre-line text-slate-800">{{ $session->description }}</p>
            @endif
            @if(!empty($session->topics))
            <h3 class="mt-5 text-sm font-semibold text-slate-900">Sujets de prière</h3>
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
            <h2 id="participants-title" class="card-title">Inscrits <span class="tabular-nums text-slate-500">({{ $session->participant_count }})</span></h2>
            @if($participants->isEmpty())
            <p class="mt-2 text-sm text-slate-500">Personne n'est encore inscrit. Partagez le lien de la session.</p>
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
            <h2 id="room-title" class="card-title">La salle de prière</h2>
            <p class="mt-1 text-sm text-slate-600">
                <span class="font-semibold text-slate-900">{{ $session->participant_count }}</span> {{ $session->participant_count > 1 ? 'inscrits' : 'inscrit' }}
            </p>

            @if($phase === 'cancelled')
                <p class="mt-3 text-sm text-slate-500">Cette session a été annulée.</p>
            @elseif($isHost)
                @if(!$configured)
                    <p class="alert-warning mt-3 text-sm"><i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i><span>Le service vidéo n'est pas encore configuré sur ce serveur.</span></p>
                @elseif($session->canBeStartedBy($viewer))
                    <form method="POST" action="{{ route('prayer.sessions.open', $session->id) }}" class="mt-4" data-loading-label="Ouverture de la salle…">
                        @csrf
                        <button type="submit" class="btn-cta w-full"><i class="fa-solid fa-tower-broadcast" aria-hidden="true"></i>{{ $session->activeLive() ? 'Retourner dans la salle' : 'Ouvrir la salle' }}</button>
                    </form>
                    <p class="form-hint mt-2">Vous arrivez dans le studio : passez à l'antenne pour commencer. Les inscrits sont prévenus.</p>
                @elseif($phase === 'upcoming')
                    <p class="mt-3 text-sm text-slate-500">Vous pourrez ouvrir la salle à partir du {{ $session->starts_at->copy()->subMinutes(\App\Models\PrayerSession::OPEN_BEFORE_MINUTES)->translatedFormat('j F à H:i') }}.</p>
                @else
                    <p class="mt-3 text-sm text-slate-500">Cette session est terminée.</p>
                @endif
                @if($phase !== 'ended')
                <form method="POST" action="{{ route('prayer.sessions.cancel', $session->id) }}" class="mt-2 text-center" data-loading-label="Annulation…">
                    @csrf
                    <button type="submit" class="btn-ghost btn-sm">Annuler la session</button>
                </form>
                @endif
            @else
                @if($live)
                    <a href="{{ route('prayer.sessions.room', $session->id) }}" class="btn-cta mt-4 w-full"><i class="fa-solid fa-door-open" aria-hidden="true"></i>Rejoindre la salle</a>
                    <p class="form-hint mt-2">Commentez et demandez à prendre la parole depuis la salle.</p>
                @elseif($phase === 'ended')
                    <p class="mt-3 text-sm text-slate-500">Cette session est terminée.</p>
                @else
                    <p class="mt-3 text-sm text-slate-500">La salle s'ouvrira à l'heure prévue. {{ $registered ? 'Vous serez prévenu.' : '' }}</p>
                @endif

                @if($phase !== 'ended')
                    @if(!$viewer)
                    <a href="{{ route('login') }}" class="btn-secondary mt-3 w-full"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>Se connecter pour s'inscrire</a>
                    @elseif($registered)
                    <form method="POST" action="{{ route('prayer.sessions.leave', $session->id) }}" class="mt-3" data-loading-label="Mise à jour…">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-primary w-full" aria-pressed="true"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Je serai là</button>
                    </form>
                    <p class="form-hint mt-1 text-center">Toucher à nouveau pour retirer l'inscription.</p>
                    @else
                    <form method="POST" action="{{ route('prayer.sessions.join', $session->id) }}" class="mt-3" data-loading-label="Inscription…">
                        @csrf
                        <button type="submit" class="btn-secondary w-full"><i class="fa-regular fa-bell" aria-hidden="true"></i>Je serai là</button>
                    </form>
                    @endif
                @endif
            @endif

            @if($viewer?->canModerate() && !$isHost && $live)
            <p class="form-hint mt-3">Modération : coupez la salle depuis la page du direct (« Couper le direct »).</p>
            @endif
        </section>
    </aside>
</div>
@endsection

{{--
    Bloc « Prière » de la page d'un événement (colonne latérale) : sessions de prière rattachées
    et dernières requêtes. @include('prayer.partials.event-section', ['event' => $event])
    Voir docs/fonctionnalites/requetes-de-priere.md, sessions-de-priere.md
--}}
@php
    $prayerViewer   = Auth::user();
    $eventRequests  = app(\App\Services\PrayerRequests::class)->list($prayerViewer, 'event', $event->id)->limit(3)->get();
    $requestTotal   = app(\App\Services\PrayerRequests::class)->list($prayerViewer, 'event', $event->id)->count();
    $eventSessions  = app(\App\Services\PrayerSessions::class)->list($prayerViewer, 'event', $event->id)
        ->where('status', \App\Models\PrayerSession::SCHEDULED)->where('ends_at', '>=', now())->limit(3)->get();
    $canAddSession  = $event->canBeManagedBy($prayerViewer) && $event->status === \App\Enums\EventStatus::Published;
@endphp
<section class="card p-5" aria-labelledby="event-prayer-title">
    <h2 id="event-prayer-title" class="card-title"><i class="fa-solid fa-hands-praying mr-1 text-primary-600" aria-hidden="true"></i>Prière</h2>

    @if($eventSessions->isNotEmpty())
    <ul class="mt-3 space-y-2">
        @foreach($eventSessions as $eventSession)
        <li>
            <a href="{{ route('prayer.sessions.show', $eventSession->id) }}" class="flex items-center gap-3 rounded-lg border border-slate-200 p-3 hover:border-primary-200">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-700" aria-hidden="true"><i class="fa-solid fa-people-group"></i></span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold text-slate-900">{{ $eventSession->title }}</span>
                    <span class="block text-xs text-slate-500">{{ $eventSession->phase() === 'live' ? 'En cours' : $eventSession->starts_at->translatedFormat('D j M, H:i') }}</span>
                </span>
            </a>
        </li>
        @endforeach
    </ul>
    @endif

    <p class="mt-3 text-sm text-slate-600">
        <span class="font-semibold text-slate-900">{{ $requestTotal }}</span> {{ $requestTotal > 1 ? 'requêtes de prière' : 'requête de prière' }}
    </p>
    @foreach($eventRequests as $eventRequest)
    <a href="{{ route('prayer.requests.show', $eventRequest->id) }}" class="mt-2 block rounded-lg bg-slate-50 p-3 text-sm break-words text-slate-700 hover:bg-slate-100">
        <span class="block text-xs font-semibold text-slate-500">{{ $eventRequest->revealsAuthorTo($prayerViewer) ? $eventRequest->user?->display_name : 'Anonyme' }} · {{ $eventRequest->prayer_count }} <i class="fa-solid fa-hands-praying" aria-hidden="true"></i></span>
        {{ Str::limit($eventRequest->body, 120) }}
    </a>
    @endforeach

    <div class="mt-4 grid grid-cols-1 gap-2">
        @if($prayerViewer)
        <a href="{{ route('prayer.requests.create', ['evenement' => $event->id]) }}" class="btn-secondary w-full"><i class="fa-solid fa-plus" aria-hidden="true"></i>Confier une requête</a>
        @else
        <a href="{{ route('login') }}" class="btn-secondary w-full"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>Se connecter pour prier</a>
        @endif
        @if($canAddSession)
        <a href="{{ route('prayer.sessions.create', ['evenement' => $event->id]) }}" class="btn-ghost btn-sm w-full"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>Programmer une session de prière</a>
        @endif
    </div>
</section>

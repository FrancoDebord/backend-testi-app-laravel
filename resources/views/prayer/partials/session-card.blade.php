{{-- Carte d'une session de prière. Variable : $session (charger host, event, lives actives). --}}
@php
    $phase = $session->phase();
    $badge = match ($phase) {
        'live'      => 'badge-live',
        'ended'     => 'badge-neutral',
        'cancelled' => 'badge-rejected',
        default     => 'badge-blue',
    };
@endphp
<a href="{{ route('prayer.sessions.show', $session->id) }}" class="card flex h-full flex-col gap-3 p-4 hover:border-primary-200 sm:p-5">
    <div class="flex flex-wrap items-center gap-2">
        <span class="{{ $badge }}">@if($phase === 'live')<i class="fa-solid fa-circle text-[8px]" aria-hidden="true"></i>@endif{{ $session->phaseLabel() }}</span>
        @if($session->visibility === \App\Models\PrayerSession::FOLLOWERS)
        <span class="badge-neutral"><i class="fa-solid fa-user-group" aria-hidden="true"></i>{{ __('prayer.sessions.card.followers') }}</span>
        @endif
        @if($session->isRegistered(Auth::user()))
        <span class="badge-validated"><i class="fa-solid fa-check" aria-hidden="true"></i>{{ __('prayer.sessions.card.registered') }}</span>
        @endif
    </div>
    <div class="min-w-0 flex-1">
        <h3 class="font-semibold break-words text-slate-900">{{ $session->title }}</h3>
        @if(filled($session->description))
        <p class="mt-1 text-sm break-words text-slate-600">{{ Str::limit($session->description, 140) }}</p>
        @endif
    </div>
    <dl class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
        <div><dt class="sr-only">{{ __('prayer.sessions.card.date') }}</dt><dd><i class="fa-regular fa-calendar mr-1" aria-hidden="true"></i>{{ $session->starts_at->translatedFormat('D j M, H:i') }}</dd></div>
        <div><dt class="sr-only">{{ __('prayer.sessions.card.duration') }}</dt><dd><i class="fa-regular fa-clock mr-1" aria-hidden="true"></i>{{ __('prayer.sessions.card.minutes', ['count' => $session->duration_minutes]) }}</dd></div>
        <div class="min-w-0"><dt class="sr-only">{{ __('prayer.sessions.card.host') }}</dt><dd class="truncate"><i class="fa-regular fa-user mr-1" aria-hidden="true"></i>{{ $session->host?->display_name }}</dd></div>
        <div><dt class="sr-only">{{ __('prayer.sessions.card.participants') }}</dt><dd><i class="fa-solid fa-people-group mr-1" aria-hidden="true"></i>{{ $session->participant_count }} {{ trans_choice('prayer.sessions.card.registered_count', $session->participant_count) }}</dd></div>
    </dl>
</a>

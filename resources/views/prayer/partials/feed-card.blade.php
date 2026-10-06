{{--
    Carte d'une requête de prière, réutilisable (liste, page d'un événement, fils « Pour vous » / « Mon fil »).
    @include('prayer.partials.feed-card', ['request' => $prayerRequest])   (alias accepté : 'prayer')
    Charger `user` et `event` ; `withPrayedBy($viewer)` évite une requête par carte.
    Voir docs/fonctionnalites/requetes-de-priere.md
--}}
@php
    // `$request` d'abord : les variables de la vue parente (ex. `$prayer`) sont aussi transmises à l'include.
    $prayerItem   = (isset($request) && $request instanceof \App\Models\PrayerRequest) ? $request : $prayer;
    $viewer       = Auth::user();
    $showAuthor   = $prayerItem->revealsAuthorTo($viewer);
    $cardAuthor   = $showAuthor ? $prayerItem->user : null;
    $hasPrayed    = $prayerItem->hasPrayed($viewer);
    $compact      = $compact ?? false;
@endphp
<article class="card flex h-full flex-col gap-3 p-4 sm:p-5" aria-label="Requête de prière">
    <header class="flex items-start gap-3">
        @if($cardAuthor)
            @include('components.avatar', ['user' => $cardAuthor, 'size' => 'md'])
        @else
            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-500" aria-hidden="true"><i class="fa-solid fa-user-secret"></i></span>
        @endif
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-slate-900">
                @if($cardAuthor)
                    <a href="{{ route('profiles.show', $cardAuthor->id) }}" class="hover:underline">{{ $cardAuthor->display_name }}</a>
                    @include('components.verified-badge', ['user' => $cardAuthor])
                    @if($prayerItem->is_anonymous)<span class="text-xs font-normal text-slate-500">(anonyme pour les autres)</span>@endif
                @else
                    Anonyme
                @endif
            </p>
            <p class="text-xs text-slate-500">
                <i class="fa-solid fa-hands-praying mr-1" aria-hidden="true"></i>Requête de prière · {{ $prayerItem->created_at?->diffForHumans() }}
                @if($prayerItem->visibility !== \App\Models\PrayerRequest::PUBLIC)
                · <i class="fa-solid {{ $prayerItem->visibility === 'private' ? 'fa-lock' : 'fa-user-group' }}" aria-hidden="true"></i> {{ \App\Models\PrayerRequest::VISIBILITY_LABELS[$prayerItem->visibility] }}
                @endif
            </p>
        </div>
        @if($prayerItem->isAnswered())
        <span class="badge-validated shrink-0"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Exaucée</span>
        @endif
    </header>

    @if($prayerItem->isHidden())
    <p class="alert-warning text-sm"><i class="fa-solid fa-eye-slash mt-0.5" aria-hidden="true"></i><span>{{ $prayerItem->hidden_reason ?: 'Retirée par la modération.' }}</span></p>
    @endif

    <a href="{{ route('prayer.requests.show', $prayerItem->id) }}" class="block min-w-0 flex-1">
        <p class="text-[15px] leading-relaxed break-words whitespace-pre-line text-slate-800">{{ $compact ? Str::limit($prayerItem->body, 160) : Str::limit($prayerItem->body, 400) }}</p>
    </a>

    @if($prayerItem->event_id && $prayerItem->event && !($hideEvent ?? false))
    <a href="{{ route('events.show', $prayerItem->event_id) }}" class="truncate text-xs font-medium text-primary-700 hover:underline">
        <i class="fa-regular fa-calendar mr-1" aria-hidden="true"></i>{{ $prayerItem->event->title }}
    </a>
    @endif

    <footer class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
        @if($viewer && !$prayerItem->isHidden())
        <form method="POST" action="{{ route('prayer.requests.pray', $prayerItem->id) }}" data-loading-label="Enregistrement…">
            @csrf
            <button type="submit" class="{{ $hasPrayed ? 'btn-primary' : 'btn-secondary' }} btn-sm" aria-pressed="{{ $hasPrayed ? 'true' : 'false' }}">
                <i class="fa-solid fa-hands-praying" aria-hidden="true"></i>Je prie
                <span class="tabular-nums">{{ $prayerItem->prayer_count }}</span>
            </button>
        </form>
        @else
        <span class="text-sm text-slate-600"><i class="fa-solid fa-hands-praying mr-1 text-primary-600" aria-hidden="true"></i><span class="tabular-nums">{{ $prayerItem->prayer_count }}</span> {{ $prayerItem->prayer_count > 1 ? 'prient' : 'prie' }}</span>
        @endif
        <a href="{{ route('prayer.requests.show', $prayerItem->id) }}#encouragements" class="btn-ghost btn-sm">
            <i class="fa-regular fa-comment" aria-hidden="true"></i><span class="tabular-nums">{{ $prayerItem->message_count }}</span>
            <span class="sr-only sm:not-sr-only">{{ $prayerItem->message_count > 1 ? 'encouragements' : 'encouragement' }}</span>
        </a>
    </footer>
</article>

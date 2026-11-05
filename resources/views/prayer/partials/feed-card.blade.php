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
    $prayWith     = $prayerItem->prayWithAuthorFor($viewer); // « Je prie avec Marie » (User::shortFirstName)
    $compact      = $compact ?? false;
@endphp
<article class="card flex h-full flex-col gap-3 p-4 sm:p-5" aria-label="{{ __('prayer.requests.card.label') }}">
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
                    @if($prayerItem->is_anonymous)<span class="text-xs font-normal text-slate-500">{{ __('prayer.requests.card.anonymous_to_others') }}</span>@endif
                @else
                    {{ __('prayer.common.anonymous') }}
                @endif
            </p>
            <p class="text-xs text-slate-500">
                <i class="fa-solid fa-hands-praying mr-1" aria-hidden="true"></i>{{ __('prayer.requests.card.label') }} · {{ $prayerItem->created_at?->diffForHumans() }}
                @if($prayerItem->visibility !== \App\Models\PrayerRequest::PUBLIC)
                · <i class="fa-solid {{ $prayerItem->visibility === 'private' ? 'fa-lock' : 'fa-user-group' }}" aria-hidden="true"></i> {{ \App\Models\PrayerRequest::visibilityLabels()[$prayerItem->visibility] }}
                @endif
            </p>
            {{-- Sous la ligne d'information et non à droite du nom : sur une carte étroite, le badge écrasait la colonne du texte. --}}
            @if($prayerItem->isAnswered())
            <span class="badge-validated mt-1.5">{{ __('prayer.common.answered') }}</span>
            @endif
        </div>
    </header>

    @if($prayerItem->isHidden())
    <p class="alert-warning text-sm"><i class="fa-solid fa-eye-slash mt-0.5" aria-hidden="true"></i><span>{{ $prayerItem->hidden_reason ?: __('prayer.common.hidden_default') }}</span></p>
    @endif

    <a href="{{ route('prayer.requests.show', $prayerItem->id) }}" class="block min-w-0 flex-1">
        <p class="text-[15px] leading-relaxed break-words whitespace-pre-line text-slate-800">{{ $compact ? Str::limit(\App\Services\Mentions::plain($prayerItem->body), 160) : Str::limit(\App\Services\Mentions::plain($prayerItem->body), 400) }}</p>
    </a>

    @if($prayerItem->event_id && $prayerItem->event && !($hideEvent ?? false))
    <a href="{{ route('events.show', $prayerItem->event_id) }}" class="truncate text-xs font-medium text-primary-700 hover:underline">
        <i class="fa-regular fa-calendar mr-1" aria-hidden="true"></i>{{ $prayerItem->event->title }}
    </a>
    @endif
    @if($prayerItem->source_testimony_id && $prayerItem->sourceTestimony)
    <a href="{{ route('testimonies.show', $prayerItem->source_testimony_id) }}" class="truncate text-xs font-medium text-primary-700 hover:underline">
        <i class="fa-solid fa-book-open mr-1" aria-hidden="true"></i>{{ __('prayer.common.after_testimony', ['title' => $prayerItem->sourceTestimony->title]) }}
    </a>
    @endif

    <footer class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
        @if($viewer && !$prayerItem->isHidden())
        {{-- Chaque appui ajoute une prière, après le temps de prière (resources/js/prayer-focus.js,
             docs/fonctionnalites/temps-de-priere.md, prier-plusieurs-fois.md) --}}
        <form method="POST" action="{{ route('prayer.requests.pray', $prayerItem->id) }}" data-loading-label="{{ __('prayer.common.saving') }}"
              data-prayer-focus="{{ route('prayer.requests.pray.start', $prayerItem->id) }}"
              data-prayer-excerpt="{{ Str::limit(\App\Services\Mentions::plain($prayerItem->body), 600) }}">
            @csrf
            <button type="submit" class="{{ $hasPrayed ? 'btn-primary' : 'btn-secondary' }} btn-sm" aria-pressed="{{ $hasPrayed ? 'true' : 'false' }}"
                    @if($prayWith && !$hasPrayed) title="{{ __('reactions.i_pray_with', ['name' => $prayWith->display_name]) }}" @endif>
                <i class="fa-solid fa-hands-praying" aria-hidden="true"></i>{{ $hasPrayed ? __('reactions.prayer_times.pray_again') : ($prayWith ? __('reactions.i_pray_with', ['name' => $prayWith->shortFirstName()]) : __('reactions.i_pray')) }}
            </button>
        </form>
        @endif
        {{-- Qui a prié, et combien de fois (docs/fonctionnalites/qui-a-reagi.md) --}}
        <button type="button" class="btn-ghost btn-sm" data-reaction-people="{{ route('reactions.people', ['kind' => 'prayer_request', 'id' => $prayerItem->id, 'type' => 'pray']) }}"
                aria-haspopup="dialog" title="{{ __('reactions.people.open_prayers') }}">
            <i class="fa-solid fa-users" aria-hidden="true"></i><span class="tabular-nums">{{ $prayerItem->prayer_count }}</span> {{ trans_choice('prayer.requests.card.praying', $prayerItem->prayer_count) }}
        </button>
        <a href="{{ route('prayer.requests.show', $prayerItem->id) }}#encouragements" class="btn-ghost btn-sm">
            <i class="fa-regular fa-comment" aria-hidden="true"></i><span class="tabular-nums">{{ $prayerItem->message_count }}</span>
            <span class="sr-only sm:not-sr-only">{{ trans_choice('prayer.requests.card.encouragements', $prayerItem->message_count) }}</span>
        </a>
        @if($viewer && $hasPrayed && !$prayerItem->isHidden())
        <div class="w-full">@include('components.prayer-times', ['times' => $prayerItem->prayerTimesOf($viewer), 'action' => route('prayer.requests.pray', $prayerItem->id), 'fields' => ['last' => 1]])</div>
        @endif
    </footer>
</article>

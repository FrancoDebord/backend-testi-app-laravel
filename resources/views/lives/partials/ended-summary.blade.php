{{--
    Direct terminé : bilan rendu côté serveur, comme l'écran de fin de l'application
    (testi_app › LiveEndedView + LiveAfterwardsSection). Voir docs/fonctionnalites/lives.md (« Directs passés »).
    Aucun élément data-live-* : live.js (mode « replay ») ne s'y branche pas.
--}}
@php
    $asHost   = $live->isHost(Auth::user());
    $reasonKey = in_array($live->end_reason, ['host', 'moderator', 'connection', 'abandoned'], true) ? $live->end_reason : 'default';
    $reason   = __('lives_replay.summary.reasons.' . $reasonKey);
    $duration = $live->durationSeconds();
    $counts   = $live->reactionCounts();
    $total    = array_sum($counts);

    $tiles = array_values(array_filter([
        $duration !== null ? ['fa-regular fa-clock', \App\Models\LiveSession::formatClock($duration), __('lives_replay.summary.stats.duration'), 'duration'] : null,
        ['fa-regular fa-eye', \App\Models\LiveSession::compactNumber((int) $live->peak_viewers), __('lives_replay.summary.stats.peak_viewers'), 'peak_viewers'],
        ['fa-regular fa-comment', \App\Models\LiveSession::compactNumber((int) $live->comment_count), __('lives_replay.summary.stats.comments'), 'comments'],
        ['fa-regular fa-heart', \App\Models\LiveSession::compactNumber($total), __('lives_replay.summary.stats.reactions'), 'reactions'],
    ]));

    // Émojis de l'application (LiveReactionType), du plus fréquent au moins fréquent, ceux à 0 masqués.
    $emojis = ['like' => '❤️', 'pray' => '🙏', 'amen' => '🙌', 'worship' => '🛐', 'fire' => '🔥'];
    $bars = collect($counts)->filter(fn ($n) => $n > 0)->sortDesc()
        ->map(fn ($n, $type) => [
            'type'  => $type,
            'emoji' => $emojis[$type] ?? '',
            'label' => __('lives.show.react_' . $type),
            'count' => $n,
            'share' => max(0.02, min(1, $n / max(1, $total))),
        ])->values();

    $approvedReplay = $live->record && $live->testimony && $live->testimony->status->value === 'approved';
    $reviewReplay   = $live->record && $live->testimony && !$approvedReplay && $live->canBeModeratedBy(Auth::user());
    $outlineBtn = 'inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-white/40 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white';
@endphp

<section class="overflow-hidden rounded-2xl bg-linear-to-b from-[#241640] to-[#120A1F] px-4 py-8 text-white shadow-card sm:px-6"
         aria-labelledby="live-ended-title" data-live-ended>
    <div class="mx-auto flex max-w-[480px] flex-col items-center">

        {{-- En-tête : avatar entouré, pastille « TERMINÉ » --}}
        <div class="relative">
            <div class="rounded-full border-2 border-white/25 p-[3px]">
                @if(!empty($live->host?->avatar_url))
                    <img src="{{ $live->host->avatar_url }}" alt="" class="h-[76px] w-[76px] rounded-full object-cover">
                @else
                    <span class="inline-flex h-[76px] w-[76px] items-center justify-center rounded-full bg-[#1E1530] text-2xl font-bold text-white/80" aria-hidden="true">{{ $live->host?->initials }}</span>
                @endif
            </div>
            <span class="absolute -bottom-2 left-1/2 -translate-x-1/2 rounded-md border-2 border-[#120A1F] bg-[#475467] px-[7px] py-[3px] text-[9.5px] leading-none font-extrabold tracking-[0.5px] whitespace-nowrap text-white">{{ __('lives_replay.summary.badge') }}</span>
        </div>

        <h2 id="live-ended-title" class="mt-[22px] text-center text-[22px] leading-tight font-extrabold text-white">
            {{ $asHost ? __('lives_replay.summary.your_ended') : __('lives_replay.summary.ended') }}
        </h2>
        <p class="mt-1.5 line-clamp-2 text-center text-[15px] font-semibold break-words text-white/85">{{ $live->title }}</p>
        <p class="mt-1 text-center text-[13px] text-white/60" data-live-end-reason>
            @if($asHost || !$live->host){{ $reason }}@else{{ $live->host->display_name }} · {{ $reason }}@endif
        </p>

        {{-- Chiffres clés --}}
        <h3 class="mt-[26px] mb-2.5 self-stretch text-[11px] font-bold tracking-[0.8px] text-white/55 uppercase">
            {{ $asHost ? __('lives_replay.summary.host_numbers') : __('lives_replay.summary.numbers') }}
        </h3>
        <ul class="grid w-full grid-cols-2 gap-2.5">
            @foreach($tiles as [$icon, $value, $label, $key])
            <li class="flex min-w-0 flex-col rounded-[14px] border border-white/[0.07] bg-[#1E1530] py-3 pr-3 pl-3.5" data-live-stat="{{ $key }}">
                <span class="mb-2.5 inline-flex h-[30px] w-[30px] items-center justify-center rounded-lg bg-accent-500/15 text-[15px] text-accent-500" aria-hidden="true"><i class="{{ $icon }}"></i></span>
                <span class="truncate text-[22px] leading-tight font-extrabold text-white tabular-nums">{{ $value }}</span>
                <span class="mt-0.5 line-clamp-2 text-xs text-white/60">{{ $label }}</span>
            </li>
            @endforeach
        </ul>

        {{-- Détail des réactions --}}
        @if($total > 0)
        <h3 class="mt-[22px] mb-2.5 self-stretch text-[11px] font-bold tracking-[0.8px] text-white/55 uppercase">{{ __('lives_replay.summary.reactions') }}</h3>
        <ul class="w-full rounded-[14px] border border-white/[0.07] bg-[#1E1530] px-3.5 py-2.5">
            @foreach($bars as $bar)
            <li class="flex items-center gap-2 py-[5px]" data-live-reaction-bar="{{ $bar['type'] }}">
                <span class="w-7 shrink-0 text-lg leading-none" aria-hidden="true">{{ $bar['emoji'] }}</span>
                <span class="w-[4.5rem] shrink-0 truncate text-[12.5px] text-white/70 sm:w-24">{{ $bar['label'] }}</span>
                <span class="h-2 min-w-0 flex-1 overflow-hidden rounded bg-white/[0.08]" role="presentation">
                    <span class="block h-full rounded bg-accent-500" style="width: {{ round($bar['share'] * 100, 1) }}%"></span>
                </span>
                <span class="w-12 shrink-0 text-right text-[12.5px] font-bold text-white tabular-nums">{{ \App\Models\LiveSession::compactNumber($bar['count']) }}</span>
            </li>
            @endforeach
        </ul>
        @endif

        {{-- Après le direct : témoigner, rediffusion, revoir la discussion --}}
        <div class="mt-[22px] flex w-full flex-col gap-2.5" data-live-testify>
            {{-- Compte requis (comme requireAccount dans l'application) : un visiteur passe par la connexion. --}}
            <a href="{{ Auth::check() ? route('publish', ['live' => $live->id]) : route('login') }}"
               class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-sun-400 px-4 py-2.5 text-center text-sm font-bold text-primary-700 transition hover:bg-sun-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sun-300">
                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>{{ __('lives_replay.testify.button') }}
            </a>
            @guest
                <p class="-mt-1 text-center text-xs text-white/60">{{ __('lives_replay.testify.login') }}
                    <a href="{{ route('login') }}" class="font-semibold text-sun-300 underline hover:text-sun-200">{{ __('lives.connect') }}</a></p>
            @endguest

            @if($approvedReplay)
                <a href="{{ route('testimonies.show', $live->testimony_id) }}" class="{{ $outlineBtn }}"><i class="fa-solid fa-play" aria-hidden="true"></i>{{ __('lives.show.see_replay') }}</a>
            @elseif($reviewReplay)
                <a href="{{ route('moderation.show', $live->testimony_id) }}" class="{{ $outlineBtn }}"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i>{{ __('lives.show.review_video') }}</a>
            @endif

            <a href="#live-chat-replay" class="{{ $outlineBtn }}" data-live-chat-replay-open>
                <i class="fa-regular fa-comments" aria-hidden="true"></i>{{ __('lives_replay.chat.title') }}
            </a>
        </div>
    </div>
</section>

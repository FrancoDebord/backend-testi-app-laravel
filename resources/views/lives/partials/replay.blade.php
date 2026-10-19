{{--
    Direct terminé : revoir la discussion (messages visibles avec leur moment dans le direct, « 12:30 » / « 1:02:05 »
    comme formatLiveOffset de l'application). Ancre #live-chat-replay : bouton « Revoir la discussion » du bilan
    (lives/partials/ended-summary). Voir docs/fonctionnalites/lives.md (« Directs passés »).
    $replay = ['comments' => LengthAwarePaginator (?chat=), 'testimonies' => Collection, 'testimoniesCount' => int]
--}}
@php
    $chat = $replay['comments'];
    $formatOffset = fn (?int $seconds): ?string => $seconds === null ? null : \App\Models\LiveSession::formatClock($seconds);
@endphp

<section id="live-chat-replay" class="card flex min-h-0 min-w-0 scroll-mt-24 flex-col" data-live-chat-replay aria-labelledby="live-chat-replay-title">
    <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
        <h2 id="live-chat-replay-title" class="card-title">{{ __('lives_replay.chat.title') }}</h2>
        <span class="text-xs text-slate-500">{{ trans_choice('lives_replay.chat.count', $chat->total(), ['count' => $chat->total()]) }}</span>
    </div>

    @if($chat->isEmpty())
        <p class="px-4 py-10 text-center text-sm text-slate-500">
            <i class="fa-regular fa-comments mb-2 block text-2xl text-slate-300" aria-hidden="true"></i>
            {{ __('lives_replay.chat.empty') }}
        </p>
    @else
        <ol class="max-h-[40rem] space-y-1.5 overflow-y-auto px-2 py-3">
            @foreach($chat as $comment)
            @php
                $offset = $formatOffset($live->offsetSecondsAt($comment->created_at));
                $isHostComment = $comment->user_id === $live->host_id;
            @endphp
            <li class="rounded-lg px-2 py-1.5 {{ $isHostComment ? 'bg-primary-50' : '' }}" data-replay-comment="{{ $comment->id }}">
                <div class="flex items-baseline gap-2">
                    @if($offset)
                    <time class="shrink-0 font-mono text-[11px] text-slate-400 tabular-nums" datetime="{{ $comment->created_at?->toIso8601String() }}"
                          title="{{ __('lives_replay.chat.at', ['time' => $offset]) }}">{{ $offset }}</time>
                    @endif
                    <p class="min-w-0 text-sm break-words text-slate-800">
                        <span class="font-semibold text-slate-900">{{ $comment->user?->display_name }}</span>
                        @if($isHostComment)<span class="badge-live ml-1 align-middle text-[10px]">{{ __('lives_replay.chat.host') }}</span>@endif
                        <span class="whitespace-pre-line">{{ \App\Services\Mentions::plain($comment->body) }}</span>
                    </p>
                </div>
            </li>
            @endforeach
        </ol>
        @if($chat->hasPages())
        <nav class="flex items-center justify-between gap-2 border-t border-slate-100 px-4 py-3 text-sm" aria-label="{{ __('lives_replay.chat.title') }}">
            @if($chat->onFirstPage())<span></span>@else
            <a href="{{ $chat->previousPageUrl() }}" class="btn-secondary btn-sm"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i>{{ __('lives_replay.chat.previous') }}</a>
            @endif
            <span class="text-xs text-slate-500">{{ $chat->currentPage() }} / {{ $chat->lastPage() }}</span>
            @if($chat->hasMorePages())
            <a href="{{ $chat->nextPageUrl() }}" class="btn-secondary btn-sm">{{ __('lives_replay.chat.next') }}<i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
            @else<span></span>@endif
        </nav>
        @endif
    @endif
</section>

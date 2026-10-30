{{--
    Liste en cours sur la page de lecture (docs/fonctionnalites/listes-de-temoignages.md) :
    @include('videos.partials.playlist-panel', ['suffix' => 'main'|'aside'])
    Variables de watch.blade.php : $playlist (TestimonyPlaylists::contextFor), $testimony, $pageRoute.
--}}
@php
    $list      = $playlist['playlist'];
    $listUrl   = fn ($t) => route($pageRoute, ['id' => $t->id, 'list' => $list->id]);
    $panelId   = 'playlist-panel-' . $suffix;
@endphp
<section class="card overflow-hidden" aria-labelledby="{{ $panelId }}-title" data-playlist-panel>
    <div class="border-b border-slate-100 p-4">
        <p class="flex items-center gap-1.5 text-xs font-medium text-slate-500">
            <i class="fa-solid fa-layer-group text-primary-600" aria-hidden="true"></i>{{ __('playlists.panel.label') }}
        </p>
        <h2 id="{{ $panelId }}-title" class="mt-1 font-semibold break-words text-slate-900">
            <a href="{{ route('playlists.show', $list->id) }}" class="hover:text-primary-600 hover:underline">{{ $list->title }}</a>
        </h2>
        <p class="mt-0.5 text-xs text-slate-500">
            {{ $list->user?->display_name }} · <span class="tabular-nums">{{ __('playlists.panel.position', ['position' => $playlist['index'] + 1, 'count' => $playlist['items']->count()]) }}</span>
        </p>
        <div class="mt-3 flex flex-wrap gap-2">
            @if($playlist['previous'])
            <a href="{{ $listUrl($playlist['previous']) }}" class="btn-ghost btn-sm" data-autoplay-id="{{ $playlist['previous']->id }}"><i class="fa-solid fa-backward-step" aria-hidden="true"></i>{{ __('playlists.panel.previous') }}</a>
            @endif
            @if($playlist['next'])
            <a href="{{ $listUrl($playlist['next']) }}" class="btn-secondary btn-sm" data-autoplay-id="{{ $playlist['next']->id }}"><i class="fa-solid fa-forward-step" aria-hidden="true"></i>{{ __('playlists.panel.next') }}</a>
            @endif
        </div>
    </div>
    <ol class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
        @foreach($playlist['items'] as $i => $item)
        @php
            $current   = $item->id === $testimony->id;
            $itemType  = $item->type->value;
            $thumbIcon = ['text' => 'fa-quote-left', 'audio' => 'fa-microphone'][$itemType] ?? 'fa-play';
        @endphp
        <li>
            <a href="{{ $listUrl($item) }}" data-autoplay-id="{{ $item->id }}" class="{{ $current ? 'bg-primary-50' : 'hover:bg-slate-50' }} flex items-center gap-3 p-2.5" @if($current) aria-current="true" @endif>
                <span class="w-5 shrink-0 text-center text-xs text-slate-500 tabular-nums">
                    @if($current)<i class="fa-solid fa-play text-primary-600" aria-hidden="true"></i><span class="sr-only">{{ __('playlists.panel.now_playing') }}</span>@else{{ $i + 1 }}@endif
                </span>
                <span class="{{ $itemType === 'text' ? 'bg-sun-50 text-sun-400' : 'bg-slate-900 text-slate-600' }} relative flex aspect-video w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg" aria-hidden="true">
                    @if($item->cover_url)
                    <img src="{{ $item->cover_url }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
                    @else
                    <i class="fa-solid {{ $thumbIcon }} text-xs"></i>
                    @endif
                </span>
                <span class="{{ $current ? 'text-primary-700' : 'text-slate-900' }} line-clamp-2 min-w-0 text-sm font-medium break-words">{{ $item->title }}</span>
            </a>
        </li>
        @endforeach
    </ol>
</section>

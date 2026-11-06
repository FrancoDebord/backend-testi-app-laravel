{{--
    Liste en cours sur la page de lecture (docs/fonctionnalites/listes-de-temoignages.md) :
    @include('videos.partials.playlist-panel', ['suffix' => 'main'|'aside'])
    Variables de watch.blade.php : $playlist (TestimonyPlaylists::contextFor), $testimony, $pageRoute.
    En-tête compact (libellé, titre, « auteur · 2 sur 5 »), Précédent / Suivant côte à côte (Suivant en bouton
    principal ; grisé en début ou fin de liste), puis les témoignages, celui en cours marqué d'un liseré.
    data-playlist-panel et data-autoplay-id : lus par resources/js/videos.js (lecture lancée à l'arrivée).
--}}
@php
    $list      = $playlist['playlist'];
    $listUrl   = fn ($t) => route($pageRoute, ['id' => $t->id, 'list' => $list->id]);
    $panelId   = 'playlist-panel-' . $suffix;
    $owner     = $list->user?->display_name;
    $position  = __('playlists.panel.position', ['position' => $playlist['index'] + 1, 'count' => $playlist['items']->count()]);
@endphp
<section class="card overflow-hidden" aria-labelledby="{{ $panelId }}-title" data-playlist-panel>
    <div class="p-4">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600" aria-hidden="true">
                <i class="fa-solid fa-layer-group"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="section-title">{{ __('playlists.panel.label') }}</p>
                <h2 id="{{ $panelId }}-title" class="mt-0.5 line-clamp-2 font-semibold break-words text-slate-900">
                    <a href="{{ route('playlists.show', $list->id) }}" class="hover:text-primary-600 hover:underline">{{ $list->title }}</a>
                </h2>
                <p class="mt-0.5 truncate text-xs text-slate-500">
                    @if($owner){{ $owner }} · @endif<span class="tabular-nums">{{ $position }}</span>
                </p>
            </div>
        </div>

        {{-- Précédent / Suivant : toujours à la même place ; sans précédent ou suivant, le bouton est grisé. --}}
        <div class="mt-4 grid grid-cols-2 gap-2">
            @if($playlist['previous'])
            <a href="{{ $listUrl($playlist['previous']) }}" class="btn-secondary btn-sm w-full" data-autoplay-id="{{ $playlist['previous']->id }}"
               title="{{ $playlist['previous']->title }}">
                <i class="fa-solid fa-backward-step" aria-hidden="true"></i>{{ __('playlists.panel.previous') }}
            </a>
            @else
            <span class="btn-secondary btn-sm w-full cursor-not-allowed opacity-50" aria-disabled="true">
                <i class="fa-solid fa-backward-step" aria-hidden="true"></i>{{ __('playlists.panel.previous') }}
            </span>
            @endif
            @if($playlist['next'])
            <a href="{{ $listUrl($playlist['next']) }}" class="btn-primary btn-sm w-full" data-autoplay-id="{{ $playlist['next']->id }}"
               title="{{ $playlist['next']->title }}">
                {{ __('playlists.panel.next') }}<i class="fa-solid fa-forward-step" aria-hidden="true"></i>
            </a>
            @else
            <span class="btn-primary btn-sm w-full cursor-not-allowed opacity-50" aria-disabled="true">
                {{ __('playlists.panel.next') }}<i class="fa-solid fa-forward-step" aria-hidden="true"></i>
            </span>
            @endif
        </div>
    </div>

    <ol class="max-h-96 overflow-y-auto border-t border-slate-100 py-1">
        @foreach($playlist['items'] as $i => $item)
        @php
            $current   = $item->id === $testimony->id;
            $itemType  = $item->type->value;
            $thumbIcon = ['text' => 'fa-quote-left', 'audio' => 'fa-microphone'][$itemType] ?? 'fa-play';
            $duration  = $itemType !== 'text' ? $item->durationLabel() : null;
        @endphp
        <li>
            <a href="{{ $listUrl($item) }}" data-autoplay-id="{{ $item->id }}"
               class="{{ $current ? 'border-primary-600 bg-primary-50' : 'border-transparent hover:bg-slate-50' }} flex items-center gap-3 border-l-4 py-2 pr-3 pl-2"
               @if($current) aria-current="true" @endif>
                <span class="w-5 shrink-0 text-center text-xs text-slate-500 tabular-nums">
                    @if($current)<i class="fa-solid fa-play text-primary-600" aria-hidden="true"></i><span class="sr-only">{{ __('playlists.panel.now_playing') }}</span>@else{{ $i + 1 }}@endif
                </span>
                <span class="{{ $itemType === 'text' ? 'bg-sun-50 text-sun-400' : 'bg-slate-900 text-slate-600' }} relative flex aspect-video w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg" aria-hidden="true">
                    @if($item->cover_url)
                    <img src="{{ $item->cover_url }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
                    @else
                    <i class="fa-solid {{ $thumbIcon }} text-xs"></i>
                    @endif
                    @if($duration)
                    <span class="absolute right-1 bottom-1 rounded bg-slate-900/80 px-1 text-[10px] font-medium text-white tabular-nums">{{ $duration }}</span>
                    @endif
                </span>
                <span class="min-w-0 flex-1">
                    <span class="{{ $current ? 'font-semibold text-primary-700' : 'font-medium text-slate-900' }} line-clamp-2 text-sm break-words">{{ $item->title }}</span>
                    @if($current)
                    <span class="mt-0.5 block text-xs font-medium text-primary-600" aria-hidden="true">{{ __('playlists.panel.now_playing') }}</span>
                    @endif
                </span>
            </a>
        </li>
        @endforeach
    </ol>
</section>

{{--
    Éléments d'une page de résultats (affichage initial et « Afficher plus »), en cartes ou en lignes compactes
    selon le choix de la personne (App\Support\FeedLayout). Le conteneur est rendu par components.testimony-list.
    Variables : $items, $routeName (page ouverte au clic, défaut videos.show), $tab (« shorts » : cartes verticales),
    $variant : « tile » par défaut (carte encadrée, videos/partials/tile) ; « card » pour l'ancienne carte sans cadre.
    $encourage (true) : un message « Témoigner » (components/encouragement) tous les config('encouragements.feed_every')
    témoignages, numéroté selon la position dans la liste entière (pages suivantes comprises) ; pas dans les shorts.
    $reasons : [id => 'following'|'suggested'] (Mon fil) ; « Suggestion » sur les témoignages suggérés.
    $inserts : éléments insérés (événements, requêtes de prière — feed/partials/insert) : un après le 3e témoignage
    puis tous les 6, selon la position dans la liste entière ; avec moins de 3 témoignages, ils suivent la liste.
    Même règle que l'application (lib/shared/content/feed_mix.dart). Pas dans les shorts.
--}}
@php
    $compact   = \App\Support\FeedLayout::isCompact();
    $routeName = $routeName ?? 'videos.show';
    $asShort   = ($tab ?? null) === 'shorts';
    $reasons   = $reasons ?? [];
    $every     = max(1, (int) config('encouragements.feed_every', 8));
    $encourage = ($encourage ?? false) && !$asShort;
    // Position du premier élément de la page dans la liste entière (choix des messages stable d'une page à l'autre).
    $offset    = $items instanceof \Illuminate\Contracts\Pagination\Paginator ? ($items->currentPage() - 1) * $items->perPage() : 0;
    $inserts   = $asShort ? [] : array_values(collect($inserts ?? [])->all());
    $insertAt  = fn (int $position) => $position >= 3 && ($position - 3) % 6 === 0 ? intdiv($position - 3, 6) : null;
@endphp
@foreach($items as $testimony)
    @if(($reasons[$testimony->id] ?? null) === 'suggested')
    <div class="relative min-w-0">
        <span class="badge-yellow pointer-events-none absolute top-2 left-2 z-10">Suggestion</span>
    @endif
    @if($compact)
        @include('videos.partials.row', ['testimony' => $testimony, 'url' => route($routeName, $testimony->id)])
    @elseif(!$asShort && ($variant ?? 'tile') === 'tile')
        @include('videos.partials.tile', ['testimony' => $testimony, 'url' => route($routeName, $testimony->id)])
    @else
        @include('videos.partials.card', ['testimony' => $testimony, 'short' => $asShort, 'large' => false, 'url' => route($routeName, $testimony->id)])
    @endif
    @if(($reasons[$testimony->id] ?? null) === 'suggested')
    </div>
    @endif
    @php $position = $offset + $loop->iteration; $n = $insertAt($position); @endphp
    @if($n !== null && isset($inserts[$n]))
        @include('feed.partials.insert', ['insert' => $inserts[$n]])
    @endif
    @if($encourage && $position % $every === 0)
        <x-encouragement variant="feed" :index="intdiv($position, $every) - 1" :compact="$compact" />
    @endif
@endforeach
{{-- Fil court (moins de 3 témoignages en tout) : les éléments insérés suivent la liste. --}}
@if($offset === 0 && count($items) < 3)
    @foreach($inserts as $insert)
        @include('feed.partials.insert', ['insert' => $insert])
    @endforeach
@endif

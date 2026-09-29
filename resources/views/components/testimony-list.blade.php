{{--
    Liste de témoignages en grandes cartes ou en liste compacte (docs/fonctionnalites/affichage-et-lecture.md) :
    @include('components.testimony-list', ['items' => $list, 'routeName' => 'testimonies.show', 'listId' => null, 'gridClass' => null, 'tab' => null])
    gridClass : classes de la grille en mode cartes (chaîne littérale, jamais construite), par défaut 1 à 4 colonnes.
    variant : « tile » pour les cartes encadrées de l'accueil (videos/partials/tile).
--}}
@php
    $compact   = \App\Support\FeedLayout::isCompact();
    $gridClass = $gridClass ?? 'grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4';
@endphp
<div @if(!empty($listId)) id="{{ $listId }}" @endif
     class="{{ $compact ? 'card divide-y divide-slate-100' : $gridClass }}" data-layout="{{ $compact ? 'compact' : 'cards' }}">
    @include('videos.partials.cards', ['items' => $items, 'routeName' => $routeName ?? 'testimonies.show', 'tab' => $tab ?? null, 'variant' => $variant ?? null])
</div>

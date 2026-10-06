{{--
    Liste de témoignages en grandes cartes ou en liste compacte (docs/fonctionnalites/affichage-et-lecture.md) :
    @include('components.testimony-list', ['items' => $list, 'routeName' => 'testimonies.show', 'listId' => null, 'gridClass' => null, 'tab' => null])
    gridClass : classes de la grille en mode cartes (chaîne littérale, jamais construite), par défaut 1 à 4 colonnes.
    variant : « tile » (par défaut, carte encadrée videos/partials/tile) ou « card ».
    encourage : true pour insérer un message « Témoigner » tous les config('encouragements.feed_every') témoignages
    (components/encouragement). reasons : [id => 'following'|'suggested'] (Mon fil, étiquette « Suggestion »).
    inserts : événements / requêtes de prière insérés entre les témoignages (voir videos/partials/cards).
--}}
@php
    $compact   = \App\Support\FeedLayout::isCompact();
    // Cartes encadrées (videos/partials/tile) : espacement régulier de 16 à 20 px.
    $gridClass = $gridClass ?? 'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 xl:gap-5';
@endphp
<div @if(!empty($listId)) id="{{ $listId }}" @endif
     class="{{ $compact ? 'card divide-y divide-slate-100' : $gridClass }}" data-layout="{{ $compact ? 'compact' : 'cards' }}">
    @include('videos.partials.cards', ['items' => $items, 'routeName' => $routeName ?? 'testimonies.show', 'tab' => $tab ?? null, 'variant' => $variant ?? null, 'encourage' => $encourage ?? false, 'reasons' => $reasons ?? [], 'inserts' => $inserts ?? []])
</div>

{{--
    Éléments d'une page de résultats (affichage initial et « Afficher plus »), en cartes ou en lignes compactes
    selon le choix de la personne (App\Support\FeedLayout). Le conteneur est rendu par components.testimony-list.
    Variables : $items, $routeName (page ouverte au clic, défaut videos.show), $tab (« shorts » : cartes verticales),
    $variant (« tile » : carte encadrée de l'accueil, videos/partials/tile).
--}}
@php
    $compact   = \App\Support\FeedLayout::isCompact();
    $routeName = $routeName ?? 'videos.show';
    $asShort   = ($tab ?? null) === 'shorts';
@endphp
@foreach($items as $testimony)
    @if($compact)
        @include('videos.partials.row', ['testimony' => $testimony, 'url' => route($routeName, $testimony->id)])
    @elseif(($variant ?? null) === 'tile' && !$asShort)
        @include('videos.partials.tile', ['testimony' => $testimony, 'url' => route($routeName, $testimony->id)])
    @else
        @include('videos.partials.card', ['testimony' => $testimony, 'short' => $asShort, 'large' => false, 'url' => route($routeName, $testimony->id)])
    @endif
@endforeach

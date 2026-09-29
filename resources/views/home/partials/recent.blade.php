{{--
    Accueil : « Témoignages récents » (ou « Résultats » avec un filtre), filtres par type,
    choix de l'affichage, cartes encadrées (videos/partials/tile) ou lignes compactes, pagination.
    Variables de la vue parente : $feed, $typeFilters, $hasFilters, $activeCategory ; $title.
--}}
<section class="card p-5" aria-labelledby="home-feed">
    <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-3">
        <h2 id="home-feed" class="card-title">{{ $title }}</h2>
        <nav class="order-last -mx-5 flex w-full min-w-0 basis-full gap-2 overflow-x-auto px-5 [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:px-0" aria-label="Filtrer par type">
            @foreach($typeFilters as $val => $label)
            <a href="{{ route('home', array_filter(['category' => request('category'), 'type' => $val ?: null])) }}"
               class="{{ (request('type') ?? '') === $val ? 'chip-active' : 'chip' }} text-xs" @if((request('type') ?? '') === $val) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
            @if($activeCategory)
            <a href="{{ route('home', array_filter(['type' => request('type')])) }}" class="chip-active text-xs" aria-label="Retirer le filtre {{ $activeCategory->name }}">
                {{ $activeCategory->name }}<i class="fa-solid fa-xmark text-[10px]" aria-hidden="true"></i>
            </a>
            @endif
        </nav>
        <div class="ml-auto flex items-center gap-3">
            @if($feed->isNotEmpty())@include('components.layout-toggle')@endif
            <a href="{{ route('explore') }}" class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">Voir tout<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
        </div>
    </div>

    @if($feed->isEmpty())
        @include('components.empty-state', [
            'title'       => "Aucun témoignage pour l'instant",
            'text'        => $hasFilters ? 'Aucun témoignage ne correspond à ces filtres.' : 'Les témoignages publiés apparaîtront ici.',
            'actionUrl'   => Auth::check() ? route('publish') : null,
            'actionLabel' => 'Publier un témoignage',
        ])
    @else
        <div class="mb-4">
            @include('components.testimony-list', [
                'items'     => $feed,
                'routeName' => 'testimonies.show',
                'variant'   => 'tile',
                'gridClass' => 'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4',
            ])
        </div>
        {{ $feed->appends(request()->query())->links() }}
    @endif
</section>

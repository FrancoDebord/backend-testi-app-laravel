@extends('layouts.app')
@section('title', 'Vidéos')
@php
    $header      = 'Vidéos';
    $subheader   = 'Témoignages en vidéo, shorts, directs, audios et textes : regardez, réagissez, commentez.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Vidéos'],
    ];
    // Paramètres conservés d'un filtre à l'autre.
    $params   = array_filter(['tab' => $tab !== 'all' ? $tab : null, 'sort' => $sort !== 'recent' ? $sort : null, 'q' => $q ?: null, 'category' => $category]);
    $isShorts = $tab === 'shorts';
    $filtered = $q !== '' || $category;
    $gridClass = $isShorts
        ? 'grid grid-cols-2 gap-x-3 gap-y-6 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6'
        : 'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 xl:gap-5';
@endphp

@section('content')
{{-- ── Recherche ─────────────────────────────────────────────────────── --}}
<form method="GET" action="{{ route('videos.index') }}" role="search" class="mb-5 flex max-w-2xl gap-2" data-loading-inline data-loading-label="Recherche…">
    @if($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
    @if($sort !== 'recent')<input type="hidden" name="sort" value="{{ $sort }}">@endif
    @if($category)<input type="hidden" name="category" value="{{ $category }}">@endif
    <label for="videos-q" class="sr-only">Rechercher une vidéo</label>
    <div class="relative min-w-0 flex-1">
        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
        <input id="videos-q" type="search" name="q" value="{{ $q }}" maxlength="100" autocomplete="off"
               class="form-input pl-9" placeholder="Titre, auteur, catégorie, mot-clé…">
    </div>
    <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass sm:hidden" aria-hidden="true"></i><span class="max-sm:sr-only">Rechercher</span></button>
</form>

{{-- ── Types de contenu ──────────────────────────────────────────────── --}}
<nav aria-label="Type de contenu" class="mb-4 border-b border-slate-200">
    <ul class="-mb-px flex gap-5 overflow-x-auto">
        @foreach(\App\Http\Controllers\Web\VideoController::TABS as $key => $label)
        <li>
            <a href="{{ route('videos.index', array_merge($params, ['tab' => $key === 'all' ? null : $key])) }}"
               class="{{ $tab === $key ? 'tab-active' : 'tab' }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
        </li>
        @endforeach
    </ul>
</nav>

{{-- ── Tri et catégories ─────────────────────────────────────────────── --}}
<div class="mb-6 space-y-3">
    <div class="flex flex-wrap gap-2" role="group" aria-label="Trier">
        @foreach(\App\Http\Controllers\Web\VideoController::SORTS as $key => $label)
        <a href="{{ route('videos.index', array_merge($params, ['sort' => $key === 'recent' ? null : $key])) }}"
           class="{{ $sort === $key ? 'chip-active' : 'chip' }}" @if($sort === $key) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </div>
    @if($categories->isNotEmpty())
    <div class="flex gap-2 overflow-x-auto pb-1" role="group" aria-label="Catégories">
        <a href="{{ route('videos.index', array_merge($params, ['category' => null])) }}"
           class="{{ !$category ? 'chip-active' : 'chip' }}" @if(!$category) aria-current="true" @endif>Toutes</a>
        @foreach($categories as $cat)
        <a href="{{ route('videos.index', array_merge($params, ['category' => $cat->slug])) }}"
           class="{{ $category === $cat->slug ? 'chip-active' : 'chip' }}" @if($category === $cat->slug) aria-current="true" @endif>{{ $cat->name }}</a>
        @endforeach
    </div>
    @endif
</div>

@if($q !== '')
<p class="mb-4 text-sm text-slate-600" role="status">
    {{ $items->total() }} résultat{{ $items->total() > 1 ? 's' : '' }} pour <span class="font-semibold text-slate-900">« {{ $q }} »</span>
</p>
@endif

{{-- ── Directs en cours ──────────────────────────────────────────────── --}}
@if($lives->isNotEmpty())
<section class="mb-8" aria-labelledby="videos-lives-title">
    <h2 id="videos-lives-title" class="card-title mb-3">En direct maintenant</h2>
    <div class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach($lives as $live)
            @include('videos.partials.live-card', ['live' => $live])
        @endforeach
    </div>
</section>
@endif

{{-- ── Grille ────────────────────────────────────────────────────────── --}}
@if($items->isEmpty())
    @if($filtered)
        @include('components.empty-state', [
            'title'       => 'Aucune vidéo ne correspond à votre recherche.',
            'text'        => "Vérifiez l'orthographe, essayez d'autres mots-clés ou retirez le filtre de catégorie.",
            'actionUrl'   => route('videos.index', array_filter(['tab' => $tab !== 'all' ? $tab : null])),
            'actionLabel' => 'Effacer la recherche',
        ])
    @elseif($lives->isEmpty())
        @include('components.empty-state', [
            'title' => $tab === 'lives' ? 'Aucun direct ni rediffusion pour le moment.' : 'Aucune vidéo disponible pour le moment.',
            'text'  => 'Les nouveaux témoignages apparaîtront ici dès leur publication.',
        ])
    @endif
@else
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        @if($lives->isNotEmpty())<h2 class="card-title">{{ $tab === 'lives' ? 'Rediffusions' : 'Toutes les publications' }}</h2>@endif
        <div class="ml-auto">@include('components.layout-toggle')</div>
    </div>
    @include('components.testimony-list', ['items' => $items, 'routeName' => 'videos.show', 'listId' => 'videos-grid', 'gridClass' => $gridClass, 'tab' => $tab, 'encourage' => true])

    @if($items->hasMorePages())
    <div class="mt-8 flex justify-center">
        {{-- Sans JavaScript : lien vers la page suivante. Avec : les cartes suivantes sont ajoutées à la grille. --}}
        <a href="{{ $items->nextPageUrl() }}" class="btn-secondary" data-load-more="videos-grid">Afficher plus</a>
    </div>
    @endif
@endif
@endsection

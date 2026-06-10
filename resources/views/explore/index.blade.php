@extends('layouts.app')
@section('title', 'Explorer')

@section('content')
<div class="container-fluid px-4 py-4">

    <h4 class="fw-bold mb-4"><i class="bi bi-search me-2 text-primary"></i>Explorer les témoignages</h4>

    {{-- Search --}}
    <form method="GET" action="{{ route('explore') }}" class="mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" name="q" value="{{ $q }}" class="form-control border-start-0 ps-1"
                           placeholder="Rechercher des témoignages, auteurs...">
                </div>
            </div>
            <div class="col-auto">
                <select name="type" class="form-select form-select-lg">
                    <option value="">Tous les types</option>
                    <option value="text" {{ $type === 'text' ? 'selected' : '' }}>📝 Texte</option>
                    <option value="audio" {{ $type === 'audio' ? 'selected' : '' }}>🎵 Audio</option>
                    <option value="video" {{ $type === 'video' ? 'selected' : '' }}>🎬 Vidéo</option>
                </select>
            </div>
            <div class="col-auto">
                <select name="sort" class="form-select form-select-lg">
                    <option value="recent" {{ $sort === 'recent' ? 'selected' : '' }}>🕐 Récent</option>
                    <option value="popular" {{ $sort === 'popular' ? 'selected' : '' }}>🔥 Populaire</option>
                    <option value="recommended" {{ $sort === 'recommended' ? 'selected' : '' }}>⭐ Recommandé</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-lg px-4">
                    <i class="bi bi-search me-1"></i>Rechercher
                </button>
            </div>
        </div>

        {{-- Category chips --}}
        <div class="d-flex flex-wrap gap-2 mt-3">
            <a href="{{ route('explore', ['q' => $q, 'type' => $type, 'sort' => $sort]) }}"
               class="btn btn-sm {{ !$category ? 'btn-primary' : 'btn-outline-secondary' }} rounded-pill">
                Toutes catégories
            </a>
            @foreach($categories as $cat)
            <a href="{{ route('explore', ['q' => $q, 'type' => $type, 'sort' => $sort, 'category' => $cat->slug]) }}"
               class="btn btn-sm {{ $category === $cat->slug ? 'btn-primary' : 'btn-outline-secondary' }} rounded-pill">
                {{ $cat->icon }} {{ $cat->name }}
            </a>
            @endforeach
        </div>
    </form>

    {{-- Results count --}}
    @if($q)
    <p class="text-muted mb-3"><i class="bi bi-info-circle me-1"></i>{{ $results->total() }} résultat(s) pour <strong>« {{ $q }} »</strong></p>
    @endif

    {{-- Results --}}
    @if($results->isEmpty())
    <div class="text-center py-5 text-muted">
        <i class="bi bi-search" style="font-size:4rem;opacity:.2;"></i>
        <p class="mt-3 fs-5 fw-semibold">Aucun résultat trouvé</p>
        <p class="small">Essayez d'autres mots-clés ou catégories.</p>
    </div>
    @else
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-4 g-4 mb-4">
        @foreach($results as $testimony)
        <div class="col">@include('components.testimony-card', ['testimony' => $testimony])</div>
        @endforeach
    </div>
    <div class="d-flex justify-content-center">{{ $results->withQueryString()->links() }}</div>
    @endif
</div>
@endsection

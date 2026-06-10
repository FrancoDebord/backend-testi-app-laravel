@extends('layouts.app')
@section('title', 'Accueil')

@section('content')

{{-- Daily verse banner --}}
@if($verse)
<div class="text-white text-center py-3 px-4" style="background:linear-gradient(135deg,#6366f1,#7c3aed);">
    <i class="bi bi-book-fill me-2 opacity-75"></i>
    <em>« {{ $verse->verse_text }} »</em>
    <strong class="d-block opacity-75" style="font-size:.85rem;margin-top:4px;">— {{ $verse->reference }}</strong>
</div>
@endif

<div class="container-fluid px-4 py-4">

    {{-- Featured carousel --}}
    @if($featured->isNotEmpty())
    <div class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="fw-bold mb-0"><i class="bi bi-star-fill text-warning me-2"></i>À la une</h5>
            <a href="{{ route('explore') }}" class="btn btn-sm btn-outline-primary">Tout explorer <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
        <div id="featuredCarousel" class="carousel slide rounded-3 overflow-hidden shadow" data-bs-ride="carousel">
            <div class="carousel-indicators">
                @foreach($featured->take(5) as $i => $t)
                <button type="button" data-bs-target="#featuredCarousel" data-bs-slide-to="{{ $i }}" {{ $i===0 ? 'class=active' : '' }}></button>
                @endforeach
            </div>
            <div class="carousel-inner">
                @foreach($featured->take(5) as $i => $t)
                <div class="carousel-item {{ $i===0 ? 'active' : '' }}" style="height:300px;">
                    @if($t->cover_url)
                    <img src="{{ $t->cover_url }}" class="d-block w-100 h-100" style="object-fit:cover;filter:brightness(.55);" alt="">
                    @else
                    <div class="w-100 h-100" style="background:linear-gradient(135deg,#1e1b4b,#4c1d95);"></div>
                    @endif
                    <div class="carousel-caption text-start pb-4">
                        <span class="badge bg-warning text-dark mb-2">⭐ À la une</span>
                        <h4 class="fw-bold">{{ $t->title }}</h4>
                        @if($t->body_text)<p class="d-none d-md-block opacity-75 small">{{ Str::limit($t->body_text, 120) }}</p>@endif
                        <a href="{{ route('testimonies.show', $t->id) }}" class="btn btn-light btn-sm fw-semibold">
                            Lire le témoignage <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#featuredCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#featuredCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
            </button>
        </div>
    </div>
    @endif

    {{-- Category + type filter --}}
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <a href="{{ route('home') }}" class="btn btn-sm {{ !request('category') ? 'btn-primary' : 'btn-outline-secondary' }} rounded-pill">Tout</a>
        @foreach($categories as $cat)
        <a href="{{ route('home', array_merge(request()->query(), ['category' => $cat->slug, 'page' => null])) }}"
           class="btn btn-sm {{ request('category') === $cat->slug ? 'btn-primary' : 'btn-outline-secondary' }} rounded-pill">
            {{ $cat->icon }} {{ $cat->name }}
        </a>
        @endforeach
    </div>

    <div class="d-flex gap-2 mb-4">
        @foreach(['' => ['Tous', 'bi-grid'], 'text' => ['Texte', 'bi-file-text'], 'audio' => ['Audio', 'bi-mic'], 'video' => ['Vidéo', 'bi-camera-video']] as $val => [$label, $icon])
        <a href="{{ route('home', array_merge(request()->except('page'), ['type' => $val ?: null])) }}"
           class="btn btn-sm {{ (request('type') ?? '') === $val ? 'btn-dark' : 'btn-light' }}">
            <i class="bi {{ $icon }} me-1"></i>{{ $label }}
        </a>
        @endforeach
    </div>

    {{-- Feed --}}
    @if($feed->isEmpty())
    <div class="text-center py-5 text-muted">
        <i class="bi bi-journal-x" style="font-size:4rem;opacity:.25;"></i>
        <p class="mt-3 fs-5 fw-semibold">Aucun témoignage pour l'instant</p>
        @auth
        <a href="{{ route('publish') }}" class="btn btn-primary mt-2"><i class="bi bi-plus-lg me-1"></i>Soyez le premier !</a>
        @endauth
    </div>
    @else
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-4 g-4 mb-4">
        @foreach($feed as $testimony)
        <div class="col">@include('components.testimony-card', ['testimony' => $testimony])</div>
        @endforeach
    </div>
    <div class="d-flex justify-content-center">
        {{ $feed->appends(request()->query())->links() }}
    </div>
    @endif
</div>
@endsection

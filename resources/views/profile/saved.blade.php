@extends('layouts.app')
@section('title', 'Témoignages sauvegardés')

@section('content')
<div class="container-fluid px-4 py-4">

    <h4 class="fw-bold mb-4">
        <i class="bi bi-bookmark-fill text-warning me-2"></i>Témoignages sauvegardés
    </h4>

    @if($testimonies->isEmpty())
    <div class="text-center py-5 text-muted">
        <i class="bi bi-bookmark display-4 d-block mb-3 opacity-25"></i>
        <p class="mb-3">Vous n'avez aucun témoignage sauvegardé.</p>
        <a href="{{ route('explore') }}" class="btn btn-outline-primary">
            <i class="bi bi-compass me-1"></i>Explorer des témoignages
        </a>
    </div>
    @else
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-4 g-4 mb-4">
        @foreach($testimonies as $testimony)
        <div class="col">
            @include('components.testimony-card', ['testimony' => $testimony])
        </div>
        @endforeach
    </div>
    <div class="d-flex justify-content-center">
        {{ $testimonies->links() }}
    </div>
    @endif
</div>
@endsection

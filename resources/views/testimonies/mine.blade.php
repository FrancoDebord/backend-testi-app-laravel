@extends('layouts.app')
@section('title', 'Mes témoignages')

@section('content')
<div class="container-fluid px-4 py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="fw-bold mb-0">
            <i class="bi bi-journal-text text-primary me-2"></i>Mes témoignages
        </h4>
        <a href="{{ route('publish') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nouveau témoignage
        </a>
    </div>

    {{-- Status tabs --}}
    <ul class="nav nav-pills mb-4 gap-1">
        @foreach(['all' => 'Tous', 'approved' => 'Publiés', 'pending' => 'En attente', 'rejected' => 'Rejetés', 'draft' => 'Brouillons'] as $val => $label)
        <li class="nav-item">
            <a href="{{ route('testimonies.mine', ['status' => $val]) }}"
               class="nav-link {{ ($status === $val || (!$status && $val === 'all')) ? 'active' : '' }}">
                @if($val === 'approved')<i class="bi bi-check-circle me-1"></i>
                @elseif($val === 'pending')<i class="bi bi-clock me-1"></i>
                @elseif($val === 'rejected')<i class="bi bi-x-circle me-1"></i>
                @elseif($val === 'draft')<i class="bi bi-file-earmark me-1"></i>
                @endif
                {{ $label }}
            </a>
        </li>
        @endforeach
    </ul>

    @if($testimonies->isEmpty())
    <div class="text-center py-5 text-muted">
        <i class="bi bi-journal-text display-4 d-block mb-3 opacity-25"></i>
        <p class="mb-3">Vous n'avez pas encore de témoignage dans cette catégorie.</p>
        <a href="{{ route('publish') }}" class="btn btn-primary">
            Publier mon premier témoignage
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
        {{ $testimonies->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection

@extends('layouts.app')
@section('title', $profile->display_name)

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- Header card --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row align-items-center align-items-md-start gap-4">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:90px;height:90px;font-size:2rem;font-weight:800;">
                    {{ $profile->initials }}
                </div>
                <div class="flex-grow-1 text-center text-md-start">
                    <h4 class="fw-bold mb-1">{{ $profile->display_name }}</h4>
                    @if($profile->country)<p class="text-muted small mb-1"><i class="bi bi-geo-alt me-1"></i>{{ $profile->country }}</p>@endif
                    @if($profile->bio)<p class="text-muted small mb-2">{{ $profile->bio }}</p>@endif

                    <div class="d-flex gap-4 justify-content-center justify-content-md-start mb-3">
                        <div class="text-center"><span class="fw-bold fs-5">{{ $profile->testimony_count }}</span><br><small class="text-muted">Témoignages</small></div>
                        <div class="text-center"><span class="fw-bold fs-5">{{ $profile->follower_count }}</span><br><small class="text-muted">Abonnés</small></div>
                        <div class="text-center"><span class="fw-bold fs-5">{{ $profile->following_count }}</span><br><small class="text-muted">Abonnements</small></div>
                    </div>

                    @auth
                    @if($isOwner)
                    <a href="{{ route('profile.edit') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-pencil me-1"></i>Modifier le profil
                    </a>
                    @else
                    <form method="POST" action="{{ $isFollowing ? route('users.unfollow', $profile->id) : route('users.follow', $profile->id) }}" class="d-inline">
                        @csrf
                        @if($isFollowing)@method('DELETE')@endif
                        <button type="submit" class="btn btn-sm {{ $isFollowing ? 'btn-outline-secondary' : 'btn-primary' }}">
                            <i class="bi {{ $isFollowing ? 'bi-person-dash' : 'bi-person-plus' }} me-1"></i>
                            {{ $isFollowing ? 'Se désabonner' : "S'abonner" }}
                        </button>
                    </form>
                    @endif
                    @endauth
                </div>
            </div>
        </div>
    </div>

    {{-- Testimonies --}}
    <h5 class="fw-bold mb-3">Témoignages de {{ $profile->display_name }}</h5>
    @if($testimonies->isEmpty())
    <div class="text-center py-5 text-muted">
        <i class="bi bi-journal-x" style="font-size:3.5rem;opacity:.2;"></i>
        <p class="mt-3">Aucun témoignage publié.</p>
    </div>
    @else
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-4 g-4 mb-4">
        @foreach($testimonies as $testimony)
        <div class="col">@include('components.testimony-card', ['testimony' => $testimony])</div>
        @endforeach
    </div>
    <div class="d-flex justify-content-center">{{ $testimonies->links() }}</div>
    @endif
</div>
@endsection

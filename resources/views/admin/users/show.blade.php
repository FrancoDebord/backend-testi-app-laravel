@extends('layouts.admin')
@section('title', $user->display_name)
@section('page-title', 'Profil utilisateur')

@section('content')
<div class="row g-4">

    {{-- User card --}}
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body p-4">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3"
                     style="width:64px;height:64px;font-size:1.4rem;font-weight:700;">
                    {{ $user->initials }}
                </div>
                <h5 class="fw-bold mb-1">{{ $user->display_name }}</h5>
                <p class="text-muted small mb-1">{{ $user->email ?? $user->phone }}</p>
                @if($user->country)
                <p class="text-muted small mb-3"><i class="bi bi-geo-alt"></i> {{ $user->country }}</p>
                @endif

                <div class="row g-2 text-center border-top pt-3 mt-1">
                    <div class="col-4">
                        <p class="fw-bold mb-0">{{ $user->testimony_count }}</p>
                        <p class="text-muted" style="font-size:.72rem;">Témoin.</p>
                    </div>
                    <div class="col-4">
                        <p class="fw-bold mb-0">{{ $user->follower_count }}</p>
                        <p class="text-muted" style="font-size:.72rem;">Abonnés</p>
                    </div>
                    <div class="col-4">
                        <p class="fw-bold mb-0">{{ $user->like_count }}</p>
                        <p class="text-muted" style="font-size:.72rem;">Likes</p>
                    </div>
                </div>

                <p class="text-muted mt-3 mb-0" style="font-size:.75rem;">
                    Inscrit le {{ $user->created_at->format('d M Y') }}
                </p>
            </div>
        </div>
    </div>

    {{-- Edit form + testimonies --}}
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-semibold mb-3">Modifier le compte</h6>
                <form method="POST" action="{{ route('admin.users.update', $user->id) }}">
                    @csrf @method('PUT')
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium">Rôle</label>
                            <select name="role" class="form-select form-select-sm">
                                @foreach(['visiteur' => 'Visiteur', 'utilisateur' => 'Utilisateur', 'moderateur' => 'Modérateur', 'administrateur' => 'Administrateur'] as $v => $l)
                                <option value="{{ $v }}" {{ $user->role->value === $v ? 'selected' : '' }}>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium">Statut</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="active" {{ $user->status->value === 'active' ? 'selected' : '' }}>Actif</option>
                                <option value="suspended" {{ $user->status->value === 'suspended' ? 'selected' : '' }}>Suspendu</option>
                                <option value="banned" {{ $user->status->value === 'banned' ? 'selected' : '' }}>Banni</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm px-4">
                        <i class="bi bi-check-lg me-1"></i>Enregistrer
                    </button>
                </form>

                <hr class="my-4">

                <h6 class="fw-semibold mb-3">Derniers témoignages</h6>
                @forelse($testimonies as $t)
                <div class="d-flex align-items-center gap-3 py-2 border-bottom">
                    <div class="flex-grow-1 min-w-0">
                        <p class="small fw-medium mb-0 text-truncate">{{ $t->title }}</p>
                        <p class="text-muted mb-0" style="font-size:.72rem;">{{ $t->created_at->format('d M Y') }}</p>
                    </div>
                    <span class="badge {{ $t->status->badgeClass() }} small">{{ $t->status->label() }}</span>
                </div>
                @empty
                <p class="text-muted small">Aucun témoignage</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Inscription')

@section('content')
<div class="min-vh-100 d-flex align-items-center justify-content-center py-5" style="background:linear-gradient(135deg,#ede9fe 0%,#e0e7ff 100%);">
    <div class="col-11 col-sm-9 col-md-6 col-lg-5 col-xl-4">
        <div class="text-center mb-4">
            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:56px;height:56px;background:linear-gradient(135deg,#6366f1,#7c3aed);border-radius:16px;">
                <span class="text-white fw-bold fs-4">T</span>
            </div>
            <h4 class="fw-bold">Rejoindre TestiApp</h4>
            <p class="text-muted small">Créez votre compte gratuitement</p>
        </div>

        <div class="card border-0 shadow-lg">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4">Créer un compte</h5>

                @if($errors->any())
                <div class="alert alert-danger py-2 small">
                    @foreach($errors->all() as $e)<p class="mb-0">{{ $e }}</p>@endforeach
                </div>
                @endif

                <form method="POST" action="{{ route('register') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nom complet *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
                            <input type="text" name="display_name" value="{{ old('display_name') }}" required
                                   class="form-control border-start-0 ps-0 @error('display_name') is-invalid @enderror" placeholder="Jean Dupont">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Email *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                            <input type="email" name="email" value="{{ old('email') }}" required
                                   class="form-control border-start-0 ps-0 @error('email') is-invalid @enderror" placeholder="vous@exemple.com">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Pays</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-globe text-muted"></i></span>
                            <input type="text" name="country" value="{{ old('country') }}"
                                   class="form-control border-start-0 ps-0" placeholder="Bénin, Côte d'Ivoire...">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col">
                            <label class="form-label fw-semibold small">Mot de passe *</label>
                            <input type="password" name="password" required minlength="8"
                                   class="form-control @error('password') is-invalid @enderror" placeholder="Min. 8 caractères">
                        </div>
                        <div class="col">
                            <label class="form-label fw-semibold small">Confirmation *</label>
                            <input type="password" name="password_confirmation" required class="form-control" placeholder="••••••••">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                        <i class="bi bi-person-plus me-2"></i>Créer mon compte
                    </button>
                </form>

                <hr class="my-3">
                <p class="text-center text-muted small mb-0">
                    Déjà inscrit ?
                    <a href="{{ route('login') }}" class="text-primary fw-semibold text-decoration-none">Se connecter</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

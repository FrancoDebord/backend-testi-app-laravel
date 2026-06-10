@extends('layouts.app')
@section('title', 'Connexion')

@section('content')
<div class="min-vh-100 d-flex align-items-center justify-content-center py-5" style="background:linear-gradient(135deg,#ede9fe 0%,#e0e7ff 100%);">
    <div class="col-11 col-sm-8 col-md-5 col-lg-4 col-xl-3">
        <div class="text-center mb-4">
            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:56px;height:56px;background:linear-gradient(135deg,#6366f1,#7c3aed);border-radius:16px;">
                <span class="text-white fw-bold fs-4">T</span>
            </div>
            <h4 class="fw-bold">Bienvenue sur TestiApp</h4>
            <p class="text-muted small">Partagez la gloire de Dieu</p>
        </div>

        <div class="card border-0 shadow-lg">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4">Connexion</h5>

                @if($errors->any())
                <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
                @endif
                @if(session('status'))
                <div class="alert alert-success py-2 small">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                            <input type="email" name="email" value="{{ old('email') }}" required
                                   class="form-control border-start-0 ps-0" placeholder="vous@exemple.com" autofocus>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold small mb-0">Mot de passe</label>
                            <a href="{{ route('password.request') }}" class="small text-primary text-decoration-none">Oublié ?</a>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                            <input type="password" name="password" required class="form-control border-start-0 ps-0" placeholder="••••••••">
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="remember" id="remember" class="form-check-input">
                        <label for="remember" class="form-check-label small">Se souvenir de moi</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
                    </button>
                </form>

                <hr class="my-3">
                <p class="text-center text-muted small mb-0">
                    Pas de compte ?
                    <a href="{{ route('register') }}" class="text-primary fw-semibold text-decoration-none">S'inscrire</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

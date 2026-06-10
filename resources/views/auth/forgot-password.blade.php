@extends('layouts.app')
@section('title', 'Mot de passe oublié')

@section('content')
<div class="min-vh-100 d-flex align-items-center justify-content-center py-5" style="background:linear-gradient(135deg,#ede9fe 0%,#e0e7ff 100%);">
    <div class="col-11 col-sm-8 col-md-5 col-lg-4 col-xl-3">
        <div class="card border-0 shadow-lg">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1">Mot de passe oublié</h5>
                <p class="text-muted small mb-4">Entrez votre email pour recevoir un lien de réinitialisation.</p>

                @if(session('status'))
                <div class="alert alert-success small py-2">{{ session('status') }}</div>
                @endif
                @if($errors->any())
                <div class="alert alert-danger small py-2">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Adresse email</label>
                        <input type="email" name="email" required class="form-control" placeholder="vous@exemple.com">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-semibold py-2">
                        <i class="bi bi-send me-2"></i>Envoyer le lien
                    </button>
                </form>
                <div class="text-center mt-3">
                    <a href="{{ route('login') }}" class="small text-primary text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i>Retour à la connexion
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

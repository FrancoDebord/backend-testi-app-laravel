@extends('layouts.guest')
@section('title', 'Mot de passe oublié')

@section('content')
<div class="card p-6 sm:p-8">
    <h1 class="text-xl font-semibold text-primary-600">Mot de passe oublié</h1>
    <p class="mt-1 text-sm text-slate-500">Indiquez votre adresse e-mail : nous vous enverrons un lien de réinitialisation.</p>

    @if(session('status'))
    <div class="alert-success mt-5" role="status"><i class="fa-solid fa-circle-check mt-0.5"></i><p>{{ session('status') }}</p></div>
    @endif
    @if($errors->any())
    <div class="alert-error mt-5" role="alert"><i class="fa-solid fa-circle-exclamation mt-0.5"></i><p>{{ $errors->first() }}</p></div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4" data-loading-label="Envoi du lien…">
        @csrf
        <div>
            <label for="email" class="form-label">Adresse e-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="form-input" placeholder="vous@exemple.com">
        </div>
        <button type="submit" class="btn-primary w-full">Envoyer le lien</button>
    </form>

    <p class="mt-6 border-t border-slate-100 pt-4 text-center text-sm">
        <a href="{{ route('login') }}" class="text-slate-500 hover:text-slate-900 hover:underline">
            <i class="fa-solid fa-arrow-left mr-1"></i>Retour à la connexion
        </a>
    </p>
</div>
@endsection

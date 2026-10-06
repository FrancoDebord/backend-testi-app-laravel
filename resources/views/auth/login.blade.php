@extends('layouts.guest')
@section('title', 'Connexion')

@section('content')
<div class="card p-6 sm:p-8">
    <h1 class="text-h3">Connexion</h1>
    <p class="text-secondary mt-2">Heureux de vous revoir. Accédez à votre espace TestiApp.</p>

    @if(session('status'))
    <div class="alert-success mt-6" role="status"><i class="fa-solid fa-circle-check mt-0.5"></i><p>{{ session('status') }}</p></div>
    @endif
    @if($errors->any())
    <div class="alert-error mt-6" role="alert"><i class="fa-solid fa-circle-exclamation mt-0.5"></i><p>{{ $errors->first() }}</p></div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5" data-loading-label="Connexion…">
        @csrf
        <div>
            <label for="email" class="form-label">Adresse e-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="form-input" placeholder="vous@exemple.com" @if($errors->has('email')) aria-invalid="true" @endif>
        </div>
        <div>
            <div class="mb-1 flex items-center justify-between gap-2">
                <label for="password" class="form-label mb-0">Mot de passe</label>
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-primary-600 hover:text-primary-700 hover:underline">Mot de passe oublié ?</a>
            </div>
            <div class="relative">
                <input id="password" type="password" name="password" required autocomplete="current-password" class="form-input pr-12">
                <button type="button" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-lg text-slate-500 hover:text-primary-600 focus-visible:outline-2 focus-visible:outline-primary-600"
                        data-reveal="password" data-reveal-label="le mot de passe" aria-label="Afficher le mot de passe">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="remember" class="rounded">
            Se souvenir de moi
        </label>
        <button type="submit" class="btn-primary w-full">Se connecter<i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
    </form>

    <p class="mt-8 border-t border-slate-200 pt-5 text-center text-sm text-slate-500">
        Pas encore de compte ?
        <a href="{{ route('register') }}" class="font-semibold text-primary-600 hover:text-primary-700 hover:underline">Créer un compte</a>
    </p>
</div>
@endsection

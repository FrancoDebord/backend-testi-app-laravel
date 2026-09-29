@extends('layouts.guest')
@section('title', 'Connexion')

@section('content')
<div class="card p-6 sm:p-8">
    <h1 class="text-xl font-semibold text-primary-600">Connexion</h1>
    <p class="mt-1 text-sm text-slate-500">Accédez à votre espace TestiApp.</p>

    @if(session('status'))
    <div class="alert-success mt-5" role="status"><i class="fa-solid fa-circle-check mt-0.5"></i><p>{{ session('status') }}</p></div>
    @endif
    @if($errors->any())
    <div class="alert-error mt-5" role="alert"><i class="fa-solid fa-circle-exclamation mt-0.5"></i><p>{{ $errors->first() }}</p></div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4" data-loading-label="Connexion…">
        @csrf
        <div>
            <label for="email" class="form-label">Adresse e-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="form-input" placeholder="vous@exemple.com">
        </div>
        <div>
            <div class="mb-1 flex items-center justify-between gap-2">
                <label for="password" class="form-label mb-0">Mot de passe</label>
                <a href="{{ route('password.request') }}" class="text-xs text-slate-500 hover:text-slate-900 hover:underline">Mot de passe oublié ?</a>
            </div>
            <input id="password" type="password" name="password" required autocomplete="current-password" class="form-input">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="remember" class="rounded">
            Se souvenir de moi
        </label>
        <button type="submit" class="btn-primary w-full">Se connecter</button>
    </form>

    <p class="mt-6 border-t border-slate-100 pt-4 text-center text-sm text-slate-500">
        Pas encore de compte ?
        <a href="{{ route('register') }}" class="font-medium text-slate-900 hover:underline">Créer un compte</a>
    </p>
</div>
@endsection

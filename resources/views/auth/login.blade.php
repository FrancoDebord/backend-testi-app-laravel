@extends('layouts.guest')
@section('title', __('account.login.title'))

@section('content')
<div class="card p-6 sm:p-8">
    <h1 class="text-h3">{{ __('account.login.title') }}</h1>
    <p class="text-secondary mt-2">{{ __('account.login.intro') }}</p>

    @if(session('status'))
    <div class="alert-success mt-6" role="status"><i class="fa-solid fa-circle-check mt-0.5"></i><p>{{ session('status') }}</p></div>
    @endif
    @if($errors->any())
    <div class="alert-error mt-6" role="alert"><i class="fa-solid fa-circle-exclamation mt-0.5"></i><p>{{ $errors->first() }}</p></div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5" data-loading-label="{{ __('account.login.loading') }}">
        @csrf
        <div>
            <label for="email" class="form-label">{{ __('account.fields.email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="form-input" placeholder="{{ __('account.fields.email_placeholder') }}" @if($errors->has('email')) aria-invalid="true" @endif>
        </div>
        <div>
            <div class="mb-1 flex items-center justify-between gap-2">
                <label for="password" class="form-label mb-0">{{ __('account.fields.password') }}</label>
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-primary-600 hover:text-primary-700 hover:underline">{{ __('account.login.forgot') }}</a>
            </div>
            <div class="relative">
                <input id="password" type="password" name="password" required autocomplete="current-password" class="form-input pr-12">
                <button type="button" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-lg text-slate-500 hover:text-primary-600 focus-visible:outline-2 focus-visible:outline-primary-600"
                        data-reveal="password" data-reveal-label="{{ __('account.login.reveal_what') }}" aria-label="{{ __('account.login.reveal') }}">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="remember" class="rounded">
            {{ __('account.login.remember') }}
        </label>
        <button type="submit" class="btn-primary w-full">{{ __('account.login.submit') }}<i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
    </form>

    @if($googleClientId)
    {{-- Connexion avec Google (docs/fonctionnalites/connexion-google.md) : bouton officiel de Google Identity Services. --}}
    <div id="google-signin" class="mt-6">
        <div class="flex items-center gap-3 text-sm text-slate-500" aria-hidden="true">
            <span class="flex-1 border-t border-slate-200"></span>{{ __('account.login.or') }}<span class="flex-1 border-t border-slate-200"></span>
        </div>
        <div id="google-signin-button" class="mt-5 flex min-h-10 justify-center"></div>
        <form id="google-signin-form" method="POST" action="{{ route('login.google') }}" hidden>
            @csrf
            <input type="hidden" name="credential">
            <input type="hidden" name="remember">
        </form>
    </div>
    @endif

    <p class="mt-8 border-t border-slate-200 pt-5 text-center text-sm text-slate-500">
        {{ __('account.login.no_account') }}
        <a href="{{ route('register') }}" class="font-semibold text-primary-600 hover:text-primary-700 hover:underline">{{ __('account.register.title') }}</a>
    </p>
</div>
@endsection

@if($googleClientId)
@push('scripts')
<script>
(function () {
    const block  = document.getElementById('google-signin');
    const target = document.getElementById('google-signin-button');
    const form   = document.getElementById('google-signin-form');

    function render() {
        google.accounts.id.initialize({
            client_id: @json($googleClientId),
            callback: function (response) {
                form.elements.credential.value = response.credential;
                form.elements.remember.value = document.querySelector('input[name="remember"][type="checkbox"]')?.checked ? '1' : '';
                form.submit();
            },
        });
        google.accounts.id.renderButton(target, {
            theme: 'outline', size: 'large', shape: 'rectangular', text: 'continue_with', locale: @json(app()->getLocale()),
            width: Math.min(400, Math.max(200, target.offsetWidth)),
        });
    }

    // Script bloqué (bloqueur de publicité, hors ligne) : la connexion par e-mail reste seule.
    const script = document.createElement('script');
    script.src = 'https://accounts.google.com/gsi/client?hl=' + @json(app()->getLocale());
    script.async = true;
    script.onload = render;
    script.onerror = function () { block.hidden = true; };
    document.head.appendChild(script);
})();
</script>
@endpush
@endif

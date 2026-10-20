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
        {{--
            E-mail ou téléphone de contact (docs/fonctionnalites/telephone.md). Sans JavaScript, les deux blocs restent
            visibles : le serveur utilise le mode choisi (ou le téléphone si l'e-mail est vide).
        --}}
        @php($loginMethod = old('login_method', $errors->has('phone') || $errors->has('phone_country') ? 'phone' : 'email') === 'phone' ? 'phone' : 'email')
        <fieldset data-login-method>
            <legend class="form-label">{{ __('account.login.method') }}</legend>
            <div class="grid grid-cols-2 gap-2">
                @foreach(['email' => 'fa-regular fa-envelope', 'phone' => 'fa-solid fa-mobile-screen'] as $method => $icon)
                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50 has-[:checked]:text-slate-900">
                    <input type="radio" name="login_method" value="{{ $method }}" @checked($loginMethod === $method)>
                    <i class="{{ $icon }} text-slate-400" aria-hidden="true"></i>{{ __('account.login.method_' . $method) }}
                </label>
                @endforeach
            </div>
        </fieldset>
        <div data-login-section="email">
            <label for="email" class="form-label">{{ __('account.fields.email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" @if($loginMethod === 'email') autofocus @endif autocomplete="email"
                   class="form-input" placeholder="{{ __('account.fields.email_placeholder') }}" @if($errors->has('email')) aria-invalid="true" @endif data-login-required>
        </div>
        <div data-login-section="phone">
            @include('components.phone-input', [
                'phoneCountry' => old('phone_country'),
                'number'       => old('phone'),
                'required'     => false,
                'phoneLabel'   => __('account.login.method_phone'),
                'phoneHint'    => __('account.login.phone_hint'),
                'phoneErrors'  => false,
            ])
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

    @include('auth.partials.google-signin', ['googleText' => 'continue_with'])

    <p class="mt-8 border-t border-slate-200 pt-5 text-center text-sm text-slate-500">
        {{ __('account.login.no_account') }}
        <a href="{{ route('register') }}" class="font-semibold text-primary-600 hover:text-primary-700 hover:underline">{{ __('account.register.title') }}</a>
    </p>
    <p class="mt-3 text-center text-xs text-slate-500">
        <a href="{{ \App\Services\PrivacyPolicy::url(app()->getLocale()) }}" class="hover:text-slate-900 hover:underline">{{ __('privacy.link') }}</a>
    </p>
</div>
@endsection

@push('scripts')
<script>
// Choix « E-mail » / « Téléphone » : seul le bloc choisi est affiché et obligatoire.
(function () {
    const choice = document.querySelector('[data-login-method]');
    if (!choice) return;
    function apply() {
        const method = choice.querySelector('input[name="login_method"]:checked')?.value || 'email';
        document.querySelectorAll('[data-login-section]').forEach(function (section) {
            const active = section.dataset.loginSection === method;
            section.hidden = !active;
            section.querySelectorAll('input[name="email"], input[name="phone"]').forEach(function (input) { input.required = active; });
        });
    }
    choice.addEventListener('change', function () {
        apply();
        const method = choice.querySelector('input[name="login_method"]:checked')?.value;
        document.getElementById(method === 'phone' ? 'phone' : 'email')?.focus();
    });
    apply();
})();
</script>
@endpush

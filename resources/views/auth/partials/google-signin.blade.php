{{--
    Bouton « Continuer avec Google » (Google Identity Services), page de connexion et page de création de compte.
    Même parcours dans les deux cas : compte créé s'il est nouveau, connexion s'il existe (docs/fonctionnalites/connexion-google.md).
    @include('auth.partials.google-signin', ['googleText' => 'continue_with' | 'signup_with'])
    Nécessite $googleClientId (App\Services\GoogleSignIn::webClientId()) ; rien n'est affiché sans configuration.
--}}
@if($googleClientId ?? null)
<div id="google-signin" class="mt-6">
    <div class="flex items-center gap-3 text-sm text-slate-500" aria-hidden="true">
        <span class="flex-1 border-t border-slate-200"></span>{{ __('account.login.or') }}<span class="flex-1 border-t border-slate-200"></span>
    </div>
    <div id="google-signin-button" class="mt-5 flex min-h-10 justify-center"></div>
    {{-- Un compte peut être créé par ce bouton : consentement enregistré (docs/fonctionnalites/confidentialite-et-suppression.md) --}}
    <p class="mt-3 text-center text-xs text-slate-500">{!! __('privacy.consent.google', ['link' => '<a href="' . e(\App\Services\PrivacyPolicy::url(app()->getLocale())) . '" target="_blank" rel="noopener" class="font-semibold text-primary-600 hover:underline">' . e(__('privacy.consent.link')) . '</a>']) !!}</p>
    <form id="google-signin-form" method="POST" action="{{ route('login.google') }}" hidden>
        @csrf
        <input type="hidden" name="credential">
        <input type="hidden" name="remember">
    </form>
</div>
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
            theme: 'outline', size: 'large', shape: 'rectangular', text: @json($googleText ?? 'continue_with'), locale: @json(app()->getLocale()),
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

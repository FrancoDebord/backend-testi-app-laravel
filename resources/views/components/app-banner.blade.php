{{--
    « Télécharger l'application » : liens des stores (APP_PLAY_STORE_URL, APP_STORE_URL dans .env, config/seo.php).
    Rien n'est affiché sans lien. @include('components.app-banner', ['compact' => true]) : liens seuls (en ligne).
    Voir docs/fonctionnalites/referencement.md
--}}
@php
    $stores = array_filter([
        'android' => config('seo.play_store_url'),
        'ios'     => config('seo.app_store_url'),
    ]);
@endphp
@if($stores)
@if(!empty($compact))
    @isset($stores['android'])<a href="{{ $stores['android'] }}" class="chip" rel="noopener"><i class="fa-brands fa-google-play" aria-hidden="true"></i>{{ __('seo.app.android') }}</a>@endisset
    @isset($stores['ios'])<a href="{{ $stores['ios'] }}" class="chip" rel="noopener"><i class="fa-brands fa-apple" aria-hidden="true"></i>{{ __('seo.app.ios') }}</a>@endisset
@else
<aside class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between" aria-label="{{ __('seo.app.title') }}">
    <div class="flex items-center gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i></span>
        <div>
            <p class="text-sm font-semibold text-slate-900">{{ __('seo.app.title') }}</p>
            <p class="text-xs text-slate-500">{{ __('seo.app.text') }}</p>
        </div>
    </div>
    <div class="flex flex-wrap gap-2">
        @isset($stores['android'])<a href="{{ $stores['android'] }}" class="btn-secondary btn-sm" rel="noopener"><i class="fa-brands fa-google-play" aria-hidden="true"></i>{{ __('seo.app.android') }}</a>@endisset
        @isset($stores['ios'])<a href="{{ $stores['ios'] }}" class="btn-secondary btn-sm" rel="noopener"><i class="fa-brands fa-apple" aria-hidden="true"></i>{{ __('seo.app.ios') }}</a>@endisset
    </div>
</aside>
@endif
@endif

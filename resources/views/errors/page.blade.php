{{-- Page d'erreur du site (docs/fonctionnalites/pages-erreur.md), incluse par errors/{code}.blade.php.
     Autonome : pas de layouts.guest (en-tête SEO, menus, comptes) pour s'afficher même si la base ou les assets
     sont indisponibles pendant une erreur 500. $exception est fourni par Laravel. --}}
@php
    $code = isset($exception) ? \App\Support\HttpErrors::status($exception) : (int) ($code ?? 500);
    $copy = \App\Support\HttpErrors::copy($code);
    $detail = isset($exception) ? \App\Support\HttpErrors::publicMessage($exception) : null;
    $icon = match (true) {
        $code === 404, $code === 410 => 'fa-compass',
        $code === 401 => 'fa-right-to-bracket',
        $code === 403 => 'fa-lock',
        $code === 419 => 'fa-clock-rotate-left',
        $code === 429 => 'fa-hourglass-half',
        $code === 503 => 'fa-screwdriver-wrench',
        $code >= 500 => 'fa-triangle-exclamation',
        default => 'fa-circle-exclamation',
    };
    $guest = rescue(fn () => !auth()->check(), true, false);
    // Page précédente : seulement sur ce site, et différente de la page en erreur.
    $previous = rescue(fn () => url()->previous(), null, false);
    $previous = $previous && $previous !== url()->full() && parse_url($previous, PHP_URL_HOST) === request()->getHost() ? $previous : null;
    $searchable = in_array($code, [404, 410], true) && !request()->is('api/*');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ $copy['title'] }} — My Testimony · ARISE &amp; SHINE Krea</title>
    <link rel="icon" type="image/png" href="{{ asset('icons/arise-shine-krea-star.png') }}">
    <meta name="theme-color" content="#184797">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    {{-- Assets absents (public/build non copié) : la page reste lisible plutôt que de provoquer une seconde erreur. --}}
    {!! rescue(fn () => app(\Illuminate\Foundation\Vite::class)(['resources/css/app.css']), '', false) !!}
</head>
<body class="flex min-h-full flex-col bg-white">

<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex h-20 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
        <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3">
            <img src="{{ asset('icons/arise-shine-krea.png') }}" alt="ARISE &amp; SHINE Krea" class="h-14 w-auto" width="477" height="493">
            <span class="border-l border-slate-200 pl-3 text-sm font-bold"><span class="text-primary-600">My</span> <span class="text-accent-600">Testimony</span></span>
        </a>
        <a href="{{ route('home') }}" class="btn-ghost btn-sm" aria-label="{{ __('layout.back_to_site') }}">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span class="hidden sm:inline">{{ __('layout.back_to_site') }}</span>
        </a>
    </div>
</header>

<main class="flex flex-1 items-center justify-center px-4 py-10 sm:px-6 sm:py-14">
    <div class="w-full max-w-xl overflow-hidden rounded-xl border border-slate-200 bg-white shadow-soft">
        <div class="relative overflow-hidden border-b border-primary-100 bg-primary-50" aria-hidden="true">
            @include('layouts.partials.krea-landscape', ['class' => 'block h-28 w-full sm:h-36', 'viewBox' => '0 50 400 150'])
        </div>

        <div class="p-6 sm:p-8">
            <div class="flex items-center gap-3">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg {{ $code >= 500 ? 'bg-error-50 text-error-700' : 'bg-primary-50 text-primary-600' }}">
                    <i class="fa-solid {{ $icon }} text-xl" aria-hidden="true"></i>
                </span>
                <span class="badge-neutral">{{ __('errors.code', ['code' => $code]) }}</span>
            </div>

            <h1 class="text-h3 mt-5">{{ $copy['title'] }}</h1>
            <p class="text-body mt-3 text-slate-600">{{ $copy['text'] }}</p>

            @if ($detail && $detail !== $copy['title'])
                <div class="alert-info mt-5">
                    <i class="fa-solid fa-circle-info mt-0.5 text-slate-400" aria-hidden="true"></i>
                    <p>{{ $detail }}</p>
                </div>
            @endif

            @if ($searchable)
                <form action="{{ route('search') }}" method="GET" role="search" class="mt-6">
                    <label for="error-search" class="form-label">{{ __('errors.search_label') }}</label>
                    <div class="mt-1 flex flex-col gap-2 sm:flex-row">
                        <input id="error-search" type="search" name="q" class="form-input sm:flex-1" required minlength="2" autocomplete="off">
                        <button type="submit" class="btn-secondary"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>{{ __('errors.search_button') }}</button>
                    </div>
                </form>
            @endif

            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                @if ($code === 419)
                    <a href="{{ $previous ?? route('home') }}" class="btn-primary"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i>{{ __('errors.reload') }}</a>
                @elseif (in_array($code, [401, 403], true) && $guest)
                    <a href="{{ route('login') }}" class="btn-primary"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>{{ __('errors.login') }}</a>
                @else
                    <a href="{{ route('home') }}" class="btn-primary"><i class="fa-solid fa-house" aria-hidden="true"></i>{{ __('errors.back_home') }}</a>
                @endif
                @if ($previous && $code !== 419)
                    <a href="{{ $previous }}" class="btn-ghost"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>{{ __('errors.go_back') }}</a>
                @endif
                @if ($code === 419 || (in_array($code, [401, 403], true) && $guest))
                    <a href="{{ route('home') }}" class="btn-ghost"><i class="fa-solid fa-house" aria-hidden="true"></i>{{ __('errors.back_home') }}</a>
                @endif
            </div>

            @if ($searchable)
                <div class="mt-8 border-t border-slate-200 pt-5">
                    <p class="text-secondary">{{ __('errors.suggestions') }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('explore') }}" class="chip"><i class="fa-solid fa-book-open" aria-hidden="true"></i>{{ __('errors.links.explore') }}</a>
                        <a href="{{ route('prayer.requests.index') }}" class="chip"><i class="fa-solid fa-hands-praying" aria-hidden="true"></i>{{ __('errors.links.prayers') }}</a>
                        <a href="{{ route('events.index') }}" class="chip"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i>{{ __('errors.links.events') }}</a>
                        <a href="{{ route('lives.index') }}" class="chip"><i class="fa-solid fa-tower-broadcast" aria-hidden="true"></i>{{ __('errors.links.lives') }}</a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</main>

<footer class="border-t border-slate-200 bg-white">
    <p class="mx-auto max-w-6xl px-4 py-4 text-center text-xs text-slate-500 sm:px-6">© {{ date('Y') }} My Testimony — ARISE &amp; SHINE Krea</p>
</footer>
</body>
</html>

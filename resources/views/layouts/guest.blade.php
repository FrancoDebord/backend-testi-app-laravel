<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'TestiApp') — TestiApp · ARISE &amp; SHINE Krea</title>
    <link rel="icon" type="image/png" href="{{ asset('icons/arise-shine-krea-star.png') }}">
    <meta name="theme-color" content="#184797">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col">

<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex h-20 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
        <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3">
            <img src="{{ asset('icons/arise-shine-krea.png') }}" alt="ARISE &amp; SHINE Krea" class="h-14 w-auto" width="477" height="493">
            <span class="border-l border-slate-200 pl-3 text-sm font-bold text-primary-600">TestiApp</span>
        </a>
        <a href="{{ route('home') }}" class="btn-ghost btn-sm">
            <i class="fa-solid fa-arrow-left"></i><span class="hidden sm:inline">Retour au site</span>
        </a>
    </div>
</header>

{{-- Deux colonnes dès 1024 px : panneau de marque (Blue Light, paysage vagues et soleil) et formulaire.
     En dessous, un bandeau court remplace le panneau au-dessus du formulaire. --}}
<main class="flex flex-1 justify-center px-4 py-8 sm:px-6 sm:py-10 lg:items-center lg:py-12">
    <div class="grid w-full max-w-6xl gap-10 lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] lg:gap-12">
        <aside class="hidden overflow-hidden rounded-2xl border border-primary-100 bg-primary-50 lg:sticky lg:top-8 lg:flex lg:min-h-[36rem] lg:flex-col lg:self-start">
            <div class="p-10 pb-4">
                <span class="badge-yellow"><i class="fa-solid fa-sun" aria-hidden="true"></i>ARISE &amp; SHINE Krea</span>
                <p class="text-h2 mt-5">Dieu agit encore.</p>
                <p class="text-body mt-4 text-slate-600">TestiApp rassemble les témoignages de la communauté pour encourager, édifier et rendre gloire à Dieu.</p>
                <ul class="mt-8 space-y-5">
                    <li class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white text-primary-600 shadow-soft"><i class="fa-solid fa-book-open" aria-hidden="true"></i></span>
                        <span><span class="block font-semibold text-slate-900">Lire, écouter, regarder</span><span class="text-secondary">Des témoignages en texte, audio et vidéo.</span></span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white text-primary-600 shadow-soft"><i class="fa-solid fa-tower-broadcast" aria-hidden="true"></i></span>
                        <span><span class="block font-semibold text-slate-900">Suivre les directs</span><span class="text-secondary">Rejoindre la communauté en temps réel.</span></span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white text-accent-500 shadow-soft"><i class="fa-solid fa-feather-pointed" aria-hidden="true"></i></span>
                        <span><span class="block font-semibold text-slate-900">Partager le vôtre</span><span class="text-secondary">Raconter ce que Dieu a fait dans votre vie.</span></span>
                    </li>
                </ul>
            </div>
            <div class="mt-auto">
                @include('layouts.partials.krea-landscape', ['class' => 'block h-44 w-full'])
            </div>
        </aside>

        <div class="mx-auto w-full max-w-lg lg:self-center">
            <div class="relative mb-5 overflow-hidden rounded-xl border border-primary-100 bg-primary-50 lg:hidden" aria-hidden="true">
                <p class="absolute top-3 left-4 z-10 text-base font-bold text-primary-600 sm:top-4 sm:left-5 sm:text-lg">Dieu agit encore.</p>
                @include('layouts.partials.krea-landscape', ['class' => 'block h-36 w-full sm:h-48', 'viewBox' => '0 50 400 150'])
            </div>
            @yield('content')
        </div>
    </div>
</main>

<footer class="border-t border-slate-200 bg-white">
    <p class="mx-auto max-w-6xl px-4 py-4 text-center text-xs text-slate-500 sm:px-6">
        © {{ date('Y') }} TestiApp — ARISE &amp; SHINE Krea
    </p>
</footer>

@include('layouts.partials.global-ui')
@stack('scripts')
</body>
</html>

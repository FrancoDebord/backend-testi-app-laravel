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
    <div class="mx-auto flex h-20 max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
        <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3">
            <img src="{{ asset('icons/arise-shine-krea.png') }}" alt="ARISE &amp; SHINE Krea" class="h-14 w-auto" width="477" height="493">
            <span class="border-l border-slate-200 pl-3 text-sm font-bold text-primary-600">TestiApp</span>
        </a>
        <a href="{{ route('home') }}" class="btn-ghost btn-sm">
            <i class="fa-solid fa-arrow-left"></i><span class="hidden sm:inline">Retour au site</span>
        </a>
    </div>
</header>

<main class="flex flex-1 items-start justify-center px-4 py-10 sm:items-center sm:py-16">
    <div class="w-full max-w-md">
        @yield('content')
    </div>
</main>

<footer class="border-t border-slate-200 bg-white">
    <p class="mx-auto max-w-5xl px-4 py-4 text-center text-xs text-slate-500 sm:px-6">
        © {{ date('Y') }} TestiApp — African Institute for Research in Infectious Diseases (AIRID)
    </p>
</footer>

@include('layouts.partials.global-ui')
@stack('scripts')
</body>
</html>

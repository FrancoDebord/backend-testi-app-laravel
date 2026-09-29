@php
    $authUser = Auth::user();

    // Menu latéral : chaque entrée précise qui peut la voir.
    $navGroups = [
        [
            'label' => 'Découvrir',
            'visible' => true,
            'items' => [
                ['label' => 'Accueil',  'icon' => 'fa-house',               'url' => route('home'),         'active' => request()->routeIs('home')],
                ['label' => 'Explorer', 'icon' => 'fa-magnifying-glass',    'url' => route('explore'),      'active' => request()->routeIs('explore')],
                ['label' => 'Vidéos',   'icon' => 'fa-circle-play',         'url' => route('videos.index'), 'active' => request()->routeIs('videos.*')],
                ['label' => 'Bible',    'icon' => 'fa-book-bible',          'url' => route('bible.reader'), 'active' => request()->routeIs('bible.*')],
                ['label' => 'Directs',  'icon' => 'fa-tower-broadcast',     'url' => route('lives.index'),  'active' => request()->routeIs('lives.index', 'lives.show')],
                ['label' => 'Communauté', 'icon' => 'fa-people-group',      'url' => route('community.index'), 'active' => request()->routeIs('community.*')],
            ],
        ],
        [
            'label' => 'Mon espace',
            'visible' => (bool) $authUser,
            'items' => $authUser ? [
                ['label' => 'Publier un témoignage', 'icon' => 'fa-pen-to-square', 'url' => route('publish'),                     'active' => request()->routeIs('publish')],
                ['label' => 'Mes témoignages',       'icon' => 'fa-list',          'url' => route('testimonies.mine'),            'active' => request()->routeIs('testimonies.mine')],
                ['label' => 'Mes abonnements',       'icon' => 'fa-user-check',    'url' => route('profile.following'),           'active' => request()->routeIs('profile.following')],
                ['label' => 'Sauvegardes',           'icon' => 'fa-bookmark',      'url' => route('profile.saved'),               'active' => request()->routeIs('profile.saved')],
                ['label' => 'Notifications',         'icon' => 'fa-bell',          'url' => route('notifications.index'),         'active' => request()->routeIs('notifications.*')],
                ['label' => 'Mon profil',            'icon' => 'fa-user',          'url' => route('profiles.show', $authUser->id), 'active' => request()->routeIs('profiles.show', 'profile.edit') && (int) request()->route('id', $authUser->id) === (int) $authUser->id],
                ['label' => 'Paramètres',            'icon' => 'fa-gear',          'url' => route('profile.settings'),            'active' => request()->routeIs('profile.settings')],
            ] : [],
        ],
        [
            'label' => 'Modération',
            'visible' => $authUser?->canModerate() ?? false,
            'items' => [
                ['label' => 'File de modération', 'icon' => 'fa-shield-halved', 'url' => route('moderation.index'), 'active' => request()->routeIs('moderation.*')],
                ['label' => 'Lancer un direct',   'icon' => 'fa-video',         'url' => route('lives.create'),     'active' => request()->routeIs('lives.create', 'lives.studio')],
            ],
        ],
        [
            'label' => 'Administration',
            'visible' => $authUser?->isAdmin() ?? false,
            'items' => [
                ['label' => 'Tableau de bord', 'icon' => 'fa-chart-simple', 'url' => route('admin.dashboard'),        'active' => request()->routeIs('admin.dashboard')],
                ['label' => 'Utilisateurs',    'icon' => 'fa-users',        'url' => route('admin.users.index'),      'active' => request()->routeIs('admin.users.*')],
                ['label' => 'Contenu',         'icon' => 'fa-file-lines',   'url' => route('admin.content.index'),    'active' => request()->routeIs('admin.content.*')],
                ['label' => 'Catégories',      'icon' => 'fa-tags',         'url' => route('admin.categories.index'), 'active' => request()->routeIs('admin.categories.*')],
                ['label' => 'Paramètres',      'icon' => 'fa-sliders',      'url' => route('admin.settings'),         'active' => request()->routeIs('admin.settings')],
                ['label' => 'Documentation',   'icon' => 'fa-book',         'url' => route('admin.documentation'),    'active' => request()->routeIs('admin.documentation')],
            ],
        ],
    ];

    $header      = $header ?? null;
    $subheader   = $subheader ?? null;
    $breadcrumbs = $breadcrumbs ?? [];
    $fullBleed   = $fullBleed ?? false;
@endphp
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
    @stack('styles')
</head>
<body class="min-h-full">

{{-- ── Barre latérale ───────────────────────────────────────────────────── --}}
<div id="sidebar-backdrop" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden" data-sidebar-close hidden></div>

<aside id="app-sidebar"
       class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0"
       aria-label="Menu principal">
    <div class="flex h-20 shrink-0 items-center justify-between gap-3 border-b border-slate-200 px-5">
        <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3">
            <img src="{{ asset('icons/arise-shine-krea.png') }}" alt="ARISE &amp; SHINE Krea" class="h-14 w-auto" width="477" height="493">
            <span class="border-l border-slate-200 pl-3 text-sm font-bold text-primary-600">TestiApp</span>
        </a>
        <button type="button" class="btn-ghost btn-sm lg:hidden" data-sidebar-close aria-label="Fermer le menu">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
        @foreach($navGroups as $group)
            @continue(!$group['visible'] || empty($group['items']))
            <div>
                <p class="section-title mb-2 px-3">{{ $group['label'] }}</p>
                <ul class="space-y-0.5">
                    @foreach($group['items'] as $item)
                    <li>
                        <a href="{{ $item['url'] }}"
                           @if($item['active']) aria-current="page" @endif
                           class="{{ $item['active'] ? 'bg-primary-600 text-white font-semibold shadow-soft' : 'text-slate-700 hover:bg-primary-50 hover:text-primary-600 font-medium' }} flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm">
                            <i class="fa-solid {{ $item['icon'] }} w-4 text-center {{ $item['active'] ? 'text-white' : 'text-primary-600' }}"></i>
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <div class="shrink-0 border-t border-slate-200 p-4">
        @auth
        <div class="flex items-center gap-3">
            @include('components.avatar', ['user' => $authUser, 'size' => 'md'])
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-900">{{ $authUser->display_name }}</p>
                <p class="truncate text-xs text-slate-500">{{ $authUser->role->label() }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}" data-loading-label="Déconnexion…">
                @csrf
                <button type="submit" class="btn-ghost btn-sm" title="Déconnexion" aria-label="Déconnexion">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </button>
            </form>
        </div>
        @else
        <div class="grid gap-2">
            <a href="{{ route('login') }}" class="btn-primary w-full">Se connecter</a>
            <a href="{{ route('register') }}" class="btn-cta w-full">Créer un compte</a>
        </div>
        @endauth
    </div>
</aside>

{{-- ── Contenu ─────────────────────────────────────────────────────────── --}}
<div class="flex min-h-screen min-w-0 flex-col lg:pl-64">

    <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b border-slate-200 bg-white px-4 sm:px-6 lg:px-8">
        <button type="button" class="btn-ghost btn-sm lg:hidden" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false" aria-label="Ouvrir le menu">
            <i class="fa-solid fa-bars text-base"></i>
        </button>
        <a href="{{ route('home') }}" class="flex items-center lg:hidden">
            <img src="{{ asset('icons/arise-shine-krea.png') }}" alt="ARISE &amp; SHINE Krea" class="h-11 w-auto" width="477" height="493">
        </a>

        {{-- Recherche globale (page Explorer) --}}
        <form method="GET" action="{{ route('explore') }}" role="search" class="mx-auto hidden w-full max-w-xl md:flex" data-loading-inline>
            <label for="global-search" class="sr-only">Rechercher un témoignage</label>
            <div class="relative min-w-0 flex-1">
                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
                <input id="global-search" type="search" name="q" value="{{ request()->routeIs('explore') ? request('q') : '' }}"
                       class="form-input min-h-10 rounded-full bg-slate-50 pl-9" placeholder="Rechercher un témoignage, un auteur…">
            </div>
        </form>

        <div class="ml-auto flex items-center gap-2 md:ml-0">
            <a href="{{ route('explore') }}" class="btn-ghost btn-sm md:hidden" title="Rechercher" aria-label="Rechercher">
                <i class="fa-solid fa-magnifying-glass text-base"></i>
            </a>
            @auth
            <a href="{{ route('notifications.index') }}" class="btn-ghost btn-sm" title="Notifications" aria-label="Notifications">
                <i class="fa-regular fa-bell text-base"></i>
            </a>
            <a href="{{ route('profiles.show', $authUser->id) }}" class="hidden items-center gap-2 rounded-lg px-2 py-1 hover:bg-slate-50 sm:flex">
                @include('components.avatar', ['user' => $authUser, 'size' => 'sm'])
                <span class="max-w-40 truncate text-sm font-medium text-slate-700">{{ $authUser->display_name }}</span>
            </a>
            @else
            <a href="{{ route('login') }}" class="btn-ghost btn-sm">Connexion</a>
            <a href="{{ route('register') }}" class="btn-cta btn-sm">S'inscrire</a>
            @endauth
        </div>
    </header>

    <main class="{{ $fullBleed ? '' : 'px-4 py-6 sm:px-6 lg:px-8' }} min-w-0 flex-1">
    <div class="{{ $fullBleed ? '' : 'mx-auto w-full max-w-[1280px]' }}">

        @if($header)
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                @if(count($breadcrumbs))
                <nav aria-label="Fil d'Ariane" class="mb-2">
                    <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                        @foreach($breadcrumbs as $crumb)
                        <li class="flex min-w-0 items-center gap-2">
                            @if(!$loop->first)<i class="fa-solid fa-chevron-right text-[10px] text-slate-300"></i>@endif
                            @if(!empty($crumb['url']))
                                <a href="{{ $crumb['url'] }}" class="hover:text-slate-900 hover:underline">{{ $crumb['label'] }}</a>
                            @else
                                <span class="max-w-60 truncate text-slate-700" aria-current="page">{{ $crumb['label'] }}</span>
                            @endif
                        </li>
                        @endforeach
                    </ol>
                </nav>
                @endif
                <h1 class="text-2xl font-bold break-words text-primary-600 sm:text-[28px]">{{ $header }}</h1>
                @if($subheader)<p class="mt-1 text-sm text-slate-500">{{ $subheader }}</p>@endif
            </div>
            @hasSection('headerActions')
            <div class="flex flex-wrap items-center gap-2">@yield('headerActions')</div>
            @endif
        </div>
        @endif

        @php
            // Messages de session + erreur générale renvoyée par withErrors(['error' => …]).
            $flashMessages = array_filter([
                ['alert-success', 'fa-circle-check', session('success')],
                ['alert-success', 'fa-circle-check', session('status')],
                ['alert-error', 'fa-circle-exclamation', session('error') ?: $errors->first('error')],
            ], fn ($m) => filled($m[2]));
        @endphp
        @if($flashMessages)
        <div class="{{ $fullBleed ? 'px-4 pt-4 sm:px-6' : '' }} mb-6 space-y-2">
            @foreach($flashMessages as [$alertClass, $icon, $message])
            <div class="{{ $alertClass }}" role="{{ $alertClass === 'alert-error' ? 'alert' : 'status' }}">
                <i class="fa-solid {{ $icon }} mt-0.5"></i>
                <p class="min-w-0 flex-1">{{ $message }}</p>
                <button type="button" class="opacity-60 hover:opacity-100" data-dismiss-alert aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
            </div>
            @endforeach
        </div>
        @endif

        @yield('content')
    </div>
    </main>

    <footer class="border-t border-slate-200 bg-white px-4 py-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
            <p>© {{ date('Y') }} TestiApp — African Institute for Research in Infectious Diseases (AIRID)</p>
            <p class="flex gap-4">
                <a href="{{ route('home') }}" class="hover:text-slate-900">Accueil</a>
                <a href="{{ route('explore') }}" class="hover:text-slate-900">Explorer</a>
                <a href="{{ route('videos.index') }}" class="hover:text-slate-900">Vidéos</a>
                <a href="{{ route('bible.reader') }}" class="hover:text-slate-900">Bible</a>
            </p>
        </div>
    </footer>
</div>

@include('layouts.partials.global-ui')

@stack('scripts')
</body>
</html>

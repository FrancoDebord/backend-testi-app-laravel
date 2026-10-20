@php
    $authUser = Auth::user();

    // Menu latéral : chaque entrée précise qui peut la voir.
    $navGroups = [
        [
            'label' => __('layout.nav.discover'),
            'visible' => true,
            'items' => [
                ['label' => __('layout.nav.home'),  'icon' => 'fa-house',               'url' => route('home'),         'active' => request()->routeIs('home')],
                ['label' => __('layout.nav.explore'), 'icon' => 'fa-magnifying-glass',    'url' => route('explore'),      'active' => request()->routeIs('explore')],
                ['label' => __('layout.nav.videos'),   'icon' => 'fa-circle-play',         'url' => route('videos.index'), 'active' => request()->routeIs('videos.*')],
                // Verset du jour et historique, comme le menu de l'application (docs/fonctionnalites/verset-du-jour.md)
                ['label' => __('layout.nav.daily_verse'), 'icon' => 'fa-sun', 'url' => route('daily-verse.show'), 'active' => request()->routeIs('daily-verse.show')],
                ['label' => __('layout.nav.verse_history'), 'icon' => 'fa-clock-rotate-left', 'url' => route('daily-verse.history'), 'active' => request()->routeIs('daily-verse.history')],
                ['label' => __('layout.nav.bible'),    'icon' => 'fa-book-bible',          'url' => route('bible.reader'), 'active' => request()->routeIs('bible.*')],
                ['label' => __('layout.nav.lives'),  'icon' => 'fa-tower-broadcast',     'url' => route('lives.index'),  'active' => request()->routeIs('lives.index', 'lives.show')],
                ['label' => __('layout.nav.events'), 'icon' => 'fa-calendar-days',     'url' => route('events.index'), 'active' => request()->routeIs('events.*') && !request()->routeIs('events.past')],
                ['label' => __('layout.nav.past_events'), 'icon' => 'fa-clock-rotate-left', 'url' => route('events.past'), 'active' => request()->routeIs('events.past')],
                ['label' => __('layout.nav.conversions'), 'icon' => 'fa-dove', 'url' => route('testimonies.conversions'), 'active' => request()->routeIs('testimonies.conversions')],
                ['label' => __('layout.nav.prayer_requests'), 'icon' => 'fa-hands-praying', 'url' => route('prayer.requests.index'), 'active' => request()->routeIs('prayer.requests.*')],
                ['label' => __('layout.nav.prayer_sessions'), 'icon' => 'fa-person-praying', 'url' => route('prayer.sessions.index'), 'active' => request()->routeIs('prayer.sessions.*')],
                ['label' => __('layout.nav.community'), 'icon' => 'fa-people-group',      'url' => route('community.index'), 'active' => request()->routeIs('community.*')],
            ],
        ],
        [
            'label' => __('layout.nav.my_space'),
            'visible' => (bool) $authUser,
            'items' => $authUser ? [
                ['label' => __('layout.nav.my_feed'),               'icon' => 'fa-house-user',    'url' => route('feed.personal'),               'active' => request()->routeIs('feed.personal')],
                ['label' => __('layout.nav.publish'), 'icon' => 'fa-pen-to-square', 'url' => route('publish'),                     'active' => request()->routeIs('publish')],
                ['label' => __('layout.nav.my_testimonies'),       'icon' => 'fa-list',          'url' => route('testimonies.mine'),            'active' => request()->routeIs('testimonies.mine')],
                ['label' => __('layout.nav.journal'),          'icon' => 'fa-book-open',     'url' => route('journal.index'),               'active' => request()->routeIs('journal.*')],
                ['label' => __('layout.nav.prophecies'),  'icon' => 'fa-scroll',        'url' => route('prophecies.index'),            'active' => request()->routeIs('prophecies.*')],
                ['label' => __('layout.nav.following'),       'icon' => 'fa-user-check',    'url' => route('profile.following'),           'active' => request()->routeIs('profile.following')],
                ['label' => __('layout.nav.saved'),           'icon' => 'fa-bookmark',      'url' => route('profile.saved'),               'active' => request()->routeIs('profile.saved')],
                ['label' => __('layout.nav.notifications'),         'icon' => 'fa-bell',          'url' => route('notifications.index'),         'active' => request()->routeIs('notifications.*')],
                ['label' => __('layout.nav.my_profile'),            'icon' => 'fa-user',          'url' => route('profiles.show', $authUser->id), 'active' => request()->routeIs('profiles.show', 'profile.edit') && (int) request()->route('id', $authUser->id) === (int) $authUser->id],
                ['label' => __('layout.nav.settings'),            'icon' => 'fa-gear',          'url' => route('profile.settings'),            'active' => request()->routeIs('profile.settings')],
            ] : [],
        ],
        [
            'label' => __('layout.nav.moderation'),
            'visible' => $authUser?->canModerate() ?? false,
            'items' => [
                // Back-office des témoignages (docs/fonctionnalites/gestion-des-temoignages.md)
                ['label' => __('layout.nav.mod_testimonies'), 'icon' => 'fa-shield-halved', 'url' => route('moderation.index'), 'active' => request()->routeIs('moderation.*') && !request()->routeIs('moderation.dashboard')],
                ['label' => __('layout.nav.mod_stats'), 'icon' => 'fa-chart-line', 'url' => route('moderation.dashboard'), 'active' => request()->routeIs('moderation.dashboard')],
                ['label' => __('layout.nav.mod_prayer'), 'icon' => 'fa-flag', 'url' => route('prayer.moderation.index'), 'active' => request()->routeIs('prayer.moderation.*')],
                ['label' => __('layout.nav.mod_events'), 'icon' => 'fa-calendar-xmark', 'url' => route('events.moderation.index'), 'active' => request()->routeIs('events.moderation.*')],
                ['label' => __('layout.nav.start_live'),   'icon' => 'fa-video',         'url' => route('lives.create'),     'active' => request()->routeIs('lives.create', 'lives.studio')],
            ],
        ],
        [
            'label' => __('layout.nav.administration'),
            'visible' => $authUser?->isAdmin() ?? false,
            'items' => [
                ['label' => __('layout.nav.dashboard'), 'icon' => 'fa-chart-simple', 'url' => route('admin.dashboard'),        'active' => request()->routeIs('admin.dashboard')],
                ['label' => __('layout.nav.users'),    'icon' => 'fa-users',        'url' => route('admin.users.index'),      'active' => request()->routeIs('admin.users.*')],
                ['label' => __('layout.nav.verifications'), 'icon' => 'fa-user-check', 'url' => route('admin.verifications.index'), 'active' => request()->routeIs('admin.verifications.*')],
                ['label' => __('layout.nav.content'),         'icon' => 'fa-file-lines',   'url' => route('admin.content.index'),    'active' => request()->routeIs('admin.content.*')],
                ['label' => __('layout.nav.categories'),      'icon' => 'fa-tags',         'url' => route('admin.categories.index'), 'active' => request()->routeIs('admin.categories.*')],
                ['label' => __('layout.nav.event_types'),     'icon' => 'fa-calendar-check', 'url' => route('admin.event-types.index'), 'active' => request()->routeIs('admin.event-types.*')],
                ['label' => __('layout.nav.admin_settings'),      'icon' => 'fa-sliders',      'url' => route('admin.settings'),         'active' => request()->routeIs('admin.settings')],
                ['label' => __('layout.nav.documentation'),   'icon' => 'fa-book',         'url' => route('admin.documentation'),    'active' => request()->routeIs('admin.documentation')],
            ],
        ],
    ];

    // Administrateurs de compte (docs/fonctionnalites/administrateurs-de-compte.md) :
    // la personne réellement connectée, ses comptes (le sien puis ceux qu'on lui a confiés)
    // et le compte au nom duquel elle agit. Sur les pages réservées au titulaire
    // (paramètres…), Auth::user() reste la personne réelle même si un compte est choisi.
    $realUser      = $authUser ? \App\Services\AccountDelegation::actor(request()) : null;
    $actingActor   = request()->attributes->get(\App\Services\AccountDelegation::ACTOR_ATTRIBUTE);
    $myAccounts    = $realUser ? \App\Services\AccountDelegation::accounts($realUser) : collect();
    $hasAccountSwitcher = $myAccounts->count() > 1;
    $chosenAccountId    = session(\App\Services\AccountDelegation::SESSION_KEY);
    $activeAccountId    = $actingActor
        ? $authUser->id
        : ($chosenAccountId && $myAccounts->contains('id', $chosenAccountId) ? $chosenAccountId : $realUser?->id);
    $actingAccount = $activeAccountId && $activeAccountId !== $realUser?->id ? $myAccounts->firstWhere('id', $activeAccountId) : null;

    $header      = $header ?? null;
    $subheader   = $subheader ?? null;
    $breadcrumbs = $breadcrumbs ?? [];
    $fullBleed   = $fullBleed ?? false;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'My Testimony') — My Testimony · ARISE &amp; SHINE Krea</title>
    <link rel="icon" type="image/png" href="{{ asset('icons/arise-shine-krea-star.png') }}">
    <meta name="theme-color" content="#184797">
    @stack('meta')

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700|caveat:600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <script>window.I18N = @json(__('js'));</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-full">

{{-- ── Barre latérale ───────────────────────────────────────────────────── --}}
<div id="sidebar-backdrop" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden" data-sidebar-close hidden></div>

<aside id="app-sidebar"
       class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0"
       aria-label="{{ __('layout.main_menu') }}">
    <div class="flex h-20 shrink-0 items-center justify-between gap-3 border-b border-slate-200 px-5">
        <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3">
            <img src="{{ asset('icons/arise-shine-krea.png') }}" alt="ARISE &amp; SHINE Krea" class="h-14 w-auto" width="477" height="493">
            <span class="border-l border-slate-200 pl-3 text-sm font-bold"><span class="text-primary-600">My</span> <span class="text-accent-600">Testimony</span></span>
        </a>
        <button type="button" class="btn-ghost btn-sm lg:hidden" data-sidebar-close aria-label="{{ __('layout.close_menu') }}">
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

        {{-- Mes comptes (menu mobile ; sur grand écran, dans le menu de l'en-tête) --}}
        @if($hasAccountSwitcher)
        <div class="lg:hidden">
            @include('layouts.partials.account-switcher', ['accounts' => $myAccounts, 'activeId' => $activeAccountId])
        </div>
        @endif
    </nav>

    <div class="shrink-0 border-t border-slate-200 p-4">
        @auth
        <div class="flex items-center gap-3">
            @include('components.avatar', ['user' => $authUser, 'size' => 'md'])
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-900">{{ $authUser->display_name }}</p>
                @if($actingActor)
                <p class="truncate text-xs text-slate-500">{{ __('layout.by', ['name' => $realUser->display_name]) }}</p>
                @else
                <p class="truncate text-xs text-slate-500">{{ $authUser->role->label() }}</p>
                @endif
            </div>
            <form method="POST" action="{{ route('logout') }}" data-loading-label="{{ __('layout.logging_out') }}">
                @csrf
                <button type="submit" class="btn-ghost btn-sm" title="{{ __('layout.logout') }}" aria-label="{{ __('layout.logout') }}">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </button>
            </form>
        </div>
        @else
        <div class="grid gap-2">
            <a href="{{ route('login') }}" class="btn-primary w-full">{{ __('layout.sign_in') }}</a>
            <a href="{{ route('register') }}" class="btn-cta w-full">{{ __('layout.create_account') }}</a>
        </div>
        @endauth
    </div>

    {{-- Bas du menu : citation et paysage (charte ARISE & SHINE Krea). Masqué si l'écran est peu haut,
         pour laisser toute la place au menu. --}}
    <div class="pointer-events-none relative hidden shrink-0 overflow-hidden [@media(min-height:860px)]:block" aria-hidden="true">
        <p class="font-script relative z-10 flex gap-2 px-6 pt-3 text-[22px] leading-tight text-primary-600">
            <i class="fa-solid fa-sun mt-1 text-lg text-sun-400"></i>
            <span>{!! __('layout.quote') !!}</span>
        </p>
        <svg class="-mt-4 block h-36 w-full" viewBox="0 0 256 128" preserveAspectRatio="none" focusable="false">
            <path d="M0 40 C80 20 160 18 256 30 V128 H0Z" fill="#FFF8D9"/>
            <path d="M0 52 C70 38 150 36 256 50 V128 H0Z" fill="#FDE7A0"/>
            <path d="M0 46 C35 42 70 58 110 72 C160 90 215 84 256 70 V128 H0Z" fill="#FCC11D"/>
            <path d="M0 76 C50 62 100 80 150 88 C200 96 235 86 256 78 V128 H0Z" fill="#2B5DB0"/>
            <path d="M0 94 C60 82 120 102 180 104 C215 105 240 98 256 94 V128 H0Z" fill="#184797"/>
            <g stroke="#FCC11D" stroke-width="2.5" stroke-linecap="round">
                <circle cx="206" cy="40" r="9" fill="#FCC11D" stroke="none"/>
                <path d="M206 24 V28 M206 52 V56 M190 40 H194 M218 40 H222 M195 29 L198 32 M214 48 L217 51 M195 51 L198 48 M214 32 L217 29"/>
            </g>
        </svg>
    </div>
</aside>

{{-- ── Contenu ─────────────────────────────────────────────────────────── --}}
<div class="flex min-h-screen min-w-0 flex-col lg:pl-64">

    <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b border-slate-200 bg-white px-4 sm:px-6 lg:px-8">
        <button type="button" class="btn-ghost btn-sm lg:hidden" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false" aria-label="{{ __('layout.open_menu') }}">
            <i class="fa-solid fa-bars text-base"></i>
        </button>
        <a href="{{ route('home') }}" class="flex items-center lg:hidden">
            <img src="{{ asset('icons/arise-shine-krea.png') }}" alt="ARISE &amp; SHINE Krea" class="h-11 w-auto" width="477" height="493">
        </a>

        {{-- Recherche globale (page Explorer) --}}
        <form method="GET" action="{{ route('explore') }}" role="search" class="mx-auto hidden w-full max-w-xl md:flex" data-loading-inline>
            <label for="global-search" class="sr-only">{{ __('layout.search_label') }}</label>
            <div class="relative min-w-0 flex-1">
                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
                <input id="global-search" type="search" name="q" value="{{ request()->routeIs('explore') ? request('q') : '' }}"
                       class="form-input min-h-10 rounded-full bg-slate-50 pl-9" placeholder="{{ __('layout.search_placeholder') }}"
                       data-search-autocomplete="all" data-search-recent-key="explore">
            </div>
            {{-- Autocomplétion de toutes les barres [data-search-autocomplete] ─ docs/fonctionnalites/recherche.md --}}
            @include('components.search-autocomplete')
        </form>

        <div class="ml-auto flex items-center gap-2 md:ml-0">
            <div class="hidden sm:block">@include('components.locale-switcher')</div>
            <a href="{{ route('explore') }}" class="btn-ghost btn-sm md:hidden" title="{{ __('layout.search') }}" aria-label="{{ __('layout.search') }}">
                <i class="fa-solid fa-magnifying-glass text-base"></i>
            </a>
            @auth
            <a href="{{ route('notifications.index') }}" class="btn-ghost btn-sm" title="{{ __('layout.nav.notifications') }}" aria-label="{{ __('layout.nav.notifications') }}">
                <i class="fa-regular fa-bell text-base"></i>
            </a>
            @if($hasAccountSwitcher)
            {{-- Menu utilisateur avec « Mes comptes » (administrateurs de compte) --}}
            <details class="relative" data-account-menu>
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-2 py-1 hover:bg-slate-50 [&::-webkit-details-marker]:hidden"
                         aria-label="{{ __('layout.account_menu') }}">
                    @include('components.avatar', ['user' => $authUser, 'size' => 'sm'])
                    <span class="hidden max-w-40 truncate text-sm font-medium text-slate-700 sm:inline">{{ $authUser->display_name }}</span>
                    <i class="fa-solid fa-chevron-down text-xs text-slate-500" aria-hidden="true"></i>
                </summary>
                <div class="absolute right-0 z-30 mt-2 w-72 rounded-xl border border-slate-200 bg-white p-2 shadow-card">
                    <a href="{{ route('profiles.show', $authUser->id) }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-primary-50 hover:text-primary-600">
                        <i class="fa-solid fa-user w-4 text-center text-primary-600" aria-hidden="true"></i>{{ __('layout.view_profile') }}
                    </a>
                    <div class="mt-2 border-t border-slate-100 pt-2">
                        @include('layouts.partials.account-switcher', ['accounts' => $myAccounts, 'activeId' => $activeAccountId])
                    </div>
                </div>
            </details>
            @else
            <a href="{{ route('profiles.show', $authUser->id) }}" class="hidden items-center gap-2 rounded-lg px-2 py-1 hover:bg-slate-50 sm:flex">
                @include('components.avatar', ['user' => $authUser, 'size' => 'sm'])
                <span class="max-w-40 truncate text-sm font-medium text-slate-700">{{ $authUser->display_name }}</span>
            </a>
            @endif
            @else
            <a href="{{ route('login') }}" class="btn-ghost btn-sm">{{ __('layout.login') }}</a>
            <a href="{{ route('register') }}" class="btn-cta btn-sm">{{ __('layout.register') }}</a>
            @endauth
        </div>
    </header>

    {{-- Bandeau : la personne agit au nom d'un compte qu'elle administre --}}
    @if($actingAccount)
    <div class="bg-primary-600 px-4 py-3 text-white sm:px-6 lg:px-8" role="status">
        <div class="mx-auto flex w-full max-w-[1280px] flex-wrap items-center gap-x-4 gap-y-2">
            <i class="fa-solid fa-user-shield text-sun-400" aria-hidden="true"></i>
            <p class="min-w-0 flex-1 text-sm">
                {!! __('layout.acting_as', ['name' => e($actingAccount['displayName'])]) !!}
                @unless($actingActor)
                <span class="block">{{ __('layout.acting_own_page', ['name' => $realUser->display_name]) }}</span>
                @endunless
            </p>
            <form method="POST" action="{{ route('accounts.switch', $realUser->id) }}" data-loading-label="{{ __('layout.back_to_account_loading') }}">
                @csrf
                <button type="submit" class="btn-accent btn-sm"><i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i>{{ __('layout.back_to_account') }}</button>
            </form>
        </div>
    </div>
    @endif

    <main class="{{ $fullBleed ? '' : 'px-4 py-6 sm:px-6 lg:px-8' }} min-w-0 flex-1">
    <div class="{{ $fullBleed ? '' : 'mx-auto w-full max-w-[1280px]' }}">

        @if($header)
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                @if(count($breadcrumbs))
                <nav aria-label="{{ __('layout.breadcrumb') }}" class="mb-2">
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
                <button type="button" class="opacity-60 hover:opacity-100" data-dismiss-alert aria-label="{{ __('layout.close') }}"><i class="fa-solid fa-xmark"></i></button>
            </div>
            @endforeach
        </div>
        @endif

        @yield('content')
    </div>
    </main>

    <footer class="border-t border-slate-200 bg-white px-4 py-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
            <p>© {{ date('Y') }} My Testimony — ARISE &amp; SHINE Krea</p>
            <div class="flex flex-wrap items-center gap-4">
                <p class="flex flex-wrap gap-4">
                    <a href="{{ route('home') }}" class="hover:text-slate-900">{{ __('layout.nav.home') }}</a>
                    <a href="{{ route('explore') }}" class="hover:text-slate-900">{{ __('layout.nav.explore') }}</a>
                    <a href="{{ route('videos.index') }}" class="hover:text-slate-900">{{ __('layout.nav.videos') }}</a>
                    <a href="{{ route('bible.reader') }}" class="hover:text-slate-900">{{ __('layout.nav.bible') }}</a>
                </p>
                <div class="sm:hidden">@include('components.locale-switcher')</div>
            </div>
        </div>
    </footer>
</div>

@include('layouts.partials.global-ui')

@if($hasAccountSwitcher)
<script>
    // Menu du compte (<details data-account-menu>) : se ferme au clic en dehors ou avec Échap.
    (() => {
        const menu = document.querySelector('[data-account-menu]');
        if (!menu) return;
        document.addEventListener('click', (e) => { if (menu.open && !menu.contains(e.target)) menu.open = false; });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && menu.open) { menu.open = false; menu.querySelector('summary')?.focus(); }
        });
    })();
</script>
@endif

@stack('scripts')
</body>
</html>

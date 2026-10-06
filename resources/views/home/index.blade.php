@extends('layouts.app')
@section('title', 'Accueil')
@php
    // Accueil selon la maquette « Témoignages de Gloire » et la charte ARISE & SHINE Krea.
    // Voir docs/fonctionnalites/accueil.md. Les blocs n'apparaissent que sur la première page sans filtre ;
    // avec un filtre ou une autre page : seulement la liste des témoignages.
    $typeFilters = ['' => 'Tous', 'video' => 'Vidéos', 'audio' => 'Audios', 'text' => 'Textes'];
    $hasFilters  = request('category') || request('type');
    $activeCategory = $category ? $categories->firstWhere('slug', $category) : null;
    $viewer      = auth()->user();
    $canModerate = $viewer?->canModerate() ?? false;
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');

    if ($showShelves) {
        $hero     = $featured->first();

        $statStrip = [
            ['fa-play',           'bg-primary-600', 'text-white',       $n($stats['testimonies']), 'Témoignages publiés'],
            ['fa-users',          'bg-primary-600', 'text-white',       $n($stats['users']),       'Utilisateurs actifs'],
            ['fa-heart',          'bg-accent-500',  'text-white',       $n($stats['views']),       'Vues totales'],
            ['fa-hands-praying',  'bg-sun-400',     'text-primary-700', $n($stats['prayers']),     'Prières reçues'],
        ];

        // Actions rapides selon le rôle : [adresse, icône, libellé, style]
        $quickActions = match (true) {
            $viewer === null => [
                [route('login'),        'fa-right-to-bracket', 'Se connecter',              'btn-primary'],
                [route('register'),     'fa-user-plus',        'Créer mon compte',          'btn-cta'],
                [route('explore'),      'fa-compass',          'Explorer les témoignages',  'btn-soft'],
                [route('lives.index'),  'fa-tower-broadcast',  'Voir les directs',          'btn-soft'],
            ],
            $canModerate => array_values(array_filter([
                [route('publish'),              'fa-plus',            'Ajouter un témoignage',   'btn-primary'],
                $viewer->isAdmin() ? [route('admin.users.index'), 'fa-user-gear', 'Gérer les utilisateurs', 'btn-soft'] : null,
                [route('moderation.index'),     'fa-shield-halved',   'Voir la file de modération', 'btn-accent'],
                $viewer->isAdmin() ? [route('admin.settings'), 'fa-gear', 'Paramètres', 'btn-soft'] : [route('profile.settings'), 'fa-gear', 'Paramètres', 'btn-soft'],
            ])),
            default => [
                [route('publish'),            'fa-plus',        'Ajouter un témoignage', 'btn-primary'],
                [route('testimonies.mine'),   'fa-list',        'Mes témoignages',       'btn-soft'],
                [route('profile.following'),  'fa-user-check',  'Mes abonnements',       'btn-soft'],
                [route('profile.settings'),   'fa-gear',        'Paramètres',            'btn-soft'],
            ],
        };

        // Statistiques globales : courbes des 7 derniers jours (témoignages en bleu, inscriptions en orange).
        $days     = $activity['days'];
        $chartMax = max(1, collect($days)->max('testimonies'), collect($days)->max('users'));
        $points   = fn (string $key) => collect($days)->values()->map(fn ($d, $i) =>
            round(10 + $i * (280 / max(1, count($days) - 1)), 1) . ',' . round(110 - $d[$key] / $chartMax * 95, 1)
        )->implode(' ');
        $changes = [
            ['Nouveaux témoignages', $activity['testimonies'], \App\Support\WeeklyActivity::change($activity['testimonies'], $activity['previousTestimonies'])],
            ['Nouveaux utilisateurs', $activity['users'],      \App\Support\WeeklyActivity::change($activity['users'], $activity['previousUsers'])],
            ['Vues totales',          $stats['views'],         null],
        ];

        $typeIcons = ['video' => 'fa-video', 'audio' => 'fa-microphone', 'text' => 'fa-file-lines'];
    }
@endphp


@section('content')
<h1 class="sr-only">Accueil — Témoignages de Gloire</h1>

@if($showShelves)
<div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">

    {{-- ── Bandeau « Témoignages de Gloire » et chiffres clés ─────────────── --}}
    <section class="relative isolate overflow-hidden rounded-2xl border border-slate-200 shadow-card" aria-labelledby="home-hero-title">
        {{-- Illustration : lever de soleil, montagnes, personne les bras levés (couleurs de la charte) --}}
        <svg class="absolute inset-0 -z-10 h-full w-full" viewBox="0 0 1200 400" preserveAspectRatio="xMaxYMin slice" aria-hidden="true" focusable="false">
            <defs>
                <linearGradient id="home-sky" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#EAF1FC"/>
                    <stop offset="0.42" stop-color="#FFF8D9"/>
                    <stop offset="0.72" stop-color="#FDD55C"/>
                    <stop offset="1" stop-color="#F7942F"/>
                </linearGradient>
                <radialGradient id="home-glow" cx="0.5" cy="0.5" r="0.5">
                    <stop offset="0" stop-color="#FFF8D9" stop-opacity="1"/>
                    <stop offset="0.55" stop-color="#FCC11D" stop-opacity="0.35"/>
                    <stop offset="1" stop-color="#FCC11D" stop-opacity="0"/>
                </radialGradient>
            </defs>
            <rect width="1200" height="400" fill="url(#home-sky)"/>
            {{-- Scène placée dans la moitié haute : la bande des chiffres couvre le bas du bandeau. --}}
            <circle cx="1000" cy="200" r="250" fill="url(#home-glow)"/>
            <circle cx="1000" cy="200" r="66" fill="#FCC11D"/>
            <path d="M0 215 L120 170 L240 198 L380 142 L520 188 L660 150 L800 196 L940 146 L1080 184 L1200 156 L1200 400 L0 400Z" fill="#AFC8EF" opacity="0.9"/>
            <path d="M0 250 Q150 205 300 238 T600 228 T900 224 T1200 214 L1200 400 L0 400Z" fill="#7FA5E2"/>
            <path d="M0 290 Q200 252 420 280 T820 258 T1200 276 L1200 400 L0 400Z" fill="#2B5DB0"/>
            <path d="M740 400 L740 330 Q1000 190 1260 330 L1260 400Z" fill="#184797"/>
            <g fill="#103675" stroke="#103675" stroke-linecap="round" stroke-linejoin="round" transform="translate(180 0)">
                <circle cx="820" cy="178" r="13" stroke="none"/>
                <path d="M806 196 L834 196 L829 238 L811 238Z" stroke-width="6"/>
                <path d="M808 199 L780 146" stroke-width="10" fill="none"/>
                <path d="M832 199 L860 146" stroke-width="10" fill="none"/>
                <path d="M814 236 L811 256" stroke-width="10" fill="none"/>
                <path d="M826 236 L829 256" stroke-width="10" fill="none"/>
            </g>
        </svg>
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-white/70 via-white/20 to-transparent sm:bg-gradient-to-r sm:from-white/80 sm:via-white/30" aria-hidden="true"></div>

        <div class="flex min-h-[27rem] flex-col p-5 sm:p-8 lg:min-h-[25rem]">
            <h2 id="home-hero-title" class="text-[28px] leading-tight font-extrabold text-primary-600 sm:text-4xl lg:text-[44px]">Témoignages de Gloire</h2>
            <p class="mt-1 text-base font-medium text-primary-700 sm:text-lg">Des vies transformées pour la gloire de Dieu</p>
            <p class="pointer-events-none absolute top-6 right-6 hidden -rotate-6 text-right text-4xl leading-none text-primary-600 md:block lg:text-5xl font-script" aria-hidden="true">Dieu<br>agit encore !</p>

            <dl class="mt-auto grid grid-cols-2 gap-4 rounded-xl bg-white/95 p-4 shadow-card lg:grid-cols-4">
                @foreach($statStrip as [$icon, $bubble, $iconColor, $value, $label])
                <div class="flex min-w-0 items-center gap-3">
                    <span class="{{ $bubble }} {{ $iconColor }} flex h-10 w-10 shrink-0 items-center justify-center rounded-full" aria-hidden="true"><i class="fa-solid {{ $icon }}"></i></span>
                    <div class="min-w-0">
                        <dd class="text-lg leading-tight font-bold text-primary-700">{{ $value }}</dd>
                        <dt class="text-xs leading-tight text-slate-500">{{ $label }}</dt>
                    </div>
                </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- ── Actions rapides ──────────────────────────────────────────────── --}}
    <section class="card p-5" aria-labelledby="home-actions-title">
        <h2 id="home-actions-title" class="card-title mb-4">Actions rapides</h2>
        <div class="grid gap-3">
            @foreach($quickActions as [$url, $icon, $label, $style])
            <a href="{{ $url }}" class="{{ $style }} w-full justify-start">
                <i class="fa-solid {{ $icon }} w-5 text-center" aria-hidden="true"></i>
                <span class="truncate">{{ $label }}</span>
                @if($style === 'btn-accent' && ($pendingCount ?? 0) > 0)
                <span class="ml-auto rounded-full bg-primary-700 px-2 text-xs leading-5 text-white">{{ $pendingCount }}</span>
                @endif
            </a>
            @endforeach
        </div>
    </section>

    {{-- ══ Colonne principale ════════════════════════════════════════════ --}}
    <div class="min-w-0 space-y-6">

        {{-- Invitation à témoigner (le verset du jour est déjà dans la colonne de droite) --}}
        <x-encouragement kind="call" />

        {{-- En direct --}}
        @include('lives.partials.now', ['lives' => $lives])

        @include('home.partials.recent', ['title' => 'Témoignages récents'])

        {{-- Catégories populaires + statistiques globales --}}
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <section class="card p-5" aria-labelledby="home-categories-title">
                <div class="mb-4 flex items-center justify-between gap-2">
                    <h2 id="home-categories-title" class="card-title">Catégories populaires</h2>
                    <a href="{{ route('explore') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">Voir tout<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
                </div>
                @if($popularCategories->isEmpty())
                <p class="text-sm text-slate-500">Aucune catégorie pour le moment.</p>
                @else
                <ul class="grid grid-cols-1 gap-3 min-[420px]:grid-cols-2">
                    @foreach($popularCategories as $cat)
                    @php($look = $cat->presentation())
                    <li>
                        <a href="{{ route('home', ['category' => $cat->slug]) }}" class="flex min-w-0 items-center gap-3 rounded-xl border border-slate-200 p-3 transition-colors hover:border-primary-200 hover:bg-primary-50">
                            <span class="{{ $look['soft'] }} {{ $look['text'] }} flex h-10 w-10 shrink-0 items-center justify-center rounded-full" aria-hidden="true"><i class="fa-solid {{ $look['icon'] }}"></i></span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-primary-700">{{ $cat->name }}</span>
                                <span class="block text-[11px] text-slate-500">{{ $n($cat->published_count) }} témoignage{{ $cat->published_count > 1 ? 's' : '' }}</span>
                            </span>
                        </a>
                    </li>
                    @endforeach
                </ul>
                @endif
            </section>

            <section class="card flex flex-col p-5" aria-labelledby="home-stats-title">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 id="home-stats-title" class="card-title">Statistiques globales</h2>
                    <span class="chip pointer-events-none text-xs">7 derniers jours</span>
                </div>
                <svg viewBox="0 0 300 120" class="h-32 w-full" role="img"
                     aria-label="{{ $activity['testimonies'] }} témoignage(s) et {{ $activity['users'] }} inscription(s) sur les 7 derniers jours">
                    @foreach([15, 47, 79, 110] as $y)
                    <line x1="10" x2="290" y1="{{ $y }}" y2="{{ $y }}" stroke="#E4E7EC" stroke-width="1"/>
                    @endforeach
                    <polyline points="{{ $points('users') }}" fill="none" stroke="#F18717" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    <polyline points="{{ $points('testimonies') }}" fill="none" stroke="#184797" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <p class="mt-1 flex justify-between text-[10px] text-slate-400 capitalize" aria-hidden="true">
                    @foreach($days as $d)<span>{{ $d['date']->translatedFormat('D') }}</span>@endforeach
                </p>
                <p class="mt-2 flex gap-4 text-[11px] text-slate-500">
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-primary-600" aria-hidden="true"></span>Témoignages</span>
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-accent-500" aria-hidden="true"></span>Inscriptions</span>
                </p>
                <dl class="mt-4 grid grid-cols-3 gap-2">
                    @foreach($changes as [$label, $value, $change])
                    <div class="min-w-0 rounded-xl border border-slate-200 p-2.5">
                        <dt class="text-[11px] leading-tight text-slate-500">{{ $label }}</dt>
                        <dd class="mt-1 text-lg leading-tight font-bold text-primary-700">{{ $n($value) }}</dd>
                        @if($change !== null)
                        <dd class="{{ $change >= 0 ? 'text-success-700' : 'text-error-700' }} text-[11px] font-semibold">
                            <i class="fa-solid {{ $change >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' }} text-[9px]" aria-hidden="true"></i>
                            {{ $change >= 0 ? '+' : '' }}{{ $change }} %<span class="sr-only"> par rapport à la semaine précédente</span>
                        </dd>
                        @else
                        <dd class="text-[11px] text-slate-400">{{ $label === 'Vues totales' ? 'au total' : 'cette semaine' }}</dd>
                        @endif
                    </div>
                    @endforeach
                </dl>
            </section>
        </div>

        {{-- À la une --}}
        @if($hero)
        <section class="card p-5" aria-labelledby="home-featured">
            <div class="mb-4 flex items-center justify-between gap-2">
                <h2 id="home-featured" class="card-title flex items-center gap-2"><i class="fa-solid fa-star text-sun-400" aria-hidden="true"></i>À la une</h2>
                <a href="{{ route('explore') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">Tout explorer<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                @foreach($featured->take(4) as $item)
                <div class="{{ $loop->index === 3 ? 'sm:block lg:hidden 2xl:block' : '' }} min-w-0">
                    @include('videos.partials.tile', ['testimony' => $item, 'url' => route('testimonies.show', $item->id)])
                </div>
                @endforeach
            </div>
        </section>
        @endif

        {{-- Shorts --}}
        @if($shorts->isNotEmpty())
        <section class="card p-5" aria-labelledby="home-shorts">
            <div class="mb-4 flex items-center justify-between gap-2">
                <h2 id="home-shorts" class="card-title flex items-center gap-2"><i class="fa-solid fa-bolt text-accent-500" aria-hidden="true"></i>Shorts</h2>
                <a href="{{ route('videos.index', ['tab' => 'shorts']) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">Tout voir<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
            </div>
            <div class="-mx-5 flex snap-x scroll-px-5 gap-3 overflow-x-auto px-5 pb-2">
                @foreach($shorts as $shortItem)
                <div class="w-36 shrink-0 snap-start sm:w-44">
                    @include('components.testimony-card', ['testimony' => $shortItem, 'short' => true])
                </div>
                @endforeach
            </div>
        </section>
        @endif

        {{-- Gestion des contenus (équipe) ou Mes témoignages (personne connectée) --}}
        @if($canModerate)
            @include('home.partials.content-table', [
                'tableTitle' => 'Gestion des contenus',
                'items'      => $contentItems,
                'tabs'       => \App\Http\Controllers\Web\HomeController::CONTENT_TABS,
                'moreUrl'    => $viewer->isAdmin() ? route('admin.content.index') : route('moderation.index'),
            ])
        @elseif($viewer)
            @include('home.partials.content-table', [
                'tableTitle' => 'Mes témoignages',
                'items'      => $myItems,
                'tabs'       => null,
                'moreUrl'    => route('testimonies.mine'),
            ])
        @endif
    </div>

    {{-- ══ Colonne de droite ═════════════════════════════════════════════ --}}
    <div class="min-w-0 space-y-6">

        {{-- Modération rapide (équipe) --}}
        @if($canModerate)
        <section class="card flex flex-col" aria-labelledby="home-moderation-title">
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
                <h2 id="home-moderation-title" class="card-title">Modération rapide</h2>
                <a href="{{ route('moderation.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">Voir tout<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse($pendingItems as $t)
                <li>
                    <a href="{{ route('moderation.show', $t->id) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-primary-50 text-primary-600" aria-hidden="true">
                            @if($t->cover_url)<img src="{{ $t->cover_url }}" alt="" class="h-full w-full object-cover" loading="lazy">@else<i class="fa-solid {{ $typeIcons[$t->type->value] ?? 'fa-file-lines' }}"></i>@endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-primary-700">{{ $t->title }}</span>
                            <span class="block truncate text-[11px] text-slate-500">{{ $t->type->label() }}@if($t->durationLabel()) · {{ $t->durationLabel() }}@endif · {{ $t->user?->display_name }}</span>
                        </span>
                        <span class="badge-orange shrink-0">À vérifier</span>
                    </a>
                </li>
                @empty
                <li class="flex flex-col items-center gap-2 px-5 py-6 text-center text-sm text-slate-500">
                    <i class="fa-solid fa-circle-check text-2xl text-success-500" aria-hidden="true"></i>
                    <span>Rien à relire pour le moment.</span>
                </li>
                @endforelse
            </ul>
            <div class="p-5 pt-2">
                <a href="{{ route('moderation.index') }}" class="btn-primary w-full">Voir toute la file{{ $pendingCount ? ' (' . $pendingCount . ')' : '' }}</a>
            </div>
        </section>
        @endif

        {{-- Témoignages les plus populaires --}}
        <section class="card" aria-labelledby="home-popular-title">
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
                <h2 id="home-popular-title" class="card-title">Les plus populaires</h2>
                <a href="{{ route('explore') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">Voir tout<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
            </div>
            <ol class="divide-y divide-slate-100">
                @forelse($popular as $t)
                <li>
                    <a href="{{ route('testimonies.show', $t->id) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
                        <span class="{{ $loop->first ? 'bg-sun-400 text-primary-700' : 'text-primary-600' }} flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-sm font-bold">{{ $loop->iteration }}</span>
                        @if($t->cover_url)
                        <img src="{{ $t->cover_url }}" alt="" class="h-10 w-10 shrink-0 rounded-full object-cover" loading="lazy">
                        @else
                        @include('components.avatar', ['user' => $t->user, 'size' => 'md'])
                        @endif
                        <span class="min-w-0 flex-1">
                            <span class="line-clamp-2 text-sm leading-snug font-semibold text-primary-700">{{ $t->title }}</span>
                            <span class="block text-[11px] text-slate-500">{{ $t->viewsLabel() }} · {{ $n($t->like_count ?? 0) }} j’aime</span>
                        </span>
                    </a>
                </li>
                @empty
                <li class="px-5 py-6 text-center text-sm text-slate-500">Les témoignages les plus regardés apparaîtront ici.</li>
                @endforelse
            </ol>
        </section>

        {{-- Verset du jour : encadré « inspiration » --}}
        @if($verse)
        <section class="card-insight p-5" aria-labelledby="home-verse-title">
            <h2 id="home-verse-title" class="mb-2 flex items-center gap-2 text-sm font-bold text-sun-700"><i class="fa-solid fa-sun text-sun-500" aria-hidden="true"></i>Verset du jour</h2>
            <blockquote class="text-[15px] leading-relaxed text-slate-900 italic">« {{ $verse->verse_text }} »</blockquote>
            <p class="mt-2 text-sm font-semibold text-primary-700">{{ $verse->reference }}</p>
        </section>
        @endif

        {{-- Pourquoi témoigner ? (docs/fonctionnalites/pourquoi-temoigner.md) --}}
        <x-why-testify />
    </div>
</div>

@else
    {{-- Filtre ou page suivante : seulement la liste --}}
    @include('home.partials.recent', ['title' => 'Résultats'])
@endif
@endsection

@extends('layouts.app')
@section('title', 'Tableau de bord')
@php
    // Tableau de bord de l'administration, charte ARISE & SHINE Krea (docs/interface.md) :
    // bandeau bleu + chiffres clés, actions rapides, activité, modération, listes.
    $header      = 'Tableau de bord';
    $subheader   = 'Vue d’ensemble de l’activité de la plateforme.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Administration'],
        ['label' => 'Tableau de bord'],
    ];
    $n = fn ($v) => number_format($v, 0, ',', ' ');

    // Chiffres clés : [icône, fond de la pastille, couleur de l'icône, valeur, libellé, détail]
    $statCards = [
        ['fa-play',          'bg-primary-600', 'text-white',       $n($stats['totalTestimonies']), 'Témoignages',        $stats['pendingTestimonies'] . ' en attente'],
        ['fa-users',         'bg-primary-600', 'text-white',       $n($stats['totalUsers']),       'Utilisateurs',       '+' . $stats['newUsersToday'] . " aujourd'hui"],
        ['fa-eye',           'bg-accent-500',  'text-white',       $n($stats['totalViews']),       'Vues totales',       $n($stats['viewsThisMonth']) . ' ce mois'],
        ['fa-circle-check',  'bg-sun-400',     'text-primary-700', $stats['approvalRate'] . ' %',  "Taux d'approbation", $n($stats['approvedTestimonies']) . ' approuvés'],
    ];

    $quickActions = [
        [route('publish'),                              'fa-plus',            'Ajouter un témoignage',   'btn-primary'],
        [route('admin.users.index'),                    'fa-user-gear',       'Gérer les utilisateurs',  'btn-secondary'],
        [route('moderation.index'),                     'fa-shield-halved',   'Ouvrir la modération',    'btn-accent'],
        [route('admin.categories.index'),               'fa-tags',            'Catégories',              'btn-secondary'],
        [route('admin.settings'),                       'fa-gear',            'Paramètres',              'btn-secondary'],
    ];

    // Pastilles des catégories : couleurs de la marque en alternance (classes écrites en entier).
    $categoryTones = [
        ['bg-primary-50', 'text-primary-600', 'bg-primary-600'],
        ['bg-accent-50',  'text-accent-600',  'bg-accent-500'],
        ['bg-sun-50',     'text-sun-700',     'bg-sun-400'],
    ];
    $categoryMax = max(1, (int) $popularCategories->max('published_count'));

    $activityMax     = max(1, collect($activity)->max('testimonies'), collect($activity)->max('users'));
    $weekTestimonies = collect($activity)->sum('testimonies');
    $weekUsers       = collect($activity)->sum('users');

    $typeIcons = ['video' => 'fa-play', 'audio' => 'fa-microphone', 'text' => 'fa-file-lines'];
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

    {{-- ── Bandeau et chiffres clés ─────────────────────────────────────── --}}
    <section class="card-brand relative flex flex-col overflow-hidden p-6 sm:p-8 xl:col-span-2" aria-labelledby="dashboard-welcome" style="background-image: var(--krea-gradient-blue)">
        <i class="fa-solid fa-star pointer-events-none absolute top-6 right-8 text-4xl text-sun-400" aria-hidden="true"></i>
        <p class="text-sm font-medium text-primary-100">Bonjour {{ auth()->user()->display_name }}</p>
        <h2 id="dashboard-welcome" class="mt-1 max-w-lg pr-10 text-2xl font-bold sm:text-3xl">Des vies transformées, partagées chaque jour</h2>
        <p class="mt-2 max-w-lg text-sm text-primary-100">Suivez l’activité de la plateforme et traitez les éléments en attente.</p>

        <dl class="relative mt-6 grid grid-cols-2 gap-4 rounded-xl bg-white p-4 text-slate-900 shadow-card sm:mt-auto lg:grid-cols-4">
            @foreach($statCards as [$icon, $bubble, $iconColor, $value, $label, $detail])
            <div class="flex min-w-0 items-center gap-3">
                <span class="{{ $bubble }} {{ $iconColor }} flex h-10 w-10 shrink-0 items-center justify-center rounded-full" aria-hidden="true">
                    <i class="fa-solid {{ $icon }}"></i>
                </span>
                <div class="min-w-0">
                    <dd class="text-xl leading-tight font-bold text-primary-700">{{ $value }}</dd>
                    <dt class="text-xs leading-tight text-slate-500">{{ $label }}</dt>
                    <dd class="truncate text-[11px] text-slate-400">{{ $detail }}</dd>
                </div>
            </div>
            @endforeach
        </dl>
    </section>

    {{-- ── Actions rapides ──────────────────────────────────────────────── --}}
    <section class="card p-5" aria-labelledby="quick-actions-title">
        <h2 id="quick-actions-title" class="card-title mb-4">Actions rapides</h2>
        <div class="grid gap-3">
            @foreach($quickActions as [$url, $icon, $label, $style])
            <a href="{{ $url }}" class="{{ $style }} w-full justify-start">
                <i class="fa-solid {{ $icon }} w-5 text-center" aria-hidden="true"></i>{{ $label }}
            </a>
            @endforeach
        </div>
    </section>

    {{-- ── Activité des 7 derniers jours ────────────────────────────────── --}}
    <section class="card p-5 xl:col-span-2" aria-labelledby="activity-title">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 id="activity-title" class="card-title">Activité des 7 derniers jours</h2>
            <p class="flex items-center gap-4 text-xs text-slate-500">
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-primary-600" aria-hidden="true"></span>Témoignages</span>
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-accent-500" aria-hidden="true"></span>Inscriptions</span>
            </p>
        </div>

        <div class="flex h-44 items-end gap-2 border-b border-slate-200 sm:gap-4" role="img"
             aria-label="{{ $weekTestimonies }} témoignage(s) et {{ $weekUsers }} inscription(s) sur les 7 derniers jours">
            @foreach($activity as $day)
            <div class="flex h-full min-w-0 flex-1 items-end justify-center gap-1" title="{{ $day['date']->translatedFormat('l j F') }} : {{ $day['testimonies'] }} témoignage(s), {{ $day['users'] }} inscription(s)">
                <span class="w-full max-w-5 rounded-t-md bg-primary-600" style="height: {{ max(2, round($day['testimonies'] / $activityMax * 100)) }}%"></span>
                <span class="w-full max-w-5 rounded-t-md bg-accent-500" style="height: {{ max(2, round($day['users'] / $activityMax * 100)) }}%"></span>
            </div>
            @endforeach
        </div>
        <div class="mt-2 flex gap-2 sm:gap-4" aria-hidden="true">
            @foreach($activity as $day)
            <span class="min-w-0 flex-1 truncate text-center text-[11px] text-slate-500 capitalize">{{ $day['date']->translatedFormat('D j') }}</span>
            @endforeach
        </div>

        <dl class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 p-3">
                <dt class="text-xs text-slate-500">Nouveaux témoignages</dt>
                <dd class="text-xl font-bold text-primary-700">{{ $n($weekTestimonies) }}</dd>
            </div>
            <div class="rounded-xl border border-slate-200 p-3">
                <dt class="text-xs text-slate-500">Nouvelles inscriptions</dt>
                <dd class="text-xl font-bold text-primary-700">{{ $n($weekUsers) }}</dd>
            </div>
            <div class="rounded-xl border border-slate-200 p-3">
                <dt class="text-xs text-slate-500">Commentaires ce mois</dt>
                <dd class="text-xl font-bold text-primary-700">{{ $n($stats['commentsThisMonth']) }}</dd>
            </div>
        </dl>
    </section>

    {{-- ── Modération rapide ────────────────────────────────────────────── --}}
    <section class="card flex min-w-0 flex-col" aria-labelledby="moderation-title">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
            <h2 id="moderation-title" class="card-title">Modération rapide</h2>
            @if($stats['pendingTestimonies'] > 0)
            <span class="badge-orange">{{ $stats['pendingTestimonies'] }} en attente</span>
            @endif
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse($pendingItems as $t)
            <li class="flex items-center gap-3 px-5 py-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600" aria-hidden="true">
                    <i class="fa-solid {{ $typeIcons[$t->type->value] ?? 'fa-file-lines' }}"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ $t->title }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $t->user?->display_name }} · {{ $t->created_at->diffForHumans() }}</p>
                </div>
                <span class="badge-orange shrink-0">À vérifier</span>
            </li>
            @empty
            <li class="flex flex-col items-center gap-2 px-5 py-8 text-center text-sm text-slate-500">
                <i class="fa-solid fa-circle-check text-2xl text-success-500" aria-hidden="true"></i>
                <span>Rien à relire pour le moment.</span>
            </li>
            @endforelse
            @if(($stats['pendingOrganizations'] ?? 0) > 0)
            <li class="px-5 py-3">
                <a href="{{ route('admin.users.index', ['tab' => 'pending']) }}" class="card-insight flex items-center gap-3 p-3 text-sm hover:border-sun-400">
                    <i class="fa-solid fa-building-circle-check text-sun-700" aria-hidden="true"></i>
                    <span class="min-w-0 flex-1"><span class="font-semibold">{{ $stats['pendingOrganizations'] }} organisation(s)</span> à vérifier</span>
                    <i class="fa-solid fa-arrow-right text-xs text-sun-700" aria-hidden="true"></i>
                </a>
            </li>
            @endif
        </ul>
        <div class="mt-auto p-5 pt-2">
            <a href="{{ route('moderation.index') }}" class="btn-primary w-full">Ouvrir la file de modération</a>
        </div>
    </section>

    {{-- ── Derniers témoignages ─────────────────────────────────────────── --}}
    <section class="card min-w-0" aria-labelledby="recent-testimonies-title">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
            <h2 id="recent-testimonies-title" class="card-title">Derniers témoignages</h2>
            <a href="{{ route('admin.content.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">Voir tout<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse($recentTestimonies as $t)
            <li class="flex items-center gap-3 px-5 py-3">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ $t->title }}</p>
                    <p class="flex min-w-0 items-center gap-2 text-xs text-slate-500">
                        @if($t->category)<span class="badge-blue shrink-0">{{ $t->category->name }}</span>@endif
                        <span class="truncate">{{ $t->user?->display_name }} · {{ $t->created_at->format('d/m/Y') }}</span>
                    </p>
                </div>
                <span class="{{ $t->status->badgeClass() }}">{{ $t->status->label() }}</span>
            </li>
            @empty
            <li class="px-5 py-6 text-center text-sm text-slate-500">Aucun témoignage.</li>
            @endforelse
        </ul>
    </section>

    {{-- ── Les plus regardés ────────────────────────────────────────────── --}}
    <section class="card min-w-0" aria-labelledby="popular-title">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
            <h2 id="popular-title" class="card-title">Les plus regardés</h2>
            <a href="{{ route('explore') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">Voir tout<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
        </div>
        <ol class="divide-y divide-slate-100">
            @forelse($popularTestimonies as $t)
            <li class="flex items-center gap-3 px-5 py-3">
                <span class="{{ $loop->first ? 'bg-sun-400 text-primary-700' : 'bg-primary-50 text-primary-600' }} flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold">{{ $loop->iteration }}</span>
                <div class="min-w-0 flex-1">
                    <a href="{{ route('testimonies.show', $t->id) }}" class="block truncate text-sm font-semibold text-slate-900 hover:text-primary-600 hover:underline">{{ $t->title }}</a>
                    <p class="truncate text-xs text-slate-500">{{ $n($t->views_count) }} vues · {{ $n($t->like_count ?? 0) }} j’aime</p>
                </div>
            </li>
            @empty
            <li class="px-5 py-6 text-center text-sm text-slate-500">Aucun témoignage publié.</li>
            @endforelse
        </ol>
    </section>

    {{-- ── Derniers utilisateurs ────────────────────────────────────────── --}}
    <section class="card min-w-0" aria-labelledby="recent-users-title">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
            <h2 id="recent-users-title" class="card-title">Derniers utilisateurs</h2>
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">Voir tout<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse($recentUsers as $user)
            <li class="flex items-center gap-3 px-5 py-3">
                @include('components.avatar', ['user' => $user, 'size' => 'md'])
                <div class="min-w-0 flex-1">
                    <p class="flex min-w-0 items-center gap-1 text-sm font-semibold text-slate-900">
                        <a href="{{ route('admin.users.show', $user->id) }}" class="truncate hover:text-primary-600 hover:underline">{{ $user->display_name }}</a>
                        @include('components.verified-badge', ['user' => $user])
                    </p>
                    <p class="truncate text-xs text-slate-500">Inscrit {{ $user->created_at->diffForHumans() }}</p>
                </div>
                <span class="{{ $user->isOrganization() ? 'badge-yellow' : 'badge-blue' }} shrink-0">{{ $user->isOrganization() ? 'Organisation' : $user->role->label() }}</span>
            </li>
            @empty
            <li class="px-5 py-6 text-center text-sm text-slate-500">Aucun utilisateur.</li>
            @endforelse
        </ul>
    </section>

    {{-- ── Catégories populaires ────────────────────────────────────────── --}}
    <section class="card p-5 xl:col-span-3" aria-labelledby="categories-title">
        <div class="mb-4 flex items-center justify-between gap-2">
            <h2 id="categories-title" class="card-title">Catégories populaires</h2>
            <a href="{{ route('admin.categories.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">Gérer<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
        </div>
        @if($popularCategories->isEmpty())
        <p class="text-sm text-slate-500">Aucune catégorie active.</p>
        @else
        <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($popularCategories as $category)
            @php([$soft, $text, $bar] = $categoryTones[$loop->index % 3])
            <li class="rounded-xl border border-slate-200 p-4">
                <div class="flex items-center gap-3">
                    <span class="{{ $soft }} {{ $text }} flex h-10 w-10 shrink-0 items-center justify-center rounded-full" aria-hidden="true">
                        <i class="fa-solid fa-hands-praying"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ $category->name }}</p>
                        <p class="text-xs text-slate-500">{{ $n($category->published_count) }} témoignage{{ $category->published_count > 1 ? 's' : '' }}</p>
                    </div>
                </div>
                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                    <div class="{{ $bar }} h-full rounded-full" style="width: {{ max(3, round($category->published_count / $categoryMax * 100)) }}%"></div>
                </div>
            </li>
            @endforeach
        </ul>
        @endif
    </section>
</div>
@endsection

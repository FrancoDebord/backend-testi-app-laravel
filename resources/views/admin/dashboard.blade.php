@extends('layouts.app')
@section('title', __('layout.nav.dashboard'))
@php
    // Tableau de bord de l'administration, charte ARISE & SHINE Krea (docs/interface.md) :
    // bandeau bleu + chiffres clés, actions rapides, activité, modération, listes.
    $header      = __('layout.nav.dashboard');
    $subheader   = __('admin.dashboard.subheader');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.administration')],
        ['label' => __('layout.nav.dashboard')],
    ];
    // Séparateur des milliers selon la langue (espace en français, virgule en anglais).
    $n = fn ($v) => app()->getLocale() === 'en' ? number_format($v) : number_format($v, 0, ',', ' ');

    // Chiffres clés : [icône, fond de la pastille, couleur de l'icône, valeur, libellé, détail]
    $statCards = [
        ['fa-play',          'bg-primary-600', 'text-white',       $n($stats['totalTestimonies']), __('admin.common.testimonies'),     __('admin.dashboard.pending', ['count' => $stats['pendingTestimonies']])],
        ['fa-users',         'bg-primary-600', 'text-white',       $n($stats['totalUsers']),       __('layout.nav.users'),             __('admin.dashboard.today', ['count' => $stats['newUsersToday']])],
        ['fa-eye',           'bg-accent-500',  'text-white',       $n($stats['totalViews']),       __('admin.dashboard.total_views'),  __('admin.dashboard.this_month', ['count' => $n($stats['viewsThisMonth'])])],
        ['fa-circle-check',  'bg-sun-400',     'text-primary-700', __('admin.formats.percent', ['value' => $stats['approvalRate']]),  __('admin.dashboard.approval_rate'), __('admin.dashboard.approved', ['count' => $n($stats['approvedTestimonies'])])],
    ];

    $quickActions = [
        [route('publish'),                              'fa-plus',            __('admin.dashboard.add_testimony'),   'btn-primary'],
        [route('admin.users.index'),                    'fa-user-gear',       __('admin.dashboard.manage_users'),    'btn-secondary'],
        [route('moderation.index'),                     'fa-shield-halved',   __('admin.dashboard.open_moderation'), 'btn-accent'],
        [route('admin.categories.index'),               'fa-tags',            __('layout.nav.categories'),           'btn-secondary'],
        [route('admin.event-types.index'),              'fa-calendar-check',  __('layout.nav.event_types'),          'btn-secondary'],
        [route('admin.settings'),                       'fa-gear',            __('layout.nav.admin_settings'),       'btn-secondary'],
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
        <p class="text-sm font-medium text-primary-100">{{ __('admin.dashboard.hello', ['name' => auth()->user()->display_name]) }}</p>
        <h2 id="dashboard-welcome" class="mt-1 max-w-lg pr-10 text-2xl font-bold sm:text-3xl">{{ __('admin.dashboard.welcome_title') }}</h2>
        <p class="mt-2 max-w-lg text-sm text-primary-100">{{ __('admin.dashboard.welcome_text') }}</p>

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
        <h2 id="quick-actions-title" class="card-title mb-4">{{ __('admin.dashboard.quick_actions') }}</h2>
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
            <h2 id="activity-title" class="card-title">{{ __('admin.dashboard.activity_title') }}</h2>
            <p class="flex items-center gap-4 text-xs text-slate-500">
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-primary-600" aria-hidden="true"></span>{{ __('admin.common.testimonies') }}</span>
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-accent-500" aria-hidden="true"></span>{{ __('admin.dashboard.signups') }}</span>
            </p>
        </div>

        <div class="flex h-44 items-end gap-2 border-b border-slate-200 sm:gap-4" role="img"
             aria-label="{{ __('admin.dashboard.activity_summary', ['testimonies' => $weekTestimonies, 'users' => $weekUsers]) }}">
            @foreach($activity as $day)
            <div class="flex h-full min-w-0 flex-1 items-end justify-center gap-1" title="{{ __('admin.dashboard.activity_day', ['date' => $day['date']->translatedFormat('l j F'), 'testimonies' => $day['testimonies'], 'users' => $day['users']]) }}">
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
                <dt class="text-xs text-slate-500">{{ __('admin.dashboard.new_testimonies') }}</dt>
                <dd class="text-xl font-bold text-primary-700">{{ $n($weekTestimonies) }}</dd>
            </div>
            <div class="rounded-xl border border-slate-200 p-3">
                <dt class="text-xs text-slate-500">{{ __('admin.dashboard.new_signups') }}</dt>
                <dd class="text-xl font-bold text-primary-700">{{ $n($weekUsers) }}</dd>
            </div>
            <div class="rounded-xl border border-slate-200 p-3">
                <dt class="text-xs text-slate-500">{{ __('admin.dashboard.comments_month') }}</dt>
                <dd class="text-xl font-bold text-primary-700">{{ $n($stats['commentsThisMonth']) }}</dd>
            </div>
        </dl>
    </section>

    {{-- ── Modération rapide ────────────────────────────────────────────── --}}
    <section class="card flex min-w-0 flex-col" aria-labelledby="moderation-title">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
            <h2 id="moderation-title" class="card-title">{{ __('admin.dashboard.quick_moderation') }}</h2>
            @if($stats['pendingTestimonies'] > 0)
            <span class="badge-orange">{{ __('admin.dashboard.pending', ['count' => $stats['pendingTestimonies']]) }}</span>
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
                <span class="badge-orange shrink-0">{{ __('admin.dashboard.to_review') }}</span>
            </li>
            @empty
            <li class="flex flex-col items-center gap-2 px-5 py-8 text-center text-sm text-slate-500">
                <i class="fa-solid fa-circle-check text-2xl text-success-500" aria-hidden="true"></i>
                <span>{{ __('admin.dashboard.nothing_to_review') }}</span>
            </li>
            @endforelse
            @if(($stats['pendingOrganizations'] ?? 0) > 0)
            <li class="px-5 py-3">
                <a href="{{ route('admin.users.index', ['tab' => 'pending']) }}" class="card-insight flex items-center gap-3 p-3 text-sm hover:border-sun-400">
                    <i class="fa-solid fa-building-circle-check text-sun-700" aria-hidden="true"></i>
                    <span class="min-w-0 flex-1"><span class="font-semibold">{{ __('admin.dashboard.pending_orgs', ['count' => $stats['pendingOrganizations']]) }}</span> {{ __('admin.dashboard.to_verify') }}</span>
                    <i class="fa-solid fa-arrow-right text-xs text-sun-700" aria-hidden="true"></i>
                </a>
            </li>
            @endif
            @if(($stats['pendingAccountVerifications'] ?? 0) > 0)
            <li class="px-5 py-3">
                <a href="{{ route('admin.verifications.index') }}" class="card-insight flex items-center gap-3 p-3 text-sm hover:border-sun-400">
                    <i class="fa-solid fa-user-check text-sun-700" aria-hidden="true"></i>
                    <span class="min-w-0 flex-1"><span class="font-semibold">{{ __('admin.dashboard.pending_personal', ['count' => $stats['pendingAccountVerifications']]) }}</span> {{ __('admin.dashboard.to_verify') }}</span>
                    <i class="fa-solid fa-arrow-right text-xs text-sun-700" aria-hidden="true"></i>
                </a>
            </li>
            @endif
        </ul>
        <div class="mt-auto p-5 pt-2">
            <a href="{{ route('moderation.index') }}" class="btn-primary w-full">{{ __('admin.dashboard.open_queue') }}</a>
        </div>
    </section>

    {{-- ── Derniers témoignages ─────────────────────────────────────────── --}}
    <section class="card min-w-0" aria-labelledby="recent-testimonies-title">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
            <h2 id="recent-testimonies-title" class="card-title">{{ __('admin.common.recent_testimonies') }}</h2>
            <a href="{{ route('admin.content.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">{{ __('admin.common.view_all') }}<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse($recentTestimonies as $t)
            <li class="flex items-center gap-3 px-5 py-3">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ $t->title }}</p>
                    <p class="flex min-w-0 items-center gap-2 text-xs text-slate-500">
                        @if($t->category)<span class="badge-blue shrink-0">{{ $t->category->displayName() }}</span>@endif
                        <span class="truncate">{{ $t->user?->display_name }} · {{ $t->created_at->format(__('admin.formats.date')) }}</span>
                    </p>
                </div>
                <span class="{{ $t->status->badgeClass() }}">{{ $t->status->label() }}</span>
            </li>
            @empty
            <li class="px-5 py-6 text-center text-sm text-slate-500">{{ __('admin.common.no_testimony') }}</li>
            @endforelse
        </ul>
    </section>

    {{-- ── Les plus regardés ────────────────────────────────────────────── --}}
    <section class="card min-w-0" aria-labelledby="popular-title">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
            <h2 id="popular-title" class="card-title">{{ __('admin.dashboard.most_viewed') }}</h2>
            <a href="{{ route('explore') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">{{ __('admin.common.view_all') }}<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
        </div>
        <ol class="divide-y divide-slate-100">
            @forelse($popularTestimonies as $t)
            <li class="flex items-center gap-3 px-5 py-3">
                <span class="{{ $loop->first ? 'bg-sun-400 text-primary-700' : 'bg-primary-50 text-primary-600' }} flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold">{{ $loop->iteration }}</span>
                <div class="min-w-0 flex-1">
                    <a href="{{ route('testimonies.show', $t->id) }}" class="block truncate text-sm font-semibold text-slate-900 hover:text-primary-600 hover:underline">{{ $t->title }}</a>
                    <p class="truncate text-xs text-slate-500">{{ __('admin.dashboard.views_likes', ['views' => $n($t->views_count), 'likes' => $n($t->like_count ?? 0)]) }}</p>
                </div>
            </li>
            @empty
            <li class="px-5 py-6 text-center text-sm text-slate-500">{{ __('admin.dashboard.no_published') }}</li>
            @endforelse
        </ol>
    </section>

    {{-- ── Derniers utilisateurs ────────────────────────────────────────── --}}
    <section class="card min-w-0" aria-labelledby="recent-users-title">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
            <h2 id="recent-users-title" class="card-title">{{ __('admin.dashboard.recent_users') }}</h2>
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">{{ __('admin.common.view_all') }}<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
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
                    <p class="truncate text-xs text-slate-500">{{ __('admin.dashboard.joined', ['time' => $user->created_at->diffForHumans()]) }}</p>
                </div>
                <span class="{{ $user->isOrganization() ? 'badge-yellow' : 'badge-blue' }} shrink-0">{{ $user->isOrganization() ? __('admin.common.organization') : $user->role->label() }}</span>
            </li>
            @empty
            <li class="px-5 py-6 text-center text-sm text-slate-500">{{ __('admin.dashboard.no_users') }}</li>
            @endforelse
        </ul>
    </section>

    {{-- ── Catégories populaires ────────────────────────────────────────── --}}
    <section class="card p-5 xl:col-span-3" aria-labelledby="categories-title">
        <div class="mb-4 flex items-center justify-between gap-2">
            <h2 id="categories-title" class="card-title">{{ __('admin.dashboard.popular_categories') }}</h2>
            <a href="{{ route('admin.categories.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">{{ __('admin.common.manage') }}<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
        </div>
        @if($popularCategories->isEmpty())
        <p class="text-sm text-slate-500">{{ __('admin.dashboard.no_categories') }}</p>
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
                        <p class="truncate text-sm font-semibold text-slate-900">{{ $category->displayName() }}</p>
                        <p class="text-xs text-slate-500">{{ trans_choice('admin.dashboard.category_count', $category->published_count, ['count' => $n($category->published_count)]) }}</p>
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

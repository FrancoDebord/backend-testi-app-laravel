@extends('layouts.app')
{{-- Tableau de bord des témoignages : tendances, top, file d'attente (docs/fonctionnalites/gestion-des-temoignages.md) --}}
@section('title', 'Tableau de bord des témoignages')
@php
    $header      = 'Tableau de bord des témoignages';
    $subheader   = "Tendances des {$days} derniers jours.";
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Modération', 'url' => route('moderation.index')],
        ['label' => 'Tableau de bord'],
    ];
    $t = $series['totals'];
    $p = $series['previous'];
    $trend = [['Vues', 'views'], ['Réactions', 'reactions'], ['Commentaires', 'comments'], ['Partages', 'shares']];
@endphp

@section('content')
<nav class="mb-6 flex flex-wrap gap-2" aria-label="Période">
    @foreach([7, 30, 90] as $d)
    <a href="{{ route('moderation.dashboard', ['jours' => $d]) }}" class="{{ $days === $d ? 'chip-active' : 'chip' }}" @if($days === $d) aria-current="page" @endif>{{ $d }} jours</a>
    @endforeach
</nav>

<div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
    <a href="{{ route('moderation.index') }}" class="card p-4 hover:border-primary-200">
        <p class="flex items-center gap-2 text-sm text-slate-500"><span class="h-2 w-2 rounded-full bg-amber-500" aria-hidden="true"></span>En attente</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900 tabular-nums">{{ $pendingCount }}</p>
    </a>
    <div class="card p-4"><p class="text-sm text-slate-500">Publiés</p><p class="mt-1 text-2xl font-semibold text-slate-900 tabular-nums">{{ number_format($counts['approved'], 0, ',', ' ') }}</p></div>
    <div class="card p-4"><p class="text-sm text-slate-500">Label « approuvé »</p><p class="mt-1 text-2xl font-semibold text-slate-900 tabular-nums">{{ $counts['verified'] }}</p></div>
    <a href="{{ route('moderation.index', ['onglet' => 'featured']) }}" class="card p-4 hover:border-primary-200"><p class="text-sm text-slate-500">À la une</p><p class="mt-1 text-2xl font-semibold text-slate-900 tabular-nums">{{ $counts['featured'] }}</p></a>
    <a href="{{ route('moderation.index', ['onglet' => 'trash']) }}" class="card p-4 hover:border-primary-200"><p class="text-sm text-slate-500">Corbeille</p><p class="mt-1 text-2xl font-semibold text-slate-900 tabular-nums">{{ $counts['trash'] }}</p></a>
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <section class="card p-5 xl:col-span-2" aria-labelledby="trend-title">
        <h2 id="trend-title" class="card-title mb-3">Tendances</h2>
        <dl class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach($trend as [$label, $key])
            @php($change = \App\Services\TestimonyStats::change($t[$key], $p[$key]))
            <div>
                <dt class="text-xs text-slate-500">{{ $label }}</dt>
                <dd class="text-lg font-semibold text-slate-900 tabular-nums">{{ number_format($t[$key], 0, ',', ' ') }}
                    @if($change !== null)<span class="ml-1 text-xs font-medium {{ $change >= 0 ? 'text-success-700' : 'text-error-700' }}">{{ $change >= 0 ? '+' : '' }}{{ $change }} %</span>@endif
                </dd>
            </div>
            @endforeach
        </dl>
        @include('moderation.partials.chart', [
            'days'  => $series['days'],
            'lines' => [['key' => 'views', 'label' => 'Vues', 'color' => '#184797'], ['key' => 'reactions', 'label' => 'Réactions', 'color' => '#F18717']],
            'label' => "Vues et réactions de tous les témoignages sur {$days} jours",
        ])
    </section>

    <section class="card p-5" aria-labelledby="queue-title">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h2 id="queue-title" class="card-title">En attente de modération</h2>
            <a href="{{ route('moderation.index') }}" class="text-xs font-semibold text-primary-700 hover:underline">Tout voir</a>
        </div>
        @if($pending->isEmpty())
        <p class="text-sm text-slate-500">Aucun témoignage en attente.</p>
        @else
        <ul class="divide-y divide-slate-100 text-sm">
            @foreach($pending as $item)
            <li class="py-2">
                <a href="{{ route('moderation.show', $item->id) }}" class="block min-w-0 hover:text-primary-700">
                    <span class="block truncate font-medium text-slate-900">{{ $item->title }}</span>
                    <span class="text-xs text-slate-500">{{ $item->user?->display_name }} · {{ $item->created_at->diffForHumans() }}</span>
                </a>
            </li>
            @endforeach
        </ul>
        @endif
    </section>
</div>

<section class="card mt-6 overflow-hidden" aria-labelledby="top-title">
    <h2 id="top-title" class="card-title px-5 pt-5">Témoignages les plus vus ({{ $days }} jours)</h2>
    @if($top->isEmpty())
    <p class="p-5 text-sm text-slate-500">Pas encore de statistiques sur cette période.</p>
    @else
    <ol class="divide-y divide-slate-100 p-5 text-sm md:hidden">
        @foreach($top as $i => $row)
        <li class="py-2">
            <a href="{{ route('moderation.stats', $row['testimony']->id) }}" class="block min-w-0">
                <span class="block truncate font-medium text-slate-900">{{ $i + 1 }}. {{ $row['testimony']->title }}</span>
                <span class="text-xs text-slate-500 tabular-nums">{{ $row['views'] }} vues · {{ $row['reactions'] }} réactions · {{ $row['comments'] }} commentaires</span>
            </a>
        </li>
        @endforeach
    </ol>
    <div class="hidden overflow-x-auto md:block">
        <table class="mt-3 min-w-full">
            <thead><tr>
                <th class="table-th">#</th><th class="table-th">Témoignage</th><th class="table-th">Auteur</th>
                <th class="table-th text-right">Vues</th><th class="table-th text-right">Réactions</th><th class="table-th text-right">Commentaires</th><th class="table-th text-right">Partages</th>
            </tr></thead>
            <tbody>
                @foreach($top as $i => $row)
                <tr class="hover:bg-slate-50">
                    <td class="table-td tabular-nums">{{ $i + 1 }}</td>
                    <td class="table-td max-w-xs"><a href="{{ route('moderation.stats', $row['testimony']->id) }}" class="block truncate font-medium text-primary-700 hover:underline">{{ $row['testimony']->title }}</a></td>
                    <td class="table-td whitespace-nowrap">{{ $row['testimony']->user?->display_name }}</td>
                    <td class="table-td text-right tabular-nums">{{ $row['views'] }}</td>
                    <td class="table-td text-right tabular-nums">{{ $row['reactions'] }}</td>
                    <td class="table-td text-right tabular-nums">{{ $row['comments'] }}</td>
                    <td class="table-td text-right tabular-nums">{{ $row['shares'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</section>
@endsection

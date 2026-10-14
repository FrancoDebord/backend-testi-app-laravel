@extends('layouts.app')
{{-- Statistiques d'un témoignage sur 7, 30 ou 90 jours (docs/fonctionnalites/gestion-des-temoignages.md) --}}
@section('title', 'Statistiques — ' . $testimony->title)
@php
    $header      = 'Statistiques';
    $subheader   = $testimony->title;
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Modération', 'url' => route('moderation.index')],
        ['label' => Str::limit($testimony->title, 30), 'url' => route('moderation.show', $testimony->id)],
        ['label' => 'Statistiques'],
    ];
    $t = $series['totals'];
    $p = $series['previous'];
    $watchHours = round($t['video_watch_seconds'] / 3600, 1);
    $cards = [
        ['Vues', $t['views'], $p['views']],
        ['Spectateurs uniques (somme par jour)', $t['unique_viewers'], $p['unique_viewers']],
        ['Réactions', $t['reactions'], $p['reactions']],
        ['Commentaires', $t['comments'], $p['comments']],
        ['Partages', $t['shares'], $p['shares']],
    ];
@endphp

@section('headerActions')
    <a href="{{ route('moderation.show', $testimony->id) }}" class="btn-ghost btn-sm"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>Fiche du témoignage</a>
@endsection

@section('content')
<nav class="mb-6 flex flex-wrap gap-2" aria-label="Période">
    @foreach([7, 30, 90] as $d)
    <a href="{{ route('moderation.stats', ['id' => $testimony->id, 'jours' => $d]) }}" class="{{ $days === $d ? 'chip-active' : 'chip' }}" @if($days === $d) aria-current="page" @endif>{{ $d }} jours</a>
    @endforeach
</nav>

<div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
    @foreach($cards as [$label, $value, $previous])
    @php($change = \App\Services\TestimonyStats::change($value, $previous))
    <div class="card p-4">
        <p class="text-sm text-slate-500">{{ $label }}</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900 tabular-nums">{{ number_format($value, 0, ',', ' ') }}</p>
        @if($change !== null)<p class="text-xs {{ $change >= 0 ? 'text-success-700' : 'text-error-700' }}">{{ $change >= 0 ? '+' : '' }}{{ $change }} % vs période précédente</p>@endif
    </div>
    @endforeach
    <div class="card p-4">
        <p class="text-sm text-slate-500">Vidéo regardée</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900 tabular-nums">{{ number_format($watchHours, 1, ',', ' ') }} h</p>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <section class="card p-5" aria-labelledby="views-chart">
        <h2 id="views-chart" class="card-title mb-3">Audience</h2>
        @include('moderation.partials.chart', [
            'days'  => $series['days'],
            'lines' => [['key' => 'views', 'label' => 'Vues', 'color' => '#184797'], ['key' => 'unique_viewers', 'label' => 'Spectateurs uniques', 'color' => '#667085']],
            'label' => "Vues et spectateurs uniques sur {$days} jours",
        ])
    </section>
    <section class="card p-5" aria-labelledby="engagement-chart">
        <h2 id="engagement-chart" class="card-title mb-3">Participation</h2>
        @include('moderation.partials.chart', [
            'days'  => $series['days'],
            'lines' => [['key' => 'reactions', 'label' => 'Réactions', 'color' => '#F18717'], ['key' => 'comments', 'label' => 'Commentaires', 'color' => '#184797'], ['key' => 'shares', 'label' => 'Partages', 'color' => '#667085']],
            'label' => "Réactions, commentaires et partages sur {$days} jours",
        ])
    </section>
</div>

{{-- Détail par jour : tableau (ordinateur) / liste (mobile) --}}
<section class="card mt-6 overflow-hidden" aria-labelledby="days-title">
    <h2 id="days-title" class="card-title px-5 pt-5">Détail par jour</h2>
    <ul class="divide-y divide-slate-100 p-5 text-sm md:hidden">
        @foreach(array_reverse($series['days']) as $d)
        <li class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2">
            <span class="font-medium text-slate-900">{{ \Illuminate\Support\Carbon::parse($d['date'])->translatedFormat('D j M') }}</span>
            <span class="text-slate-600 tabular-nums">{{ $d['views'] }} vues · {{ $d['reactions'] }} réac. · {{ $d['comments'] }} com. · {{ $d['shares'] }} part.</span>
        </li>
        @endforeach
    </ul>
    <div class="hidden overflow-x-auto md:block">
        <table class="mt-3 min-w-full">
            <thead><tr>
                <th class="table-th">Jour</th><th class="table-th text-right">Vues</th><th class="table-th text-right">Uniques</th>
                <th class="table-th text-right">Réactions</th><th class="table-th text-right">Commentaires</th><th class="table-th text-right">Partages</th><th class="table-th text-right">Vidéo (min)</th>
            </tr></thead>
            <tbody>
                @foreach(array_reverse($series['days']) as $d)
                <tr>
                    <td class="table-td whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($d['date'])->translatedFormat('l j F') }}</td>
                    <td class="table-td text-right tabular-nums">{{ $d['views'] }}</td>
                    <td class="table-td text-right tabular-nums">{{ $d['unique_viewers'] }}</td>
                    <td class="table-td text-right tabular-nums">{{ $d['reactions'] }}</td>
                    <td class="table-td text-right tabular-nums">{{ $d['comments'] }}</td>
                    <td class="table-td text-right tabular-nums">{{ $d['shares'] }}</td>
                    <td class="table-td text-right tabular-nums">{{ round($d['video_watch_seconds'] / 60) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="px-5 pb-5 text-xs text-slate-500">Aujourd’hui est calculé en direct ; les jours précédents sont figés chaque nuit (heure du Bénin).</p>
</section>
@endsection

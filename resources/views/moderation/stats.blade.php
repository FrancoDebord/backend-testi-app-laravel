@extends('layouts.app')
{{-- Statistiques d'un témoignage sur 7, 30 ou 90 jours (docs/fonctionnalites/gestion-des-temoignages.md) --}}
@section('title', __('moderation.stats.title', ['title' => $testimony->title]))
@php
    $header      = __('moderation.common.statistics');
    $subheader   = $testimony->title;
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.moderation'), 'url' => route('moderation.index')],
        ['label' => Str::limit($testimony->title, 30), 'url' => route('moderation.show', $testimony->id)],
        ['label' => __('moderation.common.statistics')],
    ];
    $t = $series['totals'];
    $p = $series['previous'];
    $watchHours = round($t['video_watch_seconds'] / 3600, 1);
    $cards = [
        [__('moderation.common.views'), $t['views'], $p['views']],
        [__('moderation.stats.unique_viewers_sum'), $t['unique_viewers'], $p['unique_viewers']],
        [__('moderation.common.reactions'), $t['reactions'], $p['reactions']],
        [__('moderation.common.comments'), $t['comments'], $p['comments']],
        [__('moderation.common.shares'), $t['shares'], $p['shares']],
    ];
@endphp

@section('headerActions')
    <a href="{{ route('moderation.show', $testimony->id) }}" class="btn-ghost btn-sm"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>{{ __('moderation.stats.testimony_page') }}</a>
@endsection

@section('content')
<nav class="mb-6 flex flex-wrap gap-2" aria-label="{{ __('moderation.common.period') }}">
    @foreach([7, 30, 90] as $d)
    <a href="{{ route('moderation.stats', ['id' => $testimony->id, 'jours' => $d]) }}" class="{{ $days === $d ? 'chip-active' : 'chip' }}" @if($days === $d) aria-current="page" @endif>{{ __('moderation.common.days', ['days' => $d]) }}</a>
    @endforeach
</nav>

<div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
    @foreach($cards as [$label, $value, $previous])
    @php($change = \App\Services\TestimonyStats::change($value, $previous))
    <div class="card p-4">
        <p class="text-sm text-slate-500">{{ $label }}</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900 tabular-nums">{{ \App\Support\Locales::number($value) }}</p>
        @if($change !== null)<p class="text-xs {{ $change >= 0 ? 'text-success-700' : 'text-error-700' }}">{{ $change >= 0 ? '+' : '' }}{{ __('moderation.stats.vs_previous', ['change' => $change]) }}</p>@endif
    </div>
    @endforeach
    <div class="card p-4">
        <p class="text-sm text-slate-500">{{ __('moderation.stats.video_watched') }}</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900 tabular-nums">{{ __('moderation.stats.hours', ['hours' => number_format($watchHours, 1, __('moderation.common.decimal_point'), __('moderation.common.thousands_sep'))]) }}</p>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <section class="card p-5" aria-labelledby="views-chart">
        <h2 id="views-chart" class="card-title mb-3">{{ __('moderation.stats.audience') }}</h2>
        @include('moderation.partials.chart', [
            'days'  => $series['days'],
            'lines' => [['key' => 'views', 'label' => __('moderation.common.views'), 'color' => '#184797'], ['key' => 'unique_viewers', 'label' => __('moderation.stats.unique_viewers'), 'color' => '#667085']],
            'label' => __('moderation.stats.audience_label', ['days' => $days]),
        ])
    </section>
    <section class="card p-5" aria-labelledby="engagement-chart">
        <h2 id="engagement-chart" class="card-title mb-3">{{ __('moderation.stats.engagement') }}</h2>
        @include('moderation.partials.chart', [
            'days'  => $series['days'],
            'lines' => [['key' => 'reactions', 'label' => __('moderation.common.reactions'), 'color' => '#F18717'], ['key' => 'comments', 'label' => __('moderation.common.comments'), 'color' => '#184797'], ['key' => 'shares', 'label' => __('moderation.common.shares'), 'color' => '#667085']],
            'label' => __('moderation.stats.engagement_label', ['days' => $days]),
        ])
    </section>
</div>

{{-- Détail par jour : tableau (ordinateur) / liste (mobile) --}}
<section class="card mt-6 overflow-hidden" aria-labelledby="days-title">
    <h2 id="days-title" class="card-title px-5 pt-5">{{ __('moderation.stats.per_day') }}</h2>
    <ul class="divide-y divide-slate-100 p-5 text-sm md:hidden">
        @foreach(array_reverse($series['days']) as $d)
        <li class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2">
            <span class="font-medium text-slate-900">{{ \Illuminate\Support\Carbon::parse($d['date'])->translatedFormat(__('moderation.stats.day_short_format')) }}</span>
            <span class="text-slate-600 tabular-nums">{{ __('moderation.stats.day_summary', ['views' => $d['views'], 'reactions' => $d['reactions'], 'comments' => $d['comments'], 'shares' => $d['shares']]) }}</span>
        </li>
        @endforeach
    </ul>
    <div class="hidden overflow-x-auto md:block">
        <table class="mt-3 min-w-full">
            <thead><tr>
                <th class="table-th">{{ __('moderation.stats.day') }}</th><th class="table-th text-right">{{ __('moderation.common.views') }}</th><th class="table-th text-right">{{ __('moderation.stats.uniques') }}</th>
                <th class="table-th text-right">{{ __('moderation.common.reactions') }}</th><th class="table-th text-right">{{ __('moderation.common.comments') }}</th><th class="table-th text-right">{{ __('moderation.common.shares') }}</th><th class="table-th text-right">{{ __('moderation.stats.video_minutes') }}</th>
            </tr></thead>
            <tbody>
                @foreach(array_reverse($series['days']) as $d)
                <tr>
                    <td class="table-td whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($d['date'])->translatedFormat(__('moderation.stats.day_long_format')) }}</td>
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
    <p class="px-5 pb-5 text-xs text-slate-500">{{ __('moderation.stats.footnote') }}</p>
</section>
@endsection

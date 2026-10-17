{{--
    Courbes des statistiques (SVG, sans bibliothèque), même style que « Statistiques globales » de l'accueil.
    @include('moderation.partials.chart', [
        'days'   => $series['days'],                       // [['date' => 'AAAA-MM-JJ', 'views' => …], …]
        'lines'  => [['key' => 'views', 'label' => 'Vues', 'color' => '#184797'], …],
        'label'  => 'Description accessible',
    ])
--}}
@php
    $chartDays = collect($days)->values();
    $n         = max(1, $chartDays->count() - 1);
    $chartMax  = max(1, ...collect($lines)->map(fn ($l) => (int) $chartDays->max($l['key']))->all());
    $pts       = fn (string $key) => $chartDays->map(fn ($d, $i) =>
        round(10 + $i * (580 / $n), 1) . ',' . round(150 - ($d[$key] ?? 0) / $chartMax * 135, 1))->implode(' ');
    $every     = max(1, (int) ceil($chartDays->count() / 7));
@endphp
<figure class="min-w-0">
    <svg viewBox="0 0 600 160" preserveAspectRatio="none" class="h-40 w-full sm:h-48" role="img" aria-label="{{ $label }}">
        @foreach([15, 60, 105, 150] as $y)
        <line x1="10" x2="590" y1="{{ $y }}" y2="{{ $y }}" stroke="#E4E7EC" stroke-width="1" vector-effect="non-scaling-stroke"/>
        @endforeach
        @foreach($lines as $line)
        <polyline points="{{ $pts($line['key']) }}" fill="none" stroke="{{ $line['color'] }}" stroke-width="2.5"
                  stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
        @endforeach
    </svg>
    <p class="mt-1 flex justify-between text-[10px] text-slate-400" aria-hidden="true">
        @foreach($chartDays as $i => $d)
            @if($i % $every === 0 || $i === $chartDays->count() - 1)
            <span>{{ \Illuminate\Support\Carbon::parse($d['date'])->format(__('moderation.common.day_month_format')) }}</span>
            @endif
        @endforeach
    </p>
    <figcaption class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
        @foreach($lines as $line)
        <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background: {{ $line['color'] }}" aria-hidden="true"></span>{{ __('moderation.common.chart_total', ['label' => $line['label']]) }} <strong class="tabular-nums">{{ \App\Support\Locales::number($chartDays->sum($line['key'])) }}</strong></span>
        @endforeach
        <span class="text-slate-400">{{ __('moderation.common.chart_max', ['max' => \App\Support\Locales::number($chartMax)]) }}</span>
    </figcaption>
</figure>

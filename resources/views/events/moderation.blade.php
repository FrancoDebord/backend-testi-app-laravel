@extends('layouts.app')
@section('title', 'Événements signalés')
@use('App\Http\Controllers\Web\EventController', 'EventPage')
@php
    $header      = 'Événements signalés';
    $subheader   = 'Signalements, événements non confirmés par le sondage des participants, événements masqués. Au 3e signalement non traité, un événement est masqué automatiquement en attendant votre décision.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Modération', 'url' => route('moderation.index')],
        ['label' => 'Événements'],
    ];
    $filters = ['reported' => ['Signalés', null], 'unconfirmed' => ['Non confirmés', 'non-confirmes'], 'hidden' => ['Masqués', 'masques']];
@endphp

@section('content')
<div class="mb-5 flex flex-wrap gap-2" role="group" aria-label="Filtre">
    @foreach($filters as $key => [$label, $param])
    <a href="{{ route('events.moderation.index', array_filter(['filtre' => $param])) }}" class="{{ $filter === $key ? 'chip-active' : 'chip' }}" @if($filter === $key) aria-current="true" @endif>{{ $label }}</a>
    @endforeach
</div>

@if($items->isEmpty())
    @include('components.empty-state', ['title' => match ($filter) {
        'hidden'      => 'Aucun événement masqué',
        'unconfirmed' => 'Aucun événement non confirmé à examiner',
        default       => 'Aucun signalement à traiter',
    }])
@else
<ul class="mb-6 space-y-4">
    @foreach($items as $item)
    @php $rel = $reliability[$item->organizer_id] ?? \App\Models\Event::reliabilityOf($item->organizer_id); @endphp
    <li class="card p-5">
        <div class="flex flex-wrap items-center gap-2">
            <p class="mr-auto min-w-0 text-sm">
                <a href="{{ route('events.show', $item->id) }}" class="font-semibold text-primary-700 hover:underline">{{ $item->title }}</a>
                <span class="block text-xs text-slate-500">{{ EventPage::dateRange($item) }} · {{ $item->organizer?->display_name }}</span>
            </p>
            @include('events.partials.status-badges', ['event' => $item, 'showStatus' => true])
            @if($item->pending_reports_count)
            <span class="badge-orange">{{ $item->pending_reports_count }} signalement{{ $item->pending_reports_count > 1 ? 's' : '' }} à traiter</span>
            @endif
        </div>

        @if($item->isHidden() && $item->hidden_reason)<p class="mt-2 text-xs text-slate-500">Motif du masquage : {{ $item->hidden_reason }}</p>@endif
        @if($item->surveyTotal() > 0)
        <p class="mt-2 text-xs text-slate-600">Sondage : {{ $item->survey_yes_count }} oui · {{ $item->survey_no_count }} non · {{ $item->survey_absent_count }} absent{{ $item->survey_absent_count > 1 ? 's' : '' }}@if($item->ratingAverage() !== null) · ★ {{ number_format($item->ratingAverage(), 1, ',', ' ') }}@endif</p>
        @endif

        @if($item->reports->isNotEmpty())
        <ul class="mt-3 space-y-1 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
            @foreach($item->reports as $report)
            <li class="{{ $report->reviewed_at ? 'opacity-60' : '' }}">
                <span class="font-semibold">{{ $reasons[$report->reason] ?? $report->reason }}</span>
                — {{ $report->reporter?->display_name }}@if($report->comment) : « {{ $report->comment }} »@endif
            </li>
            @endforeach
        </ul>
        @endif

        @include('events.partials.reliability', ['reliability' => $rel])

        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('events.show', $item->id) }}" class="btn-ghost btn-sm"><i class="fa-regular fa-eye" aria-hidden="true"></i>Voir</a>
            @if($item->isHidden())
            <form method="POST" action="{{ route('events.moderation.restore', $item->id) }}" data-loading-label="Rétablissement…">
                @csrf
                <button type="submit" class="btn-secondary btn-sm"><i class="fa-solid fa-eye" aria-hidden="true"></i>Rétablir</button>
            </form>
            @else
            <form method="POST" action="{{ route('events.moderation.dismiss', $item->id) }}" data-loading-label="Enregistrement…">
                @csrf
                <button type="submit" class="btn-secondary btn-sm"><i class="fa-solid fa-check" aria-hidden="true"></i>Garder (rejeter les signalements)</button>
            </form>
            <form method="POST" action="{{ route('events.moderation.hide', $item->id) }}" class="flex gap-2" data-loading-label="Masquage…">
                @csrf
                <label for="reason-{{ $item->id }}" class="sr-only">Motif</label>
                <input id="reason-{{ $item->id }}" type="text" name="reason" maxlength="300" class="form-input py-1.5 text-sm" placeholder="Motif (facultatif)">
                <button type="submit" class="btn-secondary btn-sm text-error-700"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i>Masquer</button>
            </form>
            @endif
        </div>
    </li>
    @endforeach
</ul>
{{ $items->links() }}
@endif
@endsection

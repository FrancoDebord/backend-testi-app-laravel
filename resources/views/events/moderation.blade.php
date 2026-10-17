@extends('layouts.app')
@section('title', __('layout.nav.mod_events'))
@use('App\Http\Controllers\Web\EventController', 'EventPage')
@php
    $header      = __('layout.nav.mod_events');
    $subheader   = __('events.moderation.subheader');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.moderation'), 'url' => route('moderation.index')],
        ['label' => __('layout.nav.events')],
    ];
    $filters = ['reported' => [__('events.moderation.filters.reported'), null], 'unconfirmed' => [__('events.moderation.filters.unconfirmed'), 'non-confirmes'], 'hidden' => [__('events.moderation.filters.hidden'), 'masques']];
@endphp

@section('content')
<div class="mb-5 flex flex-wrap gap-2" role="group" aria-label="{{ __('events.moderation.filter') }}">
    @foreach($filters as $key => [$label, $param])
    <a href="{{ route('events.moderation.index', array_filter(['filtre' => $param])) }}" class="{{ $filter === $key ? 'chip-active' : 'chip' }}" @if($filter === $key) aria-current="true" @endif>{{ $label }}</a>
    @endforeach
</div>

@if($items->isEmpty())
    @include('components.empty-state', ['title' => match ($filter) {
        'hidden'      => __('events.moderation.empty_hidden'),
        'unconfirmed' => __('events.moderation.empty_unconfirmed'),
        default       => __('events.moderation.empty_reported'),
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
            <span class="badge-orange">{{ trans_choice('events.moderation.pending_reports', $item->pending_reports_count, ['count' => \App\Support\Locales::number($item->pending_reports_count)]) }}</span>
            @endif
        </div>

        @if($item->isHidden() && $item->hidden_reason)<p class="mt-2 text-xs text-slate-500">{{ __('events.moderation.hidden_reason', ['reason' => $item->hidden_reason]) }}</p>@endif
        @if($item->surveyTotal() > 0)
        <p class="mt-2 text-xs text-slate-600">{{ trans_choice('events.moderation.survey', $item->survey_absent_count, ['yes' => $item->survey_yes_count, 'no' => $item->survey_no_count, 'count' => $item->survey_absent_count]) }}@if($item->ratingAverage() !== null) · ★ {{ \App\Support\Locales::number($item->ratingAverage(), 1) }}@endif</p>
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
            <a href="{{ route('events.show', $item->id) }}" class="btn-ghost btn-sm"><i class="fa-regular fa-eye" aria-hidden="true"></i>{{ __('events.moderation.view') }}</a>
            @if($item->isHidden())
            <form method="POST" action="{{ route('events.moderation.restore', $item->id) }}" data-loading-label="{{ __('events.moderation_card.restoring') }}">
                @csrf
                <button type="submit" class="btn-secondary btn-sm"><i class="fa-solid fa-eye" aria-hidden="true"></i>{{ __('events.moderation_card.restore') }}</button>
            </form>
            @else
            <form method="POST" action="{{ route('events.moderation.dismiss', $item->id) }}" data-loading-label="{{ __('events.saving') }}">
                @csrf
                <button type="submit" class="btn-secondary btn-sm"><i class="fa-solid fa-check" aria-hidden="true"></i>{{ __('events.moderation.keep') }}</button>
            </form>
            <form method="POST" action="{{ route('events.moderation.hide', $item->id) }}" class="flex gap-2" data-loading-label="{{ __('events.moderation_card.hiding') }}">
                @csrf
                <label for="reason-{{ $item->id }}" class="sr-only">{{ __('events.moderation_card.reason') }}</label>
                <input id="reason-{{ $item->id }}" type="text" name="reason" maxlength="300" class="form-input py-1.5 text-sm" placeholder="{{ __('events.reason_optional') }}">
                <button type="submit" class="btn-secondary btn-sm text-error-700"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i>{{ __('events.moderation_card.hide') }}</button>
            </form>
            @endif
        </div>
    </li>
    @endforeach
</ul>
{{ $items->links() }}
@endif
@endsection

@extends('layouts.app')
@section('title', __('prayer.moderation.header'))
@php
    $header      = __('prayer.moderation.header');
    $subheader   = __('prayer.moderation.subheader');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.moderation'), 'url' => route('moderation.index')],
        ['label' => __('prayer.requests.title')],
    ];
@endphp

@section('content')
<div class="mb-5 flex gap-2" role="group" aria-label="{{ __('prayer.moderation.filter') }}">
    <a href="{{ route('prayer.moderation.index') }}" class="{{ $filter === 'reported' ? 'chip-active' : 'chip' }}">{{ __('prayer.moderation.reported') }}</a>
    <a href="{{ route('prayer.moderation.index', ['filtre' => 'retirees']) }}" class="{{ $filter === 'hidden' ? 'chip-active' : 'chip' }}">{{ __('prayer.moderation.hidden') }}</a>
</div>

@if($items->isEmpty())
    @include('components.empty-state', ['title' => $filter === 'hidden' ? __('prayer.moderation.empty_hidden') : __('prayer.moderation.empty_reported')])
@else
<ul class="mb-6 space-y-4">
    @foreach($items as $item)
    <li class="card p-5">
        <div class="flex flex-wrap items-center gap-2">
            <p class="mr-auto text-sm font-semibold text-slate-900">
                {{ $item->user?->display_name }} @if($item->is_anonymous)<span class="badge-neutral">{{ __('prayer.common.anonymous_lower') }}</span>@endif
                <span class="text-xs font-normal text-slate-500">· {{ $item->created_at?->diffForHumans() }}</span>
            </p>
            @if($item->isHidden())<span class="badge-rejected">{{ __('prayer.moderation.hidden_one') }}</span>@endif
            <span class="badge-orange">{{ trans_choice('prayer.moderation.pending_reports', $item->pending_reports_count, ['count' => $item->pending_reports_count]) }}</span>
        </div>
        <p class="mt-3 text-sm break-words whitespace-pre-line text-slate-800">{{ Str::limit(\App\Services\Mentions::plain($item->body), 600) }}</p>
        @if($item->isHidden() && $item->hidden_reason)<p class="mt-2 text-xs text-slate-500">{{ __('prayer.moderation.reason_line', ['reason' => $item->hidden_reason]) }}</p>@endif

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

        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('prayer.requests.show', $item->id) }}" class="btn-ghost btn-sm"><i class="fa-regular fa-eye" aria-hidden="true"></i>{{ __('prayer.common.view') }}</a>
            @if($item->isHidden())
            <form method="POST" action="{{ route('prayer.moderation.restore', $item->id) }}" data-loading-label="{{ __('prayer.common.restoring') }}">
                @csrf
                <button type="submit" class="btn-secondary btn-sm"><i class="fa-solid fa-eye" aria-hidden="true"></i>{{ __('prayer.common.restore') }}</button>
            </form>
            @else
            <form method="POST" action="{{ route('prayer.moderation.restore', $item->id) }}" data-loading-label="{{ __('prayer.common.saving') }}">
                @csrf
                <button type="submit" class="btn-secondary btn-sm"><i class="fa-solid fa-check" aria-hidden="true"></i>{{ __('prayer.moderation.keep') }}</button>
            </form>
            <form method="POST" action="{{ route('prayer.moderation.hide', $item->id) }}" class="flex gap-2" data-loading-label="{{ __('prayer.common.removing') }}">
                @csrf
                <label for="reason-{{ $item->id }}" class="sr-only">{{ __('prayer.common.reason') }}</label>
                <input id="reason-{{ $item->id }}" type="text" name="reason" maxlength="300" class="form-input py-1.5 text-sm" placeholder="{{ __('prayer.moderation.reason_placeholder') }}">
                <button type="submit" class="btn-secondary btn-sm text-error-700"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i>{{ __('prayer.moderation.hide') }}</button>
            </form>
            @endif
            <form method="POST" action="{{ route('prayer.requests.destroy', $item->id) }}" data-loading-label="{{ __('prayer.common.deleting') }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn-ghost btn-sm text-error-700"><i class="fa-solid fa-trash" aria-hidden="true"></i>{{ __('prayer.common.delete') }}</button>
            </form>
        </div>
    </li>
    @endforeach
</ul>
{{ $items->links() }}
@endif
@endsection

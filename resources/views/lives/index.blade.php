@extends('layouts.app')
@section('title', __('lives.lives'))
@php
    $header      = __('lives.index.header');
    $subheader   = __('lives.index.subheader');
    $breadcrumbs = [
        ['label' => __('lives.home'), 'url' => route('home')],
        ['label' => __('lives.lives')],
    ];
    $canGoLive = Auth::user()?->canModerate();
@endphp

@if($canGoLive)
@section('headerActions')
    <a href="{{ route('lives.create') }}" class="btn-primary"><i class="fa-solid fa-video"></i>{{ __('lives.start_live') }}</a>
@endsection
@endif

@section('content')
@if($canGoLive && !$configured)
<div class="alert-warning mb-6" role="status">
    <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
    <p>{!! __('lives.index.not_configured', ['vars' => '<code>LIVEKIT_URL</code>, <code>LIVEKIT_API_KEY</code>, <code>LIVEKIT_API_SECRET</code>']) !!}</p>
</div>
@endif

<section class="mb-8">
    <h2 class="card-title mb-3">{{ __('lives.index.now') }}</h2>
    @if($active->isEmpty())
        @include('components.empty-state', [
            'title' => __('lives.index.empty_title'),
            'text' => __('lives.index.empty_text'),
        ])
    @else
    <div class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach($active as $live)
            @include('videos.partials.live-card', ['live' => $live])
        @endforeach
    </div>
    @endif
</section>

@if($recent->isNotEmpty())
<section>
    <h2 class="card-title mb-3">{{ __('lives.index.recent') }}</h2>
    <ul class="card divide-y divide-slate-100">
        @foreach($recent as $live)
        <li class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 sm:px-5">
            <div class="min-w-0 flex-1 basis-56">
                <p class="truncate text-sm font-medium text-slate-900">{{ $live->title }}</p>
                <p class="truncate text-xs text-slate-500">{{ $live->host->display_name }} · {{ $live->started_at->format(__('lives.index.date_format')) }}</p>
            </div>
            <dl class="flex gap-4 text-xs text-slate-500">
                <div><dt class="sr-only">{{ __('lives.index.duration') }}</dt><dd><i class="fa-regular fa-clock mr-1 text-slate-400"></i>{{ $live->started_at->diff($live->ended_at ?? $live->started_at)->format('%H:%I:%S') }}</dd></div>
                <div><dt class="sr-only">{{ __('lives.index.viewers') }}</dt><dd><i class="fa-regular fa-eye mr-1 text-slate-400"></i>{{ $live->peak_viewers }}</dd></div>
                <div><dt class="sr-only">{{ __('lives.index.comments') }}</dt><dd><i class="fa-regular fa-comment mr-1 text-slate-400"></i>{{ $live->comment_count }}</dd></div>
            </dl>
            @if($live->testimony && $live->testimony->status->value === 'approved')
                <a href="{{ route('testimonies.show', $live->testimony_id) }}" class="action-btn-view"><i class="fa-solid fa-play"></i>{{ __('lives.index.replay') }}</a>
            @elseif($live->record && Auth::user()?->canModerate())
                <span class="text-xs text-slate-500">{{ $live->recordingLabel() }}</span>
            @endif
        </li>
        @endforeach
    </ul>
</section>
@endif
@endsection

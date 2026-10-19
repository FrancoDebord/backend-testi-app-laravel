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
    {{-- Comme _RecentTile de l'application : toute la ligne ouvre le bilan du direct (lien étiré) ;
         la rediffusion reste un lien secondaire posé au-dessus (pas de lien imbriqué). --}}
    <ul class="card divide-y divide-slate-100 overflow-hidden">
        @foreach($recent as $live)
        @php
            $duration = $live->durationSeconds();
            $details = implode(' · ', array_filter([
                $duration !== null ? \App\Models\LiveSession::formatClock($duration) : null,
                trans_choice('lives_replay.recent.viewers', (int) $live->peak_viewers, ['count' => (int) $live->peak_viewers]),
                trans_choice('lives_replay.recent.comments', (int) $live->comment_count, ['count' => (int) $live->comment_count]),
            ]));
        @endphp
        <li class="relative flex items-center gap-3 px-4 py-3 transition hover:bg-slate-50 focus-within:bg-slate-50 sm:px-5" data-live-recent="{{ $live->id }}">
            @include('components.avatar', ['user' => $live->host, 'size' => 'md'])
            <div class="min-w-0 flex-1">
                <a href="{{ route('lives.show', $live->id) }}"
                   class="block truncate text-sm font-semibold text-slate-900 after:absolute after:inset-0 after:content-[''] focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:-outline-offset-2 focus-visible:after:outline-primary-500"
                   title="{{ __('lives_replay.recent.open') }}">{{ $live->title }}</a>
                <p class="truncate text-xs text-slate-500">{{ $live->host->display_name }}</p>
                <p class="truncate text-xs text-slate-500">
                    <time datetime="{{ $live->started_at->toIso8601String() }}" class="sr-only">{{ $live->started_at->format(__('lives.index.date_format')) }} · </time>{{ $details }}
                </p>
            </div>
            @if($live->testimony && $live->testimony->status->value === 'approved')
                <a href="{{ route('testimonies.show', $live->testimony_id) }}" class="action-btn-view relative z-10 shrink-0"><i class="fa-solid fa-play" aria-hidden="true"></i><span class="hidden sm:inline">{{ __('lives.index.replay') }}</span><span class="sr-only sm:hidden">{{ __('lives.index.replay') }}</span></a>
            @elseif($live->record && Auth::user()?->canModerate())
                <span class="hidden shrink-0 text-xs text-slate-500 sm:inline">{{ $live->recordingLabel() }}</span>
            @endif
            <i class="fa-solid fa-chevron-right shrink-0 text-xs text-slate-400" aria-hidden="true"></i>
        </li>
        @endforeach
    </ul>
</section>
@endif
@endsection

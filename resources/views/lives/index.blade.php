@extends('layouts.app')
@section('title', 'Directs')
@php
    $header      = 'Témoignages en direct';
    $subheader   = 'Regardez, commentez et réagissez aux témoignages diffusés en direct.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Directs'],
    ];
    $canGoLive = Auth::user()?->canModerate();
@endphp

@if($canGoLive)
@section('headerActions')
    <a href="{{ route('lives.create') }}" class="btn-primary"><i class="fa-solid fa-video"></i>Lancer un direct</a>
@endsection
@endif

@section('content')
@if($canGoLive && !$configured)
<div class="alert-warning mb-6" role="status">
    <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
    <p>Le service vidéo n'est pas encore configuré sur ce serveur (variables <code>LIVEKIT_URL</code>, <code>LIVEKIT_API_KEY</code>, <code>LIVEKIT_API_SECRET</code>). Les directs ne peuvent pas être lancés.</p>
</div>
@endif

<section class="mb-8">
    <h2 class="card-title mb-3">En ce moment</h2>
    @if($active->isEmpty())
        @include('components.empty-state', [
            'title' => 'Aucun direct en cours',
            'text' => 'Les témoignages diffusés en direct apparaîtront ici.',
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
    <h2 class="card-title mb-3">Directs récents</h2>
    <ul class="card divide-y divide-slate-100">
        @foreach($recent as $live)
        <li class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 sm:px-5">
            <div class="min-w-0 flex-1 basis-56">
                <p class="truncate text-sm font-medium text-slate-900">{{ $live->title }}</p>
                <p class="truncate text-xs text-slate-500">{{ $live->host->display_name }} · {{ $live->started_at->format('d/m/Y à H:i') }}</p>
            </div>
            <dl class="flex gap-4 text-xs text-slate-500">
                <div><dt class="sr-only">Durée</dt><dd><i class="fa-regular fa-clock mr-1 text-slate-400"></i>{{ $live->started_at->diff($live->ended_at ?? $live->started_at)->format('%H:%I:%S') }}</dd></div>
                <div><dt class="sr-only">Spectateurs</dt><dd><i class="fa-regular fa-eye mr-1 text-slate-400"></i>{{ $live->peak_viewers }}</dd></div>
                <div><dt class="sr-only">Commentaires</dt><dd><i class="fa-regular fa-comment mr-1 text-slate-400"></i>{{ $live->comment_count }}</dd></div>
            </dl>
            @if($live->testimony && $live->testimony->status->value === 'approved')
                <a href="{{ route('testimonies.show', $live->testimony_id) }}" class="action-btn-view"><i class="fa-solid fa-play"></i>Rediffusion</a>
            @elseif($live->record && Auth::user()?->canModerate())
                <span class="text-xs text-slate-500">{{ $live->recordingLabel() }}</span>
            @endif
        </li>
        @endforeach
    </ul>
</section>
@endif
@endsection

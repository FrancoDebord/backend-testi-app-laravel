@extends('layouts.app')
@section('title', 'Participants')
@php
    $going       = $status === \App\Models\EventParticipation::GOING;
    $header      = 'Participants';
    $subheader   = $event->title;
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Événements', 'url' => route('events.index')],
        ['label' => Str::limit($event->title, 40), 'url' => route('events.show', $event->id)],
        ['label' => 'Participants'],
    ];
@endphp

@section('headerActions')
    <a href="{{ route('events.show', $event->id) }}" class="btn-secondary"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>Retour à l'événement</a>
@endsection

@section('content')
<nav aria-label="Réponses" class="mb-4 border-b border-slate-200">
    <ul class="-mb-px flex gap-5 overflow-x-auto">
        <li><a href="{{ route('events.participants', $event->id) }}" class="{{ $going ? 'tab-active' : 'tab' }}" @if($going) aria-current="page" @endif>Je participe <span class="text-xs text-slate-500">{{ $event->going_count }}</span></a></li>
        <li><a href="{{ route('events.participants', [$event->id, 'statut' => 'non']) }}" class="{{ !$going ? 'tab-active' : 'tab' }}" @if(!$going) aria-current="page" @endif>Je ne participe pas <span class="text-xs text-slate-500">{{ $event->not_going_count }}</span></a></li>
    </ul>
</nav>

@if($items->isEmpty())
    @include('components.empty-state', [
        'title' => 'Aucune réponse',
        'text'  => $going ? "Personne n'a encore répondu « Je participe »." : "Personne n'a répondu « Je ne participe pas ».",
    ])
@else
<ul class="card mb-6 divide-y divide-slate-100">
    @foreach($items as $participation)
    <li class="flex items-center gap-3 px-4 py-3 sm:px-5">
        @include('components.avatar', ['user' => $participation->user, 'size' => 'sm'])
        <div class="min-w-0 flex-1">
            @if($participation->user)
            <a href="{{ route('profiles.show', $participation->user_id) }}" class="flex min-w-0 items-center gap-1.5 text-sm font-semibold text-slate-900 hover:text-primary-600 hover:underline">
                <span class="truncate">{{ $participation->user->display_name }}</span>
                @include('components.verified-badge', ['user' => $participation->user])
            </a>
            @else
            <p class="text-sm text-slate-500">Compte supprimé</p>
            @endif
        </div>
        <time class="shrink-0 text-xs text-slate-500" datetime="{{ $participation->updated_at?->toIso8601String() }}">{{ $participation->updated_at?->diffForHumans() }}</time>
    </li>
    @endforeach
</ul>
{{ $items->links() }}
@endif
@endsection

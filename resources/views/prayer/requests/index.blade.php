@extends('layouts.app')
@section('title', 'Requêtes de prière')
@php
    $header      = 'Requêtes de prière';
    $subheader   = 'Confiez vos besoins à la communauté et priez les uns pour les autres (Jacques 5:16).';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Requêtes de prière'],
    ];
    $tabs = ['all' => 'Toutes', 'mine' => 'Mes requêtes'];
    $tabParam = $tab === 'mine' ? ['onglet' => 'miennes'] : [];
@endphp

@section('headerActions')
    @auth
    <a href="{{ route('prayer.requests.create') }}" class="btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>Confier une requête</a>
    @endauth
    <a href="{{ route('prayer.sessions.index') }}" class="btn-secondary"><i class="fa-solid fa-people-group" aria-hidden="true"></i>Sessions de prière</a>
@endsection

@section('content')
<aside class="card-insight mb-5 flex gap-4 p-5" aria-label="Verset">
    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-sun-500 shadow-soft" aria-hidden="true"><i class="fa-solid fa-book-bible"></i></span>
    <div class="min-w-0">
        <blockquote class="text-[15px] leading-relaxed break-words text-slate-900 italic">« Priez les uns pour les autres, afin que vous soyez guéris. La prière agissante du juste a une grande efficacité. »</blockquote>
        <p class="mt-1 text-sm font-semibold text-primary-700">Jacques 5:16</p>
    </div>
</aside>

@auth
<nav aria-label="Requêtes" class="mb-4 border-b border-slate-200">
    <ul class="-mb-px flex gap-5 overflow-x-auto">
        @foreach($tabs as $key => $label)
        @php $active = $tab === $key; @endphp
        <li>
            <a href="{{ route('prayer.requests.index', array_filter(['onglet' => $key === 'mine' ? 'miennes' : null, 'statut' => $status ? 'exaucees' : null])) }}"
               class="{{ $active ? 'tab-active' : 'tab' }}" @if($active) aria-current="page" @endif>{{ $label }}</a>
        </li>
        @endforeach
    </ul>
</nav>
@endauth

<div class="mb-5 flex gap-2" role="group" aria-label="État des requêtes">
    <a href="{{ route('prayer.requests.index', $tabParam) }}" class="{{ !$status ? 'chip-active' : 'chip' }}" @if(!$status) aria-current="true" @endif>Toutes</a>
    <a href="{{ route('prayer.requests.index', array_merge($tabParam, ['statut' => 'exaucees'])) }}" class="{{ $status ? 'chip-active' : 'chip' }}" @if($status) aria-current="true" @endif>
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>Exaucées
    </a>
</div>

@if($items->isEmpty())
    @include('components.empty-state', [
        'title'         => $tab === 'mine' ? "Vous n'avez pas encore confié de requête" : ($status ? 'Aucune requête exaucée pour le moment' : 'Aucune requête de prière pour le moment'),
        'text'          => 'Partagez un besoin de prière : la communauté priera avec vous. Vous pouvez rester anonyme.',
        'actionUrl'     => Auth::check() ? route('prayer.requests.create') : route('login'),
        'actionLabel'   => Auth::check() ? 'Confier une requête' : 'Se connecter',
        'actionPrimary' => true,
    ])
@else
    <div class="mb-8 grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-5">
        @foreach($items as $prayerRequest)
            @include('prayer.partials.feed-card', ['request' => $prayerRequest])
        @endforeach
    </div>
    {{ $items->links() }}
@endif
@endsection

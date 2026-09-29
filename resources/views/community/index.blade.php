@extends('layouts.app')
@section('title', 'Communauté')
@php
    $header      = 'Communauté';
    $subheader   = 'Églises, ministères, associations et personnes qui témoignent : suivez-les pour être prévenu de leurs témoignages et de leurs directs.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Communauté'],
    ];
    $empty = $tab === 'people'
        ? ['Aucune personne à proposer pour le moment.', 'Les personnes qui publient des témoignages apparaîtront ici.']
        : ['Aucune organisation pour le moment.', 'Les églises, ministères et associations inscrits apparaîtront ici.'];
@endphp

@section('content')
<form method="GET" action="{{ route('community.index') }}" role="search" class="mb-5 flex max-w-2xl gap-2" data-loading-inline data-loading-label="Recherche…">
    @if($tab !== 'organizations')<input type="hidden" name="tab" value="{{ $tab }}">@endif
    <label for="community-q" class="sr-only">Rechercher un compte</label>
    <div class="relative min-w-0 flex-1">
        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
        <input id="community-q" type="search" name="q" value="{{ $q }}" maxlength="100" autocomplete="off"
               class="form-input pl-9" placeholder="{{ $tab === 'people' ? 'Nom, pays…' : 'Nom, ville, pays…' }}">
    </div>
    <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass sm:hidden" aria-hidden="true"></i><span class="max-sm:sr-only">Rechercher</span></button>
</form>

<nav aria-label="Type de compte" class="mb-6 border-b border-slate-200">
    <ul class="-mb-px flex gap-5 overflow-x-auto">
        @foreach(\App\Services\CommunityDirectory::TABS as $key => $label)
        <li>
            <a href="{{ route('community.index', array_filter(['tab' => $key === 'organizations' ? null : $key, 'q' => $q ?: null])) }}"
               class="{{ $tab === $key ? 'tab-active' : 'tab' }}" @if($tab === $key) aria-current="page" @endif>
                <i class="fa-solid {{ $key === 'people' ? 'fa-user' : 'fa-church' }} text-slate-400" aria-hidden="true"></i>{{ $label }}
            </a>
        </li>
        @endforeach
    </ul>
</nav>

@if($q !== '')
<p class="mb-4 text-sm text-slate-600" role="status">
    {{ $accounts->total() }} résultat{{ $accounts->total() > 1 ? 's' : '' }} pour <span class="font-semibold text-slate-900">« {{ $q }} »</span>
</p>
@endif

@if($accounts->isEmpty())
    @include('components.empty-state', [
        'title'       => $q !== '' ? 'Aucun compte ne correspond à votre recherche.' : $empty[0],
        'text'        => $q !== '' ? "Vérifiez l'orthographe ou essayez un autre nom." : $empty[1],
        'actionUrl'   => $q !== '' ? route('community.index', array_filter(['tab' => $tab !== 'organizations' ? $tab : null])) : null,
        'actionLabel' => 'Effacer la recherche',
    ])
@else
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($accounts as $account)
            @include('community.partials.card', ['account' => $account])
        @endforeach
    </div>
    {{ $accounts->links() }}
@endif
@endsection

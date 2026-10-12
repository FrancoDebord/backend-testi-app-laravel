@extends('layouts.app')
@section('title', 'Communauté')
@php
    $header      = 'Communauté';
    $subheader   = 'Églises, ministères, associations et personnes : suivez-les pour être prévenu de leurs témoignages et de leurs directs.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Communauté'],
    ];
    $empty = match ($tab) {
        'people'        => ['Aucune personne pour le moment.', 'Les personnes inscrites apparaîtront ici.'],
        'organizations' => ['Aucune organisation pour le moment.', 'Les églises, ministères et associations inscrits apparaîtront ici.'],
        default         => ['Aucun compte pour le moment.', 'Les personnes et organisations inscrites apparaîtront ici.'],
    };
@endphp

@section('content')
<form method="GET" action="{{ route('community.index') }}" role="search" class="mb-5 flex max-w-2xl gap-2" data-loading-inline data-loading-label="Recherche…">
    @if($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
    <label for="community-q" class="sr-only">Rechercher un compte</label>
    <div class="relative min-w-0 flex-1">
        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
        <input id="community-q" type="search" name="q" value="{{ $q }}" data-search-autocomplete="users" data-search-recent-key="community" maxlength="100" autocomplete="off"
               class="form-input pl-9" placeholder="{{ $tab === 'people' ? 'Nom, pays…' : 'Nom, ville, pays…' }}">
    </div>
    <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass sm:hidden" aria-hidden="true"></i><span class="max-sm:sr-only">Rechercher</span></button>
</form>

{{-- Suggestions personnalisées du type de l'onglet (première page, sans recherche) ─ docs/fonctionnalites/abonnements.md --}}
@if($q === '' && (int) request('page', 1) <= 1)
    @include('community.partials.suggested', ['suggestedTitle' => 'Suggestions pour vous', 'suggestedMore' => false, 'suggestedType' => $tab])
@endif

<nav aria-label="Type de compte" class="mb-6 border-b border-slate-200">
    <ul class="-mb-px flex gap-5 overflow-x-auto">
        @foreach(\App\Services\CommunityDirectory::TABS as $key => $label)
        <li>
            <a href="{{ route('community.index', array_filter(['tab' => $key === 'all' ? null : $key, 'q' => $q ?: null])) }}"
               class="{{ $tab === $key ? 'tab-active' : 'tab' }}" @if($tab === $key) aria-current="page" @endif>
                <i class="fa-solid {{ match ($key) { 'people' => 'fa-user', 'organizations' => 'fa-church', default => 'fa-users' } }} text-slate-400" aria-hidden="true"></i>{{ $label }}
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
        'actionUrl'   => $q !== '' ? route('community.index', array_filter(['tab' => $tab !== 'all' ? $tab : null])) : null,
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

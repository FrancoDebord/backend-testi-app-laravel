@extends('layouts.app')
@section('title', 'Mes abonnés')
@php
    // « Mes abonnés » : comptes qui me suivent (docs/fonctionnalites/abonnements.md).
    // Bouton « Suivre en retour » pour ceux que je ne suis pas encore.
    $header      = 'Mes abonnés';
    $subheader   = 'Les comptes qui vous suivent : ils sont prévenus de vos témoignages et de vos directs.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Mon profil', 'url' => route('profiles.show', auth()->id())],
        ['label' => 'Mes abonnés'],
    ];
@endphp

@section('content')
@include('community.partials.follow-tabs', ['active' => 'followers'])

@if($q !== '' || $accounts->total() > 0)
<form method="GET" action="{{ route('profile.followers') }}" role="search" class="mb-5 flex max-w-2xl gap-2" data-loading-inline data-loading-label="Recherche…">
    <label for="followers-q" class="sr-only">Rechercher dans mes abonnés</label>
    <div class="relative min-w-0 flex-1">
        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
        <input id="followers-q" type="search" name="q" value="{{ $q }}" maxlength="100" autocomplete="off"
               class="form-input pl-9" placeholder="Nom, ville, pays…">
    </div>
    <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass sm:hidden" aria-hidden="true"></i><span class="max-sm:sr-only">Rechercher</span></button>
</form>

<p class="mb-4 text-sm text-slate-600" role="status">
    @if($q !== '')
        {{ $accounts->total() }} résultat{{ $accounts->total() > 1 ? 's' : '' }} pour <span class="font-semibold text-slate-900">« {{ $q }} »</span>
    @else
        <span class="font-semibold text-slate-900">{{ number_format($accounts->total(), 0, ',', ' ') }}</span> compte{{ $accounts->total() > 1 ? 's vous suivent' : ' vous suit' }}.
    @endif
</p>
@endif

@if($accounts->isEmpty())
    @include('components.empty-state', [
        'title'       => $q !== '' ? 'Aucun abonné ne correspond à votre recherche.' : "Personne ne vous suit encore.",
        'text'        => $q !== '' ? "Vérifiez l'orthographe ou essayez un autre nom." : 'Publiez des témoignages et participez à la Communauté : ceux qui vous suivront apparaîtront ici.',
        'actionUrl'   => $q !== '' ? route('profile.followers') : route('community.index'),
        'actionLabel' => $q !== '' ? 'Effacer la recherche' : 'Découvrir la Communauté',
    ])
@else
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($accounts as $account)
            @include('community.partials.card', ['account' => $account, 'followIdleLabel' => 'Suivre en retour'])
        @endforeach
    </div>
    {{ $accounts->links() }}
@endif
@endsection

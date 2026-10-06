@extends('layouts.app')
@section('title', 'Mes abonnements')
@php
    // « Mes abonnements » : comptes suivis (docs/fonctionnalites/abonnements.md).
    // Se désabonner laisse la carte en place (bouton « Suivre ») pour pouvoir revenir sur son choix.
    $header      = 'Mes abonnements';
    $subheader   = 'Les comptes que vous suivez : vous êtes prévenu de leurs témoignages et de leurs directs.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Mon profil', 'url' => route('profiles.show', auth()->id())],
        ['label' => 'Mes abonnements'],
    ];
@endphp

@section('content')
@include('community.partials.follow-tabs', ['active' => 'following'])

@if($q !== '' || $accounts->total() > 0)
<form method="GET" action="{{ route('profile.following') }}" role="search" class="mb-5 flex max-w-2xl gap-2" data-loading-inline data-loading-label="Recherche…">
    <label for="following-q" class="sr-only">Rechercher dans mes abonnements</label>
    <div class="relative min-w-0 flex-1">
        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
        <input id="following-q" type="search" name="q" value="{{ $q }}" maxlength="100" autocomplete="off"
               class="form-input pl-9" placeholder="Nom, ville, pays…">
    </div>
    <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass sm:hidden" aria-hidden="true"></i><span class="max-sm:sr-only">Rechercher</span></button>
</form>

<p class="mb-4 text-sm text-slate-600" role="status">
    @if($q !== '')
        {{ $accounts->total() }} résultat{{ $accounts->total() > 1 ? 's' : '' }} pour <span class="font-semibold text-slate-900">« {{ $q }} »</span>
    @else
        Vous suivez <span class="font-semibold text-slate-900">{{ number_format($accounts->total(), 0, ',', ' ') }}</span> compte{{ $accounts->total() > 1 ? 's' : '' }}.
    @endif
</p>
@endif

@if($accounts->isEmpty())
    @include('components.empty-state', [
        'title'       => $q !== '' ? 'Aucun abonnement ne correspond à votre recherche.' : 'Vous ne suivez encore personne.',
        'text'        => $q !== '' ? "Vérifiez l'orthographe ou essayez un autre nom." : 'Découvrez des églises, des ministères et des personnes qui témoignent dans la Communauté.',
        'actionUrl'   => $q !== '' ? route('profile.following') : route('community.index'),
        'actionLabel' => $q !== '' ? 'Effacer la recherche' : 'Découvrir la Communauté',
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

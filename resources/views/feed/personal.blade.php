@extends('layouts.app')
@section('title', 'Mon fil')
@php
    // Mon fil : comptes suivis + une suggestion tous les trois (App\Services\Recommendations::personalFeed).
    $header      = 'Mon fil';
    $subheader   = 'Les témoignages des comptes que vous suivez, avec quelques suggestions.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Mon fil'],
    ];
@endphp

@section('content')
{{-- Directs en cours (masqué s'il n'y en a pas) --}}
@include('lives.partials.now', ['lives' => $lives, 'class' => 'mb-6'])

@if($items->isEmpty())
    @include('components.empty-state', [
        'title'       => 'Votre fil est encore vide',
        'text'        => 'Suivez des personnes et des organisations : leurs témoignages apparaîtront ici dès leur publication.',
        'actionUrl'   => route('community.index'),
        'actionLabel' => 'Découvrir la communauté',
    ])
    {{-- Fil vide : les événements à venir des comptes suivis restent visibles. --}}
    @if(collect($inserts)->isNotEmpty())
    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($inserts as $insert)
            @include('feed.partials.insert', ['insert' => $insert])
        @endforeach
    </div>
    @endif
@else
    @unless($followsSomeone)
    <div class="alert-info mb-4" role="status">
        <i class="fa-solid fa-circle-info mt-0.5" aria-hidden="true"></i>
        <p>Vous ne suivez encore personne : voici des suggestions. <a href="{{ route('community.index') }}" class="font-semibold underline">Trouvez des comptes à suivre</a> dans la Communauté.</p>
    </div>
    @endunless

    <div class="mb-3 flex justify-end">@include('components.layout-toggle')</div>
    @include('components.testimony-list', [
        'items'     => $items,
        'routeName' => 'testimonies.show',
        'listId'    => 'personal-feed',
        'encourage' => true,
        'reasons'   => $reasons,
        'inserts'   => $inserts,
    ])

    @if($items->hasMorePages())
    <div class="mt-8 flex justify-center">
        {{-- Sans JavaScript : lien vers la page suivante. Avec : les cartes suivantes sont ajoutées à la liste. --}}
        <a href="{{ $items->nextPageUrl() }}" class="btn-secondary" data-load-more="personal-feed">Afficher plus</a>
    </div>
    @endif
@endif
@endsection

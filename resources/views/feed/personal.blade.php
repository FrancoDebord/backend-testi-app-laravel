@extends('layouts.app')
@section('title', __('layout.nav.my_feed'))
@php
    // Mon fil : tous les témoignages, les comptes suivis (et leurs partages) d'abord (App\Services\Recommendations::personalFeed).
    $header      = __('layout.nav.my_feed');
    $subheader   = __('discover.feed.subheader');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.my_feed')],
    ];
@endphp

@section('content')
{{-- Directs en cours (masqué s'il n'y en a pas) --}}
@include('lives.partials.now', ['lives' => $lives, 'class' => 'mb-6'])
{{-- Zone utilisée pour le classement et invitation à activer la position (docs/fonctionnalites/localisation.md) --}}
@include('feed.partials.place', ['place' => $place ?? null])

@if($items->isEmpty())
    @include('components.empty-state', [
        'title'       => __('discover.feed.empty_title'),
        'text'        => __('discover.feed.empty_text'),
        'actionUrl'   => route('community.index'),
        'actionLabel' => __('discover.feed.empty_action'),
    ])
    {{-- Fil vide : événements, sessions et requêtes de prière, paroles prophétiques restent visibles. --}}
    @if(collect($inserts)->isNotEmpty())
    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($inserts as $insert)
            @include('feed.partials.insert', ['insert' => $insert])
        @endforeach
    </div>
    @endif
@else
    @unless($followsSomeone)
    {{-- On ne suit encore personne : « Comptes à suivre » en tête. --}}
    @include('community.partials.suggested')
    <div class="alert-info mb-4" role="status">
        <i class="fa-solid fa-circle-info mt-0.5" aria-hidden="true"></i>
        <p>{{ __('discover.feed.no_follows') }} <a href="{{ route('community.index') }}" class="font-semibold underline">{{ __('discover.feed.no_follows_link') }}</a> {{ __('discover.feed.no_follows_after') }}</p>
    </div>
    @endunless

    <div class="mb-3 flex justify-end">@include('components.layout-toggle')</div>
    @include('components.testimony-list', [
        'items'     => $items,
        'routeName' => 'testimonies.show',
        'listId'    => 'personal-feed',
        'encourage' => true,
        'reasons'   => $reasons,
        'reposts'   => $reposts,
        // « Comptes à suivre » après les 6 premiers témoignages (en tête si l'on ne suit personne).
        'suggestedAt' => $followsSomeone ? 6 : null,
        'inserts'   => $inserts,
    ])

    @if($items->hasMorePages())
    <div class="mt-8 flex justify-center">
        {{-- Sans JavaScript : lien vers la page suivante. Avec : les cartes suivantes sont ajoutées à la liste. --}}
        <a href="{{ $items->nextPageUrl() }}" class="btn-secondary" data-load-more="personal-feed">{{ __('discover.feed.show_more') }}</a>
    </div>
    @endif
@endif
@endsection

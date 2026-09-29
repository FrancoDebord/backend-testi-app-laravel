@extends('layouts.app')
@section('title', 'Témoignages sauvegardés')
@php
    $header      = 'Témoignages sauvegardés';
    $subheader   = 'Les témoignages que vous avez mis de côté pour les relire.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Sauvegardes'],
    ];
@endphp

@section('content')
@if($testimonies->isEmpty())
    @include('components.empty-state', [
        'title' => 'Aucune sauvegarde',
        'text' => 'Utilisez le bouton « Sauvegarder » sur un témoignage pour le retrouver ici.',
        'actionUrl' => route('explore'),
        'actionLabel' => 'Explorer les témoignages',
    ])
@else
    <div class="mb-3 flex justify-end">@include('components.layout-toggle')</div>
    <div class="mb-8">
        @include('components.testimony-list', ['items' => $testimonies, 'routeName' => 'testimonies.show'])
    </div>
    {{ $testimonies->links() }}
@endif
@endsection

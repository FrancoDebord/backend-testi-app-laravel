@extends('layouts.app')
@section('title', __('profile.saved.title'))
@php
    $header      = __('profile.saved.title');
    $subheader   = __('profile.saved.subheader');
    $breadcrumbs = [
        ['label' => __('profile.breadcrumb.home'), 'url' => route('home')],
        ['label' => __('profile.show.shortcuts.saved')],
    ];
@endphp

@section('content')
@if($testimonies->isEmpty())
    @include('components.empty-state', [
        'title' => __('profile.saved.empty_title'),
        'text' => __('profile.saved.empty_text'),
        'actionUrl' => route('explore'),
        'actionLabel' => __('profile.saved.empty_action'),
    ])
@else
    <div class="mb-3 flex justify-end">@include('components.layout-toggle')</div>
    <div class="mb-8">
        @include('components.testimony-list', ['items' => $testimonies, 'routeName' => 'testimonies.show'])
    </div>
    {{ $testimonies->links() }}
@endif
@endsection

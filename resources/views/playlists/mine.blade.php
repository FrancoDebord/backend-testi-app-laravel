@extends('layouts.app')
@section('title', __('playlists.mine.title'))
{{-- Mes listes de témoignages (docs/fonctionnalites/listes-de-temoignages.md) --}}
@php
    $header      = __('playlists.mine.title');
    $subheader   = __('playlists.mine.subheader');
    $breadcrumbs = [
        ['label' => __('testimonies.common.home'), 'url' => route('home')],
        ['label' => __('testimonies.mine.title'), 'url' => route('testimonies.mine')],
        ['label' => __('playlists.nav')],
    ];
@endphp

@section('headerActions')
    <button type="button" class="btn-primary" data-modal-open="playlist-create-modal"><i class="fa-solid fa-plus" aria-hidden="true"></i>{{ __('playlists.mine.create') }}</button>
@endsection

@section('content')
@if($playlists->isEmpty())
    @include('components.empty-state', [
        'title' => __('playlists.mine.empty_title'),
        'text'  => __('playlists.mine.empty_text'),
    ])
@else
    <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($playlists as $playlist)
        <li>
            <a href="{{ route('playlists.show', $playlist->id) }}" class="card flex h-full items-start gap-4 p-4 hover:border-primary-200">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600" aria-hidden="true">
                    <i class="fa-solid fa-layer-group"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold break-words text-slate-900">{{ $playlist->title }}</span>
                    <span class="mt-0.5 block text-sm text-slate-500">{{ trans_choice('playlists.mine.count', $playlist->testimonies_count, ['count' => $playlist->testimonies_count]) }}</span>
                    <span class="mt-0.5 block text-xs text-slate-400">{{ __('playlists.mine.updated', ['date' => $playlist->updated_at->diffForHumans()]) }}</span>
                </span>
            </a>
        </li>
        @endforeach
    </ul>
@endif

@include('playlists.partials.form-modal', ['modalId' => 'playlist-create-modal', 'playlist' => null])
@endsection

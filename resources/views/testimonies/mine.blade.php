@extends('layouts.app')
@section('title', __('testimonies.mine.title'))
@php
    $header      = __('testimonies.mine.title');
    $subheader   = __('testimonies.mine.subheader');
    $breadcrumbs = [
        ['label' => __('testimonies.common.home'), 'url' => route('home')],
        ['label' => __('testimonies.mine.title')],
    ];
    $tabs = [
        'all'      => [__('testimonies.mine.tab_all'), null],
        'approved' => [__('testimonies.mine.tab_approved'), 'bg-emerald-500'],
        'pending'  => [__('testimonies.mine.tab_pending'), 'bg-amber-500'],
        'rejected' => [__('testimonies.mine.tab_rejected'), 'bg-red-500'],
        'draft'    => [__('testimonies.mine.tab_draft'), 'bg-slate-400'],
    ];
    $currentStatus = $status ?: 'all';
@endphp

@section('headerActions')
    <a href="{{ route('publish') }}" class="btn-cta"><i class="fa-solid fa-plus"></i>{{ __('testimonies.mine.new') }}</a>
@endsection

@section('content')
<nav class="mb-6 overflow-x-auto border-b border-slate-200" aria-label="{{ __('testimonies.mine.filter') }}">
    <ul class="flex gap-6">
        @foreach($tabs as $val => [$label, $dot])
        <li>
            <a href="{{ route('testimonies.mine', ['status' => $val]) }}"
               class="{{ $currentStatus === $val ? 'tab-active' : 'tab' }}"
               @if($currentStatus === $val) aria-current="page" @endif>
                @if($dot)<span class="h-2 w-2 rounded-full {{ $dot }}"></span>@endif
                {{ $label }}
            </a>
        </li>
        @endforeach
    </ul>
</nav>

@if($testimonies->isEmpty())
    @include('components.empty-state', [
        'title' => __('testimonies.mine.empty_title'),
        'text' => $currentStatus === 'all' ? __('testimonies.mine.empty_all') : __('testimonies.mine.empty_status'),
        'actionUrl' => route('publish'),
        'actionLabel' => __('testimonies.create.title'),
    ])
    @if($currentStatus === 'all')
    {{-- Encouragement (le bouton « Publier » est déjà au-dessus) --}}
    <x-encouragement class="mx-auto mt-6 max-w-2xl" kind="verse" :action="false" />
    <x-why-testify class="mx-auto mt-6 max-w-2xl" :open="0" :action="false" />
    @endif
@else
    <div class="mb-3 flex justify-end">@include('components.layout-toggle')</div>
    <div class="mb-8">
        @include('components.testimony-list', ['items' => $testimonies, 'routeName' => 'testimonies.show'])
    </div>
    {{ $testimonies->withQueryString()->links() }}
    {{-- Pourquoi témoigner ? (docs/fonctionnalites/pourquoi-temoigner.md) --}}
    <x-why-testify collapsible class="mt-8" />
@endif
@endsection

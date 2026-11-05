@extends('layouts.app')
@section('title', __('layout.nav.prophecies'))
@php
    $header      = __('layout.nav.prophecies');
    $subheader   = __('prophecies.index.subheader');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.journal'), 'url' => route('journal.index')],
        ['label' => __('layout.nav.prophecies')],
    ];
    $tabs = [
        \App\Models\Prophecy::WAITING   => __('prophecies.status.waiting'),
        \App\Models\Prophecy::FULFILLED => __('prophecies.index.tab_fulfilled'),
    ];
    $verse = \App\Support\Encouragements::prophecyVerses()[0] ?? null;
@endphp

@section('headerActions')
    <a href="{{ route('prophecies.create') }}" class="btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>{{ __('prophecies.index.new') }}</a>
@endsection

@section('content')
@include('journal.partials.tabs', ['current' => 'prophecies'])

@if($status === \App\Models\Prophecy::WAITING && $verse)
<aside class="card-insight mb-5 flex gap-4 p-5" aria-label="{{ __('prophecies.verse') }}">
    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-sun-500 shadow-soft" aria-hidden="true"><i class="fa-solid fa-book-bible"></i></span>
    <div class="min-w-0">
        <blockquote class="text-[15px] leading-relaxed break-words text-slate-900 italic">{{ __('prophecies.quoted', ['text' => $verse['text']]) }}</blockquote>
        <p class="mt-1 text-sm font-semibold text-primary-700">{{ $verse['ref'] }}</p>
    </div>
</aside>
@endif

<div class="mb-5 flex gap-2" role="group" aria-label="{{ __('prophecies.index.status_group') }}">
    @foreach($tabs as $value => $label)
    <a href="{{ route('prophecies.index', $value === \App\Models\Prophecy::WAITING ? [] : ['statut' => $value]) }}"
       class="{{ $status === $value ? 'chip-active' : 'chip' }}" @if($status === $value) aria-current="true" @endif>
        {{ $label }} <span class="tabular-nums">{{ $counts[$value] }}</span>
    </a>
    @endforeach
</div>

@if($items->isEmpty())
    @include('components.empty-state', [
        'title'         => $status === \App\Models\Prophecy::WAITING ? __('prophecies.index.empty_waiting') : __('prophecies.index.empty_fulfilled'),
        'text'          => $status === \App\Models\Prophecy::WAITING
            ? __('prophecies.index.empty_waiting_text')
            : __('prophecies.index.empty_fulfilled_text'),
        'actionUrl'     => $status === \App\Models\Prophecy::WAITING ? route('prophecies.create') : null,
        'actionLabel'   => __('prophecies.index.keep'),
        'actionPrimary' => true,
    ])
@else
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 xl:gap-5">
        @foreach($items as $prophecy)
            @include('journal.prophecies.card', ['prophecy' => $prophecy])
        @endforeach
    </div>
    {{ $items->links() }}
@endif
@endsection

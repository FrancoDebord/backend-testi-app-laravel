@extends('layouts.app')
@section('title', 'Paroles prophétiques')
@php
    $header      = 'Paroles prophétiques';
    $subheader   = 'Gardez les paroles reçues, priez dessus, puis témoignez de leur accomplissement.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Carnet privé', 'url' => route('journal.index')],
        ['label' => 'Paroles prophétiques'],
    ];
    $tabs = [
        \App\Models\Prophecy::WAITING   => 'En attente',
        \App\Models\Prophecy::FULFILLED => 'Accomplies',
    ];
    $verse = config('encouragements.prophecy_verses.0');
@endphp

@section('headerActions')
    <a href="{{ route('prophecies.create') }}" class="btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>Nouvelle parole</a>
@endsection

@section('content')
@include('journal.partials.tabs', ['current' => 'prophecies'])

@if($status === \App\Models\Prophecy::WAITING && $verse)
<aside class="card-insight mb-5 flex gap-4 p-5" aria-label="Verset">
    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-sun-500 shadow-soft" aria-hidden="true"><i class="fa-solid fa-book-bible"></i></span>
    <div class="min-w-0">
        <blockquote class="text-[15px] leading-relaxed break-words text-slate-900 italic">« {{ $verse['text'] }} »</blockquote>
        <p class="mt-1 text-sm font-semibold text-primary-700">{{ $verse['ref'] }}</p>
    </div>
</aside>
@endif

<div class="mb-5 flex gap-2" role="group" aria-label="État des paroles">
    @foreach($tabs as $value => $label)
    <a href="{{ route('prophecies.index', $value === \App\Models\Prophecy::WAITING ? [] : ['statut' => $value]) }}"
       class="{{ $status === $value ? 'chip-active' : 'chip' }}" @if($status === $value) aria-current="true" @endif>
        {{ $label }} <span class="tabular-nums">{{ $counts[$value] }}</span>
    </a>
    @endforeach
</div>

@if($items->isEmpty())
    @include('components.empty-state', [
        'title'         => $status === \App\Models\Prophecy::WAITING ? 'Aucune parole en attente' : 'Aucune parole accomplie pour le moment',
        'text'          => $status === \App\Models\Prophecy::WAITING
            ? "Notez la parole reçue lors d'un culte, d'une prière ou d'un rêve : la date, qui l'a donnée, une échéance si elle a été annoncée."
            : "Quand une parole s'accomplit, marquez-la accomplie ou témoignez : elle apparaîtra ici.",
        'actionUrl'     => $status === \App\Models\Prophecy::WAITING ? route('prophecies.create') : null,
        'actionLabel'   => 'Garder une parole',
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

@extends('layouts.app')
@section('title', 'Sessions de prière')
@php
    $header      = 'Sessions de prière';
    $subheader   = 'Priez ensemble, en direct : inscrivez-vous, puis rejoignez la salle à l\'heure prévue.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Sessions de prière'],
    ];
    $tabs = \App\Http\Controllers\Web\PrayerSessionController::TABS;
    if (!Auth::check()) unset($tabs['joined'], $tabs['mine']);
@endphp

@section('headerActions')
    @auth
    <a href="{{ route('prayer.sessions.create') }}" class="btn-primary"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>Programmer une session</a>
    @endauth
    <a href="{{ route('prayer.requests.index') }}" class="btn-secondary"><i class="fa-solid fa-hands-praying" aria-hidden="true"></i>Requêtes de prière</a>
@endsection

@section('content')
<nav aria-label="Sessions" class="mb-5 border-b border-slate-200">
    <ul class="-mb-px flex gap-5 overflow-x-auto">
        @foreach($tabs as $key => $label)
        @php $active = $tab === $key; @endphp
        <li>
            <a href="{{ route('prayer.sessions.index', $key === 'upcoming' ? [] : ['onglet' => $key]) }}"
               class="{{ $active ? 'tab-active' : 'tab' }}" @if($active) aria-current="page" @endif>{{ $label }}</a>
        </li>
        @endforeach
    </ul>
</nav>

@if($items->isEmpty())
    @include('components.empty-state', [
        'title'         => match ($tab) {
            'joined' => "Vous n'êtes inscrit à aucune session",
            'mine'   => "Vous n'animez aucune session",
            'past'   => 'Aucune session passée',
            default  => 'Aucune session de prière programmée',
        },
        'text'          => 'Programmez un temps de prière : les inscrits sont prévenus, et vous ouvrez la salle à l\'heure prévue.',
        'actionUrl'     => Auth::check() ? route('prayer.sessions.create') : null,
        'actionLabel'   => 'Programmer une session',
        'actionPrimary' => true,
    ])
@else
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 xl:gap-5">
        @foreach($items as $session)
            @include('prayer.partials.session-card', ['session' => $session])
        @endforeach
    </div>
    {{ $items->links() }}
@endif
@endsection

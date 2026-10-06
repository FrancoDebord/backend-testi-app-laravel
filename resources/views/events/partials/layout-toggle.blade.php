{{--
    Choix de l'affichage de la liste des événements : cartes ou liste compacte. @include('events.partials.layout-toggle')
    Formulaire classique (sans JavaScript) ; choix mémorisé dans un cookie (App\Support\EventLayout).
--}}
@php($currentEventLayout = \App\Support\EventLayout::current())
<form method="POST" action="{{ route('events.layout') }}" class="flex shrink-0 items-center gap-1.5" data-no-loading
      role="group" aria-label="Affichage de la liste">
    @csrf
    @foreach([\App\Support\EventLayout::CARDS => 'fa-table-cells-large', \App\Support\EventLayout::COMPACT => 'fa-list'] as $layoutKey => $layoutIcon)
    <button type="submit" name="layout" value="{{ $layoutKey }}" class="{{ $currentEventLayout === $layoutKey ? 'chip-active' : 'chip' }}"
            aria-pressed="{{ $currentEventLayout === $layoutKey ? 'true' : 'false' }}" aria-label="{{ \App\Support\EventLayout::LABELS[$layoutKey] }}" title="{{ \App\Support\EventLayout::LABELS[$layoutKey] }}">
        <i class="fa-solid {{ $layoutIcon }}" aria-hidden="true"></i><span class="hidden sm:inline" aria-hidden="true">{{ \App\Support\EventLayout::LABELS[$layoutKey] }}</span>
    </button>
    @endforeach
</form>

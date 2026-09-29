{{--
    Choix de l'affichage des listes : grandes cartes ou liste compacte. @include('components.layout-toggle')
    Formulaire classique (fonctionne sans JavaScript) ; le choix est mémorisé dans un cookie (App\Support\FeedLayout).
--}}
@php($currentLayout = \App\Support\FeedLayout::current())
<form method="POST" action="{{ route('preferences.layout') }}" class="flex shrink-0 items-center gap-1.5" data-no-loading
      role="group" aria-label="Affichage de la liste">
    @csrf
    @foreach([\App\Support\FeedLayout::CARDS => 'fa-table-cells-large', \App\Support\FeedLayout::COMPACT => 'fa-list'] as $layoutKey => $layoutIcon)
    <button type="submit" name="layout" value="{{ $layoutKey }}" class="{{ $currentLayout === $layoutKey ? 'chip-active' : 'chip' }}"
            aria-pressed="{{ $currentLayout === $layoutKey ? 'true' : 'false' }}" aria-label="{{ \App\Support\FeedLayout::LABELS[$layoutKey] }}" title="{{ \App\Support\FeedLayout::LABELS[$layoutKey] }}">
        {{-- Pas de sr-only / sm:not-sr-only : la classe .sr-only de Font Awesome (hors couches Tailwind) l'emporte. --}}
        <i class="fa-solid {{ $layoutIcon }}" aria-hidden="true"></i><span class="hidden sm:inline" aria-hidden="true">{{ \App\Support\FeedLayout::LABELS[$layoutKey] }}</span>
    </button>
    @endforeach
</form>

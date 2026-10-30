{{-- Nombre de prières de la personne et « Retirer ma dernière prière » (docs/fonctionnalites/prier-plusieurs-fois.md).
     @include('components.prayer-times', ['times' => 3, 'action' => route(...), 'fields' => ['type' => 'pray', 'last' => 1]])
     N'affiche rien si la personne ne prie pas. --}}
@if(($times ?? 0) > 0)
<form method="POST" action="{{ $action }}" class="flex flex-wrap items-center gap-x-2 text-sm text-slate-600" data-loading-label="…">
    @csrf
    @foreach($fields ?? [] as $fieldName => $fieldValue)
    <input type="hidden" name="{{ $fieldName }}" value="{{ $fieldValue }}">
    @endforeach
    <span>{{ trans_choice('reactions.prayer_times.prayed', $times, ['count' => $times]) }}</span>
    {{-- Icône seule (flèche « annuler ») ; le libellé reste lu par les lecteurs d'écran et affiché au survol. --}}
    <button type="submit" class="action-btn-delete" title="{{ __('reactions.prayer_times.remove_last') }}" aria-label="{{ __('reactions.prayer_times.remove_last') }}">
        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
    </button>
</form>
@endif

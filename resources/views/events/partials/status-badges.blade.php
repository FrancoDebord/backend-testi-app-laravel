{{--
    Pastilles d'état d'un événement (carte, ligne compacte, page) :
    @include('events.partials.status-badges', ['event' => $event, 'showStatus' => false, 'onAir' => false])
    En direct · Annulé · Reporté (date à confirmer) · Brouillon · En cours · Non confirmé · Masqué.
--}}
@if(!empty($onAir))<span class="badge-live">En direct</span>@endif
@if($event->isCancelled())
    <span class="badge-rejected">Annulé</span>
@elseif($event->isDateTbd())
    <span class="badge-orange">Reporté — date à confirmer</span>
@else
    @if($event->isPostponed())<span class="badge-orange">Reporté</span>@endif
    @if(!empty($showStatus) && $event->status === \App\Enums\EventStatus::Draft)
        <span class="{{ $event->status->badgeClass() }}">{{ $event->status->label() }}</span>
    @elseif($event->phase() === 'ongoing')
        <span class="badge-validated">En cours</span>
    @endif
@endif
@if($event->isUnconfirmed())<span class="badge-rejected" title="La majorité des participants disent qu'il n'a pas eu lieu">Non confirmé</span>@endif
@if(!empty($showStatus) && $event->isHidden())<span class="badge-draft">Masqué</span>@endif

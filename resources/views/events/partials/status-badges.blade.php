{{--
    Pastilles d'état d'un événement (carte, ligne compacte, page) :
    @include('events.partials.status-badges', ['event' => $event, 'showStatus' => false, 'onAir' => false])
    En direct · Annulé · Reporté (date à confirmer) · Brouillon · En cours · Non confirmé · Masqué.
--}}
@if(!empty($onAir))<span class="badge-live">{{ __('events.badges.on_air') }}</span>@endif
@if($event->isCancelled())
    <span class="badge-rejected">{{ __('events.badges.cancelled') }}</span>
@elseif($event->isDateTbd())
    <span class="badge-orange">{{ __('events.badges.postponed_tbd') }}</span>
@else
    @if($event->isPostponed())<span class="badge-orange">{{ __('events.badges.postponed') }}</span>@endif
    @if(!empty($showStatus) && $event->status === \App\Enums\EventStatus::Draft)
        <span class="{{ $event->status->badgeClass() }}">{{ $event->status->label() }}</span>
    @elseif($event->phase() === 'ongoing')
        <span class="badge-validated">{{ __('events.badges.ongoing') }}</span>
    @endif
@endif
@if($event->isUnconfirmed())<span class="badge-rejected" title="{{ __('events.badges.unconfirmed_title') }}">{{ __('events.badges.unconfirmed') }}</span>@endif
@if(!empty($showStatus) && $event->isHidden())<span class="badge-draft">{{ __('events.badges.hidden') }}</span>@endif

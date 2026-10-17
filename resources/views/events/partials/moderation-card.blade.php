{{--
    Bloc « Modération » (modérateurs et administrateurs) : signalements, état, fiabilité de l'organisateur,
    actions masquer / rétablir / classer. @include('events.partials.moderation-card', ['event' => $event, 'reliability' => [...]])
--}}
@php $pendingReports = $event->reports()->whereNull('reviewed_at')->count(); @endphp
<section class="card p-5" aria-labelledby="event-moderation-title">
    <h2 id="event-moderation-title" class="card-title">{{ __('events.moderation_card.title') }}</h2>
    <p class="mt-1 text-sm text-slate-600">
        {{ trans_choice('events.moderation_card.reports_count', $event->report_count, ['count' => \App\Support\Locales::number($event->report_count)]) }}@if($pendingReports) · <strong>{{ __('events.moderation_card.pending', ['count' => \App\Support\Locales::number($pendingReports)]) }}</strong>@endif
    </p>
    @include('events.partials.reliability', ['reliability' => $reliability])
    <div class="mt-3 flex flex-wrap gap-2">
        @if($event->isHidden())
        <form method="POST" action="{{ route('events.moderation.restore', $event->id) }}" data-loading-label="{{ __('events.moderation_card.restoring') }}">
            @csrf
            <button type="submit" class="btn-secondary btn-sm"><i class="fa-solid fa-eye" aria-hidden="true"></i>{{ __('events.moderation_card.restore') }}</button>
        </form>
        @else
        <form method="POST" action="{{ route('events.moderation.hide', $event->id) }}" class="flex w-full gap-2" data-loading-label="{{ __('events.moderation_card.hiding') }}">
            @csrf
            <label for="hide-reason-{{ $event->id }}" class="sr-only">{{ __('events.moderation_card.reason') }}</label>
            <input id="hide-reason-{{ $event->id }}" type="text" name="reason" maxlength="300" class="form-input min-w-0 py-1.5 text-sm" placeholder="{{ __('events.reason_optional') }}">
            <button type="submit" class="btn-secondary btn-sm shrink-0 text-error-700"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i>{{ __('events.moderation_card.hide') }}</button>
        </form>
        @endif
        @if($pendingReports || ($event->isUnconfirmed() && !$event->unconfirmed_reviewed_at))
        <form method="POST" action="{{ route('events.moderation.dismiss', $event->id) }}" data-loading-label="{{ __('events.saving') }}">
            @csrf
            <button type="submit" class="btn-ghost btn-sm"><i class="fa-solid fa-check" aria-hidden="true"></i>{{ __('events.moderation_card.dismiss') }}</button>
        </form>
        @endif
    </div>
</section>

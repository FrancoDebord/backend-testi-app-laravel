{{--
    Fiabilité d'un organisateur (modération) : @include('events.partials.reliability', ['reliability' => Event::reliabilityOf($id)])
    Confirmés : sondage avec au moins 3 « Oui » / « Non » et une majorité de « Oui ».
--}}
<dl class="mt-3 grid grid-cols-3 gap-2 rounded-lg bg-slate-50 p-3 text-center text-xs text-slate-600" aria-label="{{ __('events.reliability.label') }}">
    <div><dt>{{ __('events.reliability.confirmed') }}</dt><dd class="text-base font-semibold text-success-700">{{ $reliability['confirmed'] }}</dd></div>
    <div><dt>{{ __('events.reliability.unconfirmed') }}</dt><dd class="text-base font-semibold {{ $reliability['unconfirmed'] ? 'text-error-700' : 'text-slate-900' }}">{{ $reliability['unconfirmed'] }}</dd></div>
    <div><dt>{{ __('events.reliability.reported') }}</dt><dd class="text-base font-semibold {{ $reliability['reported'] ? 'text-amber-600' : 'text-slate-900' }}">{{ $reliability['reported'] }}</dd></div>
</dl>
<p class="mt-1 text-xs text-slate-500">{{ trans_choice('events.reliability.organizer_events', $reliability['total'], ['count' => \App\Support\Locales::number($reliability['total'])]) }}@if($reliability['reliable']) · <span class="text-success-700">{{ __('events.reliability.reliable') }}</span>@endif</p>

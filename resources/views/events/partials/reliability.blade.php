{{--
    Fiabilité d'un organisateur (modération) : @include('events.partials.reliability', ['reliability' => Event::reliabilityOf($id)])
    Confirmés : sondage avec au moins 3 « Oui » / « Non » et une majorité de « Oui ».
--}}
<dl class="mt-3 grid grid-cols-3 gap-2 rounded-lg bg-slate-50 p-3 text-center text-xs text-slate-600" aria-label="Fiabilité de l'organisateur">
    <div><dt>Confirmés</dt><dd class="text-base font-semibold text-success-700">{{ $reliability['confirmed'] }}</dd></div>
    <div><dt>Non confirmés</dt><dd class="text-base font-semibold {{ $reliability['unconfirmed'] ? 'text-error-700' : 'text-slate-900' }}">{{ $reliability['unconfirmed'] }}</dd></div>
    <div><dt>Signalés</dt><dd class="text-base font-semibold {{ $reliability['reported'] ? 'text-amber-600' : 'text-slate-900' }}">{{ $reliability['reported'] }}</dd></div>
</dl>
<p class="mt-1 text-xs text-slate-500">Organisateur : {{ $reliability['total'] }} événement{{ $reliability['total'] > 1 ? 's' : '' }}@if($reliability['reliable']) · <span class="text-success-700">fiable</span>@endif</p>

{{-- Localisation privée pour classer « Mon fil » par zone (docs/fonctionnalites/localisation.md).
     La position n'est demandée au navigateur (navigator.geolocation, HTTPS) qu'après un clic. --}}
@php
    $location = app(\App\Services\Locations::class)->payload(Auth::user());
    $sourceLabels = [
        'gps' => __('profile.location_card.sources.gps'), 'profile' => __('profile.location_card.sources.profile'),
        'phone' => __('profile.location_card.sources.phone'), 'language' => __('profile.location_card.sources.language'),
    ];
@endphp
<section class="card mx-auto mt-6 max-w-2xl scroll-mt-24" id="localisation" aria-labelledby="location-title">
    <h2 id="location-title" class="card-title border-b border-slate-100 px-5 py-4">{{ __('profile.location_card.title') }}</h2>
    <form method="POST" action="{{ route('profile.location.update') }}" class="space-y-4 px-5 py-4" data-location-form>
        @csrf @method('PUT')
        <input type="hidden" name="latitude" value="">
        <input type="hidden" name="longitude" value="">

        <div class="flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="text-sm font-medium text-slate-900">{{ __('profile.location_card.use_gps') }}</p>
                <p class="text-sm text-slate-500">{{ __('profile.location_card.use_gps_hint') }}</p>
            </div>
            @include('components.switch', ['name' => 'use_gps', 'checked' => $location['useGps'], 'label' => __('profile.location_card.use_gps'), 'sendFalse' => true])
        </div>

        <div>
            <label for="home_city" class="mb-1 block text-sm font-medium text-slate-900">{{ __('profile.location_card.city') }} <span class="font-normal text-slate-500">{{ __('profile.location_card.city_note') }}</span></label>
            <input id="home_city" name="home_city" type="text" maxlength="120" value="{{ $location['homeCity'] }}" class="form-input w-full" placeholder="{{ __('profile.location_card.city_placeholder') }}">
            <p class="mt-1 text-xs text-slate-500">{{ __('profile.location_card.city_hint') }}</p>
        </div>

        <p class="text-sm text-slate-600" role="status" data-location-status>
            @if($location['label'])
                <i class="fa-solid fa-location-dot mr-1 text-primary-600" aria-hidden="true"></i>
                {{ __('profile.location_card.zone') }} <strong>{{ $location['label'] }}</strong>@if($location['country'] && $location['country'] !== $location['label']), {{ $location['country'] }}@endif
                <span class="text-slate-500">({{ $sourceLabels[$location['source']] ?? __('profile.location_card.source_default') }})</span>
            @else
                {{ __('profile.location_card.no_zone') }}
            @endif
        </p>

        <div class="flex flex-wrap justify-end gap-2">
            <button type="button" class="btn-secondary" data-locate>
                <i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i> {{ __('profile.location_card.locate') }}
            </button>
            <button type="submit" class="btn-primary">{{ __('profile.save') }}</button>
        </div>
    </form>
    @if($location['useGps'] || $location['latitude'] !== null || $location['homeCity'])
    <form method="POST" action="{{ route('profile.location.destroy') }}" class="border-t border-slate-100 px-5 py-3 text-right">
        @csrf @method('DELETE')
        <button type="submit" class="text-sm font-medium text-red-600 hover:underline">{{ __('profile.location_card.erase') }}</button>
    </form>
    @endif
</section>

<script>
(() => {
    const form = document.querySelector('[data-location-form]');
    if (!form) return;
    const status = form.querySelector('[data-location-status]');
    const toggle = form.querySelector('input[type=checkbox][name=use_gps]');
    const messages = {{ \Illuminate\Support\Js::from([
        'unsupported' => __('profile.location_card.unsupported'),
        'locating'    => __('profile.location_card.locating'),
        'denied'      => __('profile.location_card.denied'),
    ]) }};
    const locateThenSubmit = () => {
        if (!('geolocation' in navigator)) {
            status.textContent = messages.unsupported;
            return;
        }
        status.textContent = messages.locating;
        navigator.geolocation.getCurrentPosition((pos) => {
            form.latitude.value = pos.coords.latitude.toFixed(4);
            form.longitude.value = pos.coords.longitude.toFixed(4);
            toggle.checked = true;
            form.submit();
        }, () => {
            status.textContent = messages.denied;
        }, { enableHighAccuracy: false, timeout: 15000, maximumAge: 600000 });
    };
    form.querySelector('[data-locate]').addEventListener('click', locateThenSubmit);
    // Activer le réglage demande la position tout de suite (consentement explicite : clic de la personne).
    toggle.addEventListener('change', () => { if (toggle.checked) locateThenSubmit(); });
})();
</script>

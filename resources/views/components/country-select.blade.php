{{--
    Choix du pays : @include('components.country-select', ['name' => 'country', 'id' => 'country', 'value' => old('country', …)])
    Liste déroulante classique (fonctionne sans JavaScript), transformée par app.js en liste avec recherche
    (data-country-select), avec le drapeau de chaque pays (public/flags). Une valeur enregistrée hors liste (ancien compte) reste proposée et sélectionnée.
--}}
@php
    $countryValue = trim((string) ($value ?? ''));
    $countryList  = \App\Support\Countries::all();
    $countryExtra = $countryValue !== '' && !in_array($countryValue, $countryList, true);
@endphp
<select id="{{ $id ?? 'country' }}" name="{{ $name ?? 'country' }}" class="form-input" data-country-select autocomplete="country-name"
        @error($name ?? 'country') aria-invalid="true" @enderror>
    <option value="">Choisir un pays</option>
    @if($countryExtra)
    <option value="{{ $countryValue }}" selected>{{ $countryValue }}</option>
    @endif
    @foreach($countryList as $country)
    <option value="{{ $country }}" data-code="{{ \App\Support\Countries::code($country) }}" data-flag="{{ \App\Support\Countries::flagUrl($country) }}" @selected($country === $countryValue)>{{ $country }}</option>
    @endforeach
</select>

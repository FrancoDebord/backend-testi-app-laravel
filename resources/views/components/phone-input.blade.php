{{--
    Téléphone de contact : indicatif (pays, avec drapeau) + numéro. Voir docs/fonctionnalites/telephone.md
    @include('components.phone-input', [
        'phoneCountry' => old('phone_country', …),   // code ISO (« bj ») ; à défaut, celui du pays choisi
        'countryName'  => old('country', …),         // pays du formulaire, pour l'indicatif par défaut
        'number'       => old('phone', …),           // numéro national
        'required'     => false,                     // true : obligatoire ; 'organization' : obligatoire pour une organisation
        'follow'       => 'country',                 // nom du champ pays : l'indicatif suit le pays choisi
    ])
--}}
@php
    $phoneRequired = $required ?? false;
    $phoneCode     = strtolower(trim((string) ($phoneCountry ?? ''))) ?: \App\Support\Countries::code($countryName ?? null);
@endphp
<div>
    <label for="phone" class="form-label">
        Téléphone @if($phoneRequired === true)*@endif
        @if($phoneRequired === 'organization')<span class="font-normal text-slate-500">(obligatoire pour une organisation)</span>@endif
    </label>
    <div class="flex gap-2">
        <div class="w-36 shrink-0 sm:w-40">
            <select id="phone_country" name="phone_country" class="form-input" data-country-select data-placeholder="Indicatif"
                    data-empty-text="Aucun pays ni indicatif ne correspond." @if(!empty($follow)) data-follow-country="{{ $follow }}" @endif
                    aria-label="Indicatif du pays" autocomplete="tel-country-code" @error('phone_country') aria-invalid="true" @enderror>
                <option value="">Indicatif</option>
                @foreach(\App\Support\Countries::all() as $name)
                @php($code = \App\Support\Countries::code($name))
                <option value="{{ $code }}" data-flag="{{ \App\Support\Countries::flagUrl($name) }}" data-label="+{{ \App\Support\Countries::dialCode($code) }}"
                        @selected($code === $phoneCode)>{{ $name }} (+{{ \App\Support\Countries::dialCode($code) }})</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-0 flex-1">
            <input id="phone" type="tel" name="phone" value="{{ $number ?? '' }}" inputmode="tel" autocomplete="tel-national" maxlength="30"
                   class="form-input" placeholder="Numéro" @if($phoneRequired === true) required @endif
                   @error('phone') aria-invalid="true" @enderror aria-describedby="phone-hint">
        </div>
    </div>
    @error('phone_country')<p class="form-error">{{ $message }}</p>@enderror
    @error('phone')<p class="form-error">{{ $message }}</p>@enderror
    <p id="phone-hint" class="form-hint">Sert uniquement à vérifier votre compte : il n'est jamais affiché publiquement.</p>
</div>

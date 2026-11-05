{{--
    Téléphone de contact : indicatif (pays, avec drapeau) + numéro. Voir docs/fonctionnalites/telephone.md
    @include('components.phone-input', [
        'phoneCountry' => old('phone_country', …),   // code ISO (« bj ») ; à défaut, celui du pays choisi
        'countryName'  => old('country', …),         // pays du formulaire, pour l'indicatif par défaut
        'number'       => old('phone', …),           // numéro national
        'required'     => false,                     // true : obligatoire ; 'organization' : obligatoire pour une organisation
        'follow'       => 'country',                 // nom du champ pays : l'indicatif suit le pays choisi
        'phoneLabel'   => null,                      // libellé (défaut « Téléphone »)
        'phoneHint'    => null,                      // aide sous le champ (défaut : usage du numéro de contact)
        'phoneErrors'  => true,                      // false : erreurs affichées par la page (connexion)
    ])
--}}
@php
    $phoneRequired = $required ?? false;
    $phoneCode     = strtolower(trim((string) ($phoneCountry ?? ''))) ?: \App\Support\Countries::code($countryName ?? null);
@endphp
<div>
    <label for="phone" class="form-label">
        {{ $phoneLabel ?? __('profile.edit.phone') }} @if($phoneRequired === true)*@endif
        @if($phoneRequired === 'organization')<span class="font-normal text-slate-500">{{ __('profile.phone_input.org_required') }}</span>@endif
    </label>
    <div class="flex gap-2">
        <div class="w-36 shrink-0 sm:w-40">
            <select id="phone_country" name="phone_country" class="form-input" data-country-select data-placeholder="{{ __('profile.phone_input.dial_code') }}"
                    data-empty-text="{{ __('profile.phone_input.no_match') }}" @if(!empty($follow)) data-follow-country="{{ $follow }}" @endif
                    aria-label="{{ __('profile.phone_input.dial_code_aria') }}" autocomplete="tel-country-code" @error('phone_country') aria-invalid="true" @enderror>
                <option value="">{{ __('profile.phone_input.dial_code') }}</option>
                @foreach(\App\Support\Countries::all() as $name)
                @php($code = \App\Support\Countries::code($name))
                <option value="{{ $code }}" data-flag="{{ \App\Support\Countries::flagUrl($name) }}" data-label="+{{ \App\Support\Countries::dialCode($code) }}"
                        @selected($code === $phoneCode)>{{ $name }} (+{{ \App\Support\Countries::dialCode($code) }})</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-0 flex-1">
            <input id="phone" type="tel" name="phone" value="{{ $number ?? '' }}" inputmode="tel" autocomplete="tel-national" maxlength="30"
                   class="form-input" placeholder="{{ __('profile.phone_input.number') }}" @if($phoneRequired === true) required @endif
                   @error('phone') aria-invalid="true" @enderror aria-describedby="phone-hint">
        </div>
    </div>
    @if($phoneErrors ?? true)
    @error('phone_country')<p class="form-error">{{ $message }}</p>@enderror
    @error('phone')<p class="form-error">{{ $message }}</p>@enderror
    @endif
    <p id="phone-hint" class="form-hint">{{ $phoneHint ?? __('profile.phone_input.hint') }}</p>
</div>

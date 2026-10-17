@extends('layouts.app')
@use('App\Enums\EventStatus')
@php
    $editing     = $event->exists;
    $organizers  = $organizers ?? [];
    $header      = $editing ? __('events.form.edit_title') : __('events.index.create');
    $subheader   = $editing ? $event->title : __('events.form.subheader');
    $breadcrumbs = $editing
        ? [
            ['label' => __('layout.nav.home'), 'url' => route('home')],
            ['label' => __('layout.nav.events'), 'url' => route('events.index')],
            ['label' => Str::limit($event->title, 40), 'url' => route('events.show', $event->id)],
            ['label' => __('events.form.breadcrumb_edit')],
        ]
        : [
            ['label' => __('layout.nav.home'), 'url' => route('home')],
            ['label' => __('layout.nav.events'), 'url' => route('events.index')],
            ['label' => __('events.form.breadcrumb_create')],
        ];
    $statuses = [
        EventStatus::Draft->value     => [__('events.form.status.draft'), __('events.form.status.draft_hint')],
        EventStatus::Published->value => [__('events.form.status.published'), __('events.form.status.published_hint')],
    ];
    if ($editing && $event->status === EventStatus::Postponed) {
        // Reporté sans date : changer la date de début le reprogramme (il redevient « Publié »).
        $statuses[EventStatus::Postponed->value] = [__('events.badges.postponed_tbd'), __('events.form.status.postponed_hint')];
    }
    if ($editing) {
        $statuses[EventStatus::Cancelled->value] = [__('events.badges.cancelled'), __('events.form.status.cancelled_hint')];
    }
    $currentStatus = old('status', $event->status?->value ?? EventStatus::Published->value);
    $guests = old('guests', $event->guests ?? []);
    if (empty($guests)) $guests = [['name' => '', 'role' => '']];
    $guests = array_values($guests);
    $existingImages = $editing ? $event->images : collect();
    $freeImages = max(\App\Models\Event::MAX_IMAGES - $existingImages->count(), 0);
    $dt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d\TH:i') : '';
@endphp
@section('title', $header)

@section('content')
<div class="mx-auto max-w-3xl">

    @if($errors->any())
    <div class="alert-error mb-4" role="alert">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
        <p>{{ __('events.form.errors') }}</p>
    </div>
    @endif

    {{-- Formulaires des images existantes (hors du formulaire principal : pas de formulaires imbriqués) --}}
    @foreach($existingImages as $image)
    <form id="image-remove-{{ $image->id }}" method="POST" action="{{ route('events.images.destroy', [$event->id, $image->id]) }}" data-loading-label="{{ __('events.form.removing') }}" hidden>
        @csrf @method('DELETE')
    </form>
    <form id="image-cover-{{ $image->id }}" method="POST" action="{{ route('events.images.cover', [$event->id, $image->id]) }}" data-loading-label="{{ __('events.saving') }}" hidden>
        @csrf
    </form>
    @endforeach

    <form method="POST" action="{{ $editing ? route('events.update', $event->id) : route('events.store') }}" enctype="multipart/form-data"
          id="event-form" class="card divide-y divide-slate-100" data-loading-label="{{ __('events.form.saving_event') }}">
        @csrf
        @if($editing) @method('PUT') @endif

        {{-- L'événement --}}
        <section class="space-y-4 p-5 sm:p-6">
            <h2 class="card-title">{{ __('events.form.section_event') }}</h2>

            {{-- Organisateur : soi-même ou une organisation vérifiée gérée (création seulement) --}}
            @if(!$editing && count($organizers) > 1)
            <div>
                <label for="organization_id" class="form-label">{{ __('events.form.organizer') }} *</label>
                <select id="organization_id" name="organization_id" required class="form-input" @error('organization_id') aria-invalid="true" @enderror aria-describedby="organization_id-hint">
                    @foreach($organizers as $orgId => $orgName)
                    <option value="{{ $orgId }}" @selected(old('organization_id', array_key_first($organizers)) === $orgId)>{{ $orgName }}{{ $orgId === Auth::id() ? ' ' . __('events.form.me') : '' }}</option>
                    @endforeach
                </select>
                <p id="organization_id-hint" class="form-hint">{{ __('events.form.organizer_hint') }}</p>
                @error('organization_id')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            @elseif(!$editing && count($organizers) === 1 && array_key_first($organizers) !== Auth::id())
            <input type="hidden" name="organization_id" value="{{ array_key_first($organizers) }}">
            <p class="alert-info"><i class="fa-solid fa-building mt-0.5" aria-hidden="true"></i><span>{!! __('events.form.created_for', ['name' => '<strong class="font-semibold">' . e(reset($organizers)) . '</strong>']) !!}</span></p>
            @elseif($editing && $event->organizer && $event->organizer_id !== Auth::id())
            <p class="text-sm text-slate-500"><i class="fa-solid fa-user-tie mr-1" aria-hidden="true"></i>{{ __('events.form.organized_by') }} <span class="font-semibold text-slate-700">{{ $event->organizer->display_name }}</span></p>
            @endif

            <div>
                <label for="title" class="form-label">{{ __('events.form.title') }} *</label>
                <input id="title" type="text" name="title" value="{{ old('title', $event->title) }}" required maxlength="150" class="form-input"
                       placeholder="{{ __('events.form.title_placeholder') }}" @error('title') aria-invalid="true" @enderror>
                @error('title')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="type" class="form-label">{{ __('events_past.type_filter') }} *</label>
                <select id="type" name="type" required class="form-input" @error('type') aria-invalid="true" @enderror>
                    <option value="">{{ __('events.form.choose_type') }}</option>
                    @foreach(\App\Enums\EventType::options() as $value => $label)
                    <option value="{{ $value }}" @selected(old('type', $event->type?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('type')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="description" class="form-label">{{ __('events.form.description') }}</label>
                <textarea id="description" name="description" rows="6" maxlength="5000" class="form-input"
                          placeholder="{{ __('events.form.description_placeholder') }}" @error('description') aria-invalid="true" @enderror>{{ old('description', $event->description) }}</textarea>
                <p class="form-hint">{{ __('events.form.description_hint') }}</p>
                @error('description')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <fieldset>
                <legend class="form-label">{{ __('events.form.publication') }} *</legend>
                <div class="{{ $editing ? 'grid grid-cols-1 gap-2 sm:grid-cols-3' : 'grid grid-cols-1 gap-2 sm:grid-cols-2' }}">
                    @foreach($statuses as $value => [$label, $hint])
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50">
                        <input type="radio" name="status" value="{{ $value }}" class="mt-1" @checked($currentStatus === $value) required>
                        <span class="min-w-0">
                            <span class="block font-semibold text-slate-900">{{ $label }}</span>
                            <span class="block text-xs text-slate-500">{{ $hint }}</span>
                        </span>
                    </label>
                    @endforeach
                </div>
                @error('status')<p class="form-error">{{ $message }}</p>@enderror
            </fieldset>
        </section>

        {{-- Dates et lieu --}}
        <section class="space-y-4 p-5 sm:p-6">
            <h2 class="card-title">{{ __('events.form.section_dates') }}</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="starts_at" class="form-label">{{ __('events.form.start') }} *</label>
                    <input id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at', $dt($event->starts_at)) }}" required class="form-input" @error('starts_at') aria-invalid="true" @enderror>
                    @error('starts_at')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="ends_at" class="form-label">{{ __('events.form.end') }} *</label>
                    <input id="ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at', $dt($event->ends_at)) }}" required class="form-input" @error('ends_at') aria-invalid="true" @enderror>
                    @error('ends_at')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label for="location" class="form-label">{{ __('events.form.location') }}</label>
                <input id="location" type="text" name="location" value="{{ old('location', $event->location) }}" maxlength="200" class="form-input"
                       placeholder="{{ __('events.form.location_placeholder') }}" @error('location') aria-invalid="true" @enderror>
                @error('location')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="address" class="form-label">{{ __('events.form.address') }}</label>
                <input id="address" type="text" name="address" value="{{ old('address', $event->address) }}" maxlength="300" class="form-input"
                       placeholder="{{ __('events.form.address_placeholder') }}" aria-describedby="address-hint" @error('address') aria-invalid="true" @enderror>
                <p id="address-hint" class="form-hint">{{ __('events.form.address_hint') }}</p>
                @error('address')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="city" class="form-label">{{ __('events.form.city') }}</label>
                    <input id="city" type="text" name="city" value="{{ old('city', $event->city) }}" maxlength="100" class="form-input" @error('city') aria-invalid="true" @enderror>
                    @error('city')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="country" class="form-label">{{ __('events.form.country') }}</label>
                    @include('components.country-select', ['name' => 'country', 'id' => 'country', 'value' => old('country', $event->country)])
                    @error('country')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Position sur la carte (OpenStreetMap) : repère GPS facultatif pour l'itinéraire --}}
            @php
                $lat = old('latitude', $event->latitude);
                $lng = old('longitude', $event->longitude);
            @endphp
            <fieldset class="space-y-3" data-event-map-picker>
                <legend class="form-label">{{ __('events.form.map') }}</legend>
                <p class="form-hint">{{ __('events.form.map_hint') }}</p>
                <input type="hidden" name="latitude" value="{{ $lat }}" data-map-lat>
                <input type="hidden" name="longitude" value="{{ $lng }}" data-map-lng>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-secondary btn-sm" data-map-locate><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>{{ __('events.form.map_locate') }}</button>
                    <button type="button" class="btn-secondary btn-sm" data-map-search><i class="fa-solid fa-magnifying-glass-location" aria-hidden="true"></i>{{ __('events.form.map_search') }}</button>
                    <button type="button" class="btn-ghost btn-sm" data-map-clear @if(blank($lat)) hidden @endif><i class="fa-solid fa-xmark" aria-hidden="true"></i>{{ __('events.form.map_clear') }}</button>
                </div>
                <div class="event-map h-72 w-full overflow-hidden rounded-lg border border-slate-200 bg-slate-100" data-map
                     aria-label="{{ __('events.form.map_label') }}"></div>
                <p class="text-xs text-slate-500" data-map-status aria-live="polite">
                    @if(filled($lat) && filled($lng))
                        {{ __('events.form.map_placed', ['lat' => $lat, 'lng' => $lng]) }}
                    @else
                        {{ __('events.form.map_none') }}
                    @endif
                </p>
                @error('latitude')<p class="form-error">{{ $message }}</p>@enderror
                @error('longitude')<p class="form-error">{{ $message }}</p>@enderror
            </fieldset>
        </section>

        {{-- Invités principaux --}}
        <section class="space-y-4 p-5 sm:p-6" data-guests data-max="{{ \App\Models\Event::MAX_GUESTS }}">
            <div>
                <h2 class="card-title">{{ __('events.form.guests') }}</h2>
                <p class="form-hint">{{ __('events.form.guests_hint') }}</p>
            </div>
            @error('guests')<p class="form-error">{{ $message }}</p>@enderror
            <ul class="space-y-3" data-guests-list>
                @foreach($guests as $i => $guest)
                <li class="grid grid-cols-1 gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end sm:border-0 sm:p-0" data-guest-row>
                    <div>
                        <label for="guest-name-{{ $i }}" class="form-label">{{ __('events.form.guest_name') }}</label>
                        <input id="guest-name-{{ $i }}" type="text" name="guests[{{ $i }}][name]" value="{{ $guest['name'] ?? '' }}" maxlength="120" class="form-input"
                               @error("guests.$i.name") aria-invalid="true" @enderror>
                        @error("guests.$i.name")<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="guest-role-{{ $i }}" class="form-label">{{ __('events.form.guest_role') }}</label>
                        <input id="guest-role-{{ $i }}" type="text" name="guests[{{ $i }}][role]" value="{{ $guest['role'] ?? '' }}" maxlength="120" class="form-input" placeholder="{{ __('events.form.guest_role_placeholder') }}">
                    </div>
                    <button type="button" class="btn-ghost justify-self-end" data-guest-remove aria-label="{{ __('events.form.guest_remove') }}"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                </li>
                @endforeach
            </ul>
            <template data-guest-template>
                <li class="grid grid-cols-1 gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end sm:border-0 sm:p-0" data-guest-row>
                    <div>
                        <label for="guest-name-__INDEX__" class="form-label">{{ __('events.form.guest_name') }}</label>
                        <input id="guest-name-__INDEX__" type="text" name="guests[__INDEX__][name]" maxlength="120" class="form-input">
                    </div>
                    <div>
                        <label for="guest-role-__INDEX__" class="form-label">{{ __('events.form.guest_role') }}</label>
                        <input id="guest-role-__INDEX__" type="text" name="guests[__INDEX__][role]" maxlength="120" class="form-input" placeholder="{{ __('events.form.guest_role_placeholder') }}">
                    </div>
                    <button type="button" class="btn-ghost justify-self-end" data-guest-remove aria-label="{{ __('events.form.guest_remove') }}"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                </li>
            </template>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="btn-secondary btn-sm" data-guest-add><i class="fa-solid fa-plus" aria-hidden="true"></i>{{ __('events.form.guest_add') }}</button>
                <p class="text-xs text-slate-500" data-guests-limit hidden>{{ __('events.validation.guests_max') }}</p>
            </div>
        </section>

        {{-- Images --}}
        <section class="space-y-4 p-5 sm:p-6">
            <div>
                <h2 class="card-title">{{ __('events.form.images') }}</h2>
                <p class="form-hint">{{ __('events.form.images_hint') }}</p>
            </div>

            @if($existingImages->isNotEmpty())
            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($existingImages as $image)
                <li class="overflow-hidden rounded-lg border border-slate-200">
                    <div class="relative aspect-video bg-slate-100">
                        <img src="{{ $image->url }}" alt="{{ __('events.form.image_alt', ['number' => $loop->iteration]) }}" class="h-full w-full object-cover" loading="lazy">
                        @if($loop->first)<span class="badge-blue absolute top-2 left-2">{{ __('events.form.cover') }}</span>@endif
                    </div>
                    <div class="flex flex-wrap gap-1 p-2">
                        @unless($loop->first)
                        <button type="submit" form="image-cover-{{ $image->id }}" class="btn-ghost btn-sm"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i>{{ __('events.form.make_cover') }}</button>
                        @endunless
                        <button type="button" class="btn-ghost btn-sm"
                                onclick="openConfirmModal('image-remove-{{ $image->id }}', {{ Js::from(__('events.form.image_remove_text')) }}, {{ Js::from(__('events.form.image_remove_title')) }}, {{ Js::from(__('events.form.remove')) }}, 'fa-trash')">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i>{{ __('events.form.remove') }}
                        </button>
                    </div>
                </li>
                @endforeach
            </ul>
            @endif

            @if($freeImages > 0)
            <div>
                <label for="images" class="form-label">{{ $existingImages->isNotEmpty() ? __('events.form.add_images') : __('events.form.choose_images') }}</label>
                <input id="images" type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="form-input"
                       data-event-images data-max="{{ $freeImages }}" aria-describedby="images-hint" @if($errors->has('images') || $errors->has('images.*')) aria-invalid="true" @endif>
                <p id="images-hint" class="form-hint">{{ trans_choice('events.form.images_left', $freeImages, ['count' => $freeImages]) }}</p>
                @error('images')<p class="form-error">{{ $message }}</p>@enderror
                @foreach($errors->get('images.*') as $messages)
                    <p class="form-error">{{ $messages[0] }}</p>
                @endforeach
                <p class="form-error" data-event-images-error hidden></p>
                <ul class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-6" data-event-images-preview aria-label="{{ __('events.form.images_preview') }}"></ul>
            </div>
            @else
            <p class="text-sm text-slate-500">{{ __('events.form.images_full') }}</p>
            @endif
        </section>

        {{-- Direct YouTube (docs/fonctionnalites/evenements.md › Direct YouTube) : lien collé par l'organisateur --}}
        <section class="p-5 sm:p-6">
            <label for="youtube_live_url" class="form-label"><i class="fa-brands fa-youtube text-error-500" aria-hidden="true"></i> {{ __('events.form.youtube_live') }}</label>
            <input id="youtube_live_url" type="text" name="youtube_live_url" inputmode="url" maxlength="300" class="form-input"
                   value="{{ old('youtube_live_url', $event->youtube_live_id ? \App\Support\YouTube::watchUrl($event->youtube_live_id) : '') }}"
                   placeholder="{{ __('events.form.youtube_live_placeholder') }}" aria-describedby="youtube-live-hint" @error('youtube_live_url') aria-invalid="true" @enderror>
            <p id="youtube-live-hint" class="form-hint">{{ __('events.form.youtube_live_hint') }}</p>
            @error('youtube_live_url')<p class="form-error">{{ $message }}</p>@enderror
        </section>

        {{-- Commentaires --}}
        <section class="p-5 sm:p-6">
            <div class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 px-4 py-3">
                <div>
                    <p class="text-sm font-medium text-slate-900">{{ __('events.form.comments') }}</p>
                    <p class="text-xs text-slate-500">{{ __('events.form.comments_hint') }}</p>
                </div>
                @include('components.switch', ['name' => 'comments_enabled', 'checked' => (bool) old('comments_enabled', $event->comments_enabled ?? true), 'label' => __('events.form.comments'), 'sendFalse' => true])
            </div>
        </section>

        <div class="flex flex-wrap justify-end gap-2 p-5 sm:p-6">
            <a href="{{ $editing ? route('events.show', $event->id) : route('events.index') }}" class="btn-secondary">{{ __('events.form.cancel') }}</a>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-check" aria-hidden="true"></i>{{ $editing ? __('events.form.save') : __('events.form.create') }}</button>
        </div>
    </form>
</div>
@endsection

@include('events.partials.leaflet')
@push('scripts')
<script>
// Formulaire d'événement : repère GPS sur une carte OpenStreetMap (docs/fonctionnalites/evenements.md).
(function () {
    const box = document.querySelector('[data-event-map-picker]');
    if (!box) return;
    const latInput = box.querySelector('[data-map-lat]');
    const lngInput = box.querySelector('[data-map-lng]');
    const status   = box.querySelector('[data-map-status]');
    const clearBtn = box.querySelector('[data-map-clear]');
    const mapEl    = box.querySelector('[data-map]');
    const T        = @js(__('events.form.map_js'));
    if (!window.L) {
        mapEl.textContent = T.load_failed;
        mapEl.classList.add('flex', 'items-center', 'justify-center', 'text-sm', 'text-slate-500');
        return;
    }

    const lat0 = parseFloat(latInput.value), lng0 = parseFloat(lngInput.value);
    const hasPoint = Number.isFinite(lat0) && Number.isFinite(lng0);
    // Sans repère : vue large sur l'Afrique de l'Ouest.
    const map = L.map(mapEl, { scrollWheelZoom: false }).setView(hasPoint ? [lat0, lng0] : [8.5, 2.3], hasPoint ? 16 : 5);
    window.testiOsmLayer().addTo(map);
    let marker = null;

    const say = (text) => { status.textContent = text; };
    const place = (lat, lng, zoom) => {
        lat = Math.round(lat * 1e7) / 1e7;
        lng = Math.round(lng * 1e7) / 1e7;
        latInput.value = lat;
        lngInput.value = lng;
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], { draggable: true, title: T.marker }).addTo(map);
            marker.on('dragend', () => { const p = marker.getLatLng(); place(p.lat, p.lng); });
        }
        if (zoom) map.setView([lat, lng], zoom);
        clearBtn.hidden = false;
        say(T.placed.replace(':lat', lat.toFixed(6)).replace(':lng', lng.toFixed(6)));
    };
    if (hasPoint) place(lat0, lng0);

    map.on('click', (e) => place(e.latlng.lat, e.latlng.lng));

    clearBtn.addEventListener('click', () => {
        if (marker) { map.removeLayer(marker); marker = null; }
        latInput.value = '';
        lngInput.value = '';
        clearBtn.hidden = true;
        say(T.none);
    });

    box.querySelector('[data-map-locate]').addEventListener('click', () => {
        if (!navigator.geolocation) { say(T.no_geolocation); return; }
        say(T.locating);
        navigator.geolocation.getCurrentPosition(
            (pos) => place(pos.coords.latitude, pos.coords.longitude, 17),
            (err) => say(err.code === err.PERMISSION_DENIED
                ? T.denied
                : T.unavailable),
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 });
    });

    // Géocodage Nominatim (OpenStreetMap) : une requête par clic seulement, jamais à la frappe
    // (politique d'usage : https://operations.osmfoundation.org/policies/nominatim/).
    const searchBtn = box.querySelector('[data-map-search]');
    let lastSearch = 0;
    searchBtn.addEventListener('click', async () => {
        const form = box.closest('form');
        const val  = (n) => (form.querySelector(`[name="${n}"]`)?.value || '').trim();
        const q = [val('address'), val('location'), val('city'), val('country')].filter(Boolean).join(', ');
        if (!q) { say(T.empty_query); return; }
        if (Date.now() - lastSearch < 1500) return;
        lastSearch = Date.now();
        searchBtn.disabled = true;
        say(T.searching);
        try {
            const url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&accept-language={{ app()->getLocale() }}&q=' + encodeURIComponent(q);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const found = res.ok ? await res.json() : [];
            if (found.length) {
                place(parseFloat(found[0].lat), parseFloat(found[0].lon), 16);
                say(T.found.replace(':address', found[0].display_name));
            } else {
                say(T.not_found);
            }
        } catch (e) {
            say(T.failed);
        } finally {
            searchBtn.disabled = false;
        }
    });
})();
</script>
@endpush

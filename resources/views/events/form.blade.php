@extends('layouts.app')
@use('App\Enums\EventStatus')
@php
    $editing     = $event->exists;
    $organizers  = $organizers ?? [];
    $header      = $editing ? "Modifier l'événement" : 'Créer un événement';
    $subheader   = $editing ? $event->title : 'Annoncez une croisade, une conférence, un camp… Les participants pourront répondre et partager leurs témoignages.';
    $breadcrumbs = $editing
        ? [
            ['label' => 'Accueil', 'url' => route('home')],
            ['label' => 'Événements', 'url' => route('events.index')],
            ['label' => Str::limit($event->title, 40), 'url' => route('events.show', $event->id)],
            ['label' => 'Modifier'],
        ]
        : [
            ['label' => 'Accueil', 'url' => route('home')],
            ['label' => 'Événements', 'url' => route('events.index')],
            ['label' => 'Créer'],
        ];
    $statuses = [
        EventStatus::Draft->value     => ['Brouillon', 'Visible de vous seul, à publier plus tard.'],
        EventStatus::Published->value => ['Publié', 'Visible de tous ; les participants peuvent répondre.'],
    ];
    if ($editing) {
        $statuses[EventStatus::Cancelled->value] = ['Annulé', "Reste visible avec la mention « Annulé » ; plus de réponse possible."];
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
        <p>L'événement n'a pas pu être enregistré. Merci de corriger les champs signalés ci-dessous.</p>
    </div>
    @endif

    {{-- Formulaires des images existantes (hors du formulaire principal : pas de formulaires imbriqués) --}}
    @foreach($existingImages as $image)
    <form id="image-remove-{{ $image->id }}" method="POST" action="{{ route('events.images.destroy', [$event->id, $image->id]) }}" data-loading-label="Retrait…" hidden>
        @csrf @method('DELETE')
    </form>
    <form id="image-cover-{{ $image->id }}" method="POST" action="{{ route('events.images.cover', [$event->id, $image->id]) }}" data-loading-label="Enregistrement…" hidden>
        @csrf
    </form>
    @endforeach

    <form method="POST" action="{{ $editing ? route('events.update', $event->id) : route('events.store') }}" enctype="multipart/form-data"
          id="event-form" class="card divide-y divide-slate-100" data-loading-label="Enregistrement de l'événement…">
        @csrf
        @if($editing) @method('PUT') @endif

        {{-- L'événement --}}
        <section class="space-y-4 p-5 sm:p-6">
            <h2 class="card-title">L'événement</h2>

            {{-- Organisateur : soi-même ou une organisation vérifiée gérée (création seulement) --}}
            @if(!$editing && count($organizers) > 1)
            <div>
                <label for="organization_id" class="form-label">Organisateur *</label>
                <select id="organization_id" name="organization_id" required class="form-input" @error('organization_id') aria-invalid="true" @enderror aria-describedby="organization_id-hint">
                    @foreach($organizers as $orgId => $orgName)
                    <option value="{{ $orgId }}" @selected(old('organization_id', array_key_first($organizers)) === $orgId)>{{ $orgName }}{{ $orgId === Auth::id() ? ' (moi)' : '' }}</option>
                    @endforeach
                </select>
                <p id="organization_id-hint" class="form-hint">L'événement est publié au nom de l'organisateur choisi.</p>
                @error('organization_id')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            @elseif(!$editing && count($organizers) === 1 && array_key_first($organizers) !== Auth::id())
            <input type="hidden" name="organization_id" value="{{ array_key_first($organizers) }}">
            <p class="alert-info"><i class="fa-solid fa-building mt-0.5" aria-hidden="true"></i><span>Créé au nom de <strong class="font-semibold">{{ reset($organizers) }}</strong>.</span></p>
            @elseif($editing && $event->organizer && $event->organizer_id !== Auth::id())
            <p class="text-sm text-slate-500"><i class="fa-solid fa-user-tie mr-1" aria-hidden="true"></i>Organisé par <span class="font-semibold text-slate-700">{{ $event->organizer->display_name }}</span></p>
            @endif

            <div>
                <label for="title" class="form-label">Titre *</label>
                <input id="title" type="text" name="title" value="{{ old('title', $event->title) }}" required maxlength="150" class="form-input"
                       placeholder="Ex. : Grande croisade de guérison à Cotonou" @error('title') aria-invalid="true" @enderror>
                @error('title')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="type" class="form-label">Type d'événement *</label>
                <select id="type" name="type" required class="form-input" @error('type') aria-invalid="true" @enderror>
                    <option value="">Choisir un type</option>
                    @foreach(\App\Enums\EventType::options() as $value => $label)
                    <option value="{{ $value }}" @selected(old('type', $event->type?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('type')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="description" class="form-label">Présentation</label>
                <textarea id="description" name="description" rows="6" maxlength="5000" class="form-input"
                          placeholder="Programme, thème, public attendu, informations pratiques…" @error('description') aria-invalid="true" @enderror>{{ old('description', $event->description) }}</textarea>
                <p class="form-hint">5 000 caractères au plus. Les retours à la ligne sont conservés.</p>
                @error('description')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <fieldset>
                <legend class="form-label">Publication *</legend>
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
            <h2 class="card-title">Dates et lieu</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="starts_at" class="form-label">Début *</label>
                    <input id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at', $dt($event->starts_at)) }}" required class="form-input" @error('starts_at') aria-invalid="true" @enderror>
                    @error('starts_at')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="ends_at" class="form-label">Fin *</label>
                    <input id="ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at', $dt($event->ends_at)) }}" required class="form-input" @error('ends_at') aria-invalid="true" @enderror>
                    @error('ends_at')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label for="location" class="form-label">Lieu</label>
                <input id="location" type="text" name="location" value="{{ old('location', $event->location) }}" maxlength="200" class="form-input"
                       placeholder="Ex. : Stade de l'Amitié, salle polyvalente…" @error('location') aria-invalid="true" @enderror>
                @error('location')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="address" class="form-label">Adresse</label>
                <input id="address" type="text" name="address" value="{{ old('address', $event->address) }}" maxlength="300" class="form-input"
                       placeholder="Ex. : Boulevard de la Marina, quartier Zongo, face à la pharmacie…" aria-describedby="address-hint" @error('address') aria-invalid="true" @enderror>
                <p id="address-hint" class="form-hint">Rue, quartier, point de repère : ce qui aide les participants à trouver le lieu.</p>
                @error('address')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="city" class="form-label">Ville</label>
                    <input id="city" type="text" name="city" value="{{ old('city', $event->city) }}" maxlength="100" class="form-input" @error('city') aria-invalid="true" @enderror>
                    @error('city')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="country" class="form-label">Pays</label>
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
                <legend class="form-label">Position sur la carte</legend>
                <p class="form-hint">Touchez la carte pour placer le repère du lieu (facultatif). Les participants pourront lancer l'itinéraire depuis leur téléphone.</p>
                <input type="hidden" name="latitude" value="{{ $lat }}" data-map-lat>
                <input type="hidden" name="longitude" value="{{ $lng }}" data-map-lng>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-secondary btn-sm" data-map-locate><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>Ma position</button>
                    <button type="button" class="btn-secondary btn-sm" data-map-search><i class="fa-solid fa-magnifying-glass-location" aria-hidden="true"></i>Chercher l'adresse sur la carte</button>
                    <button type="button" class="btn-ghost btn-sm" data-map-clear @if(blank($lat)) hidden @endif><i class="fa-solid fa-xmark" aria-hidden="true"></i>Effacer le repère</button>
                </div>
                <div class="event-map h-72 w-full overflow-hidden rounded-lg border border-slate-200 bg-slate-100" data-map
                     aria-label="Carte : touchez pour placer le repère du lieu de l'événement"></div>
                <p class="text-xs text-slate-500" data-map-status aria-live="polite">
                    @if(filled($lat) && filled($lng))
                        Repère placé : {{ $lat }}, {{ $lng }}
                    @else
                        Aucun repère placé.
                    @endif
                </p>
                @error('latitude')<p class="form-error">{{ $message }}</p>@enderror
                @error('longitude')<p class="form-error">{{ $message }}</p>@enderror
            </fieldset>
        </section>

        {{-- Invités principaux --}}
        <section class="space-y-4 p-5 sm:p-6" data-guests data-max="{{ \App\Models\Event::MAX_GUESTS }}">
            <div>
                <h2 class="card-title">Invités principaux</h2>
                <p class="form-hint">Orateurs, chantres, pasteurs invités… Dix au plus. Laissez une ligne vide pour l'ignorer.</p>
            </div>
            @error('guests')<p class="form-error">{{ $message }}</p>@enderror
            <ul class="space-y-3" data-guests-list>
                @foreach($guests as $i => $guest)
                <li class="grid grid-cols-1 gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end sm:border-0 sm:p-0" data-guest-row>
                    <div>
                        <label for="guest-name-{{ $i }}" class="form-label">Nom</label>
                        <input id="guest-name-{{ $i }}" type="text" name="guests[{{ $i }}][name]" value="{{ $guest['name'] ?? '' }}" maxlength="120" class="form-input"
                               @error("guests.$i.name") aria-invalid="true" @enderror>
                        @error("guests.$i.name")<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="guest-role-{{ $i }}" class="form-label">Rôle</label>
                        <input id="guest-role-{{ $i }}" type="text" name="guests[{{ $i }}][role]" value="{{ $guest['role'] ?? '' }}" maxlength="120" class="form-input" placeholder="Ex. : Orateur principal">
                    </div>
                    <button type="button" class="btn-ghost justify-self-end" data-guest-remove aria-label="Retirer cet invité"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                </li>
                @endforeach
            </ul>
            <template data-guest-template>
                <li class="grid grid-cols-1 gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end sm:border-0 sm:p-0" data-guest-row>
                    <div>
                        <label for="guest-name-__INDEX__" class="form-label">Nom</label>
                        <input id="guest-name-__INDEX__" type="text" name="guests[__INDEX__][name]" maxlength="120" class="form-input">
                    </div>
                    <div>
                        <label for="guest-role-__INDEX__" class="form-label">Rôle</label>
                        <input id="guest-role-__INDEX__" type="text" name="guests[__INDEX__][role]" maxlength="120" class="form-input" placeholder="Ex. : Orateur principal">
                    </div>
                    <button type="button" class="btn-ghost justify-self-end" data-guest-remove aria-label="Retirer cet invité"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                </li>
            </template>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="btn-secondary btn-sm" data-guest-add><i class="fa-solid fa-plus" aria-hidden="true"></i>Ajouter un invité</button>
                <p class="text-xs text-slate-500" data-guests-limit hidden>Dix invités au plus.</p>
            </div>
        </section>

        {{-- Images --}}
        <section class="space-y-4 p-5 sm:p-6">
            <div>
                <h2 class="card-title">Images</h2>
                <p class="form-hint">Six images au plus (JPG, PNG ou WebP, 8 Mo et 600 × 300 pixels au moins). La première sert de vignette.</p>
            </div>

            @if($existingImages->isNotEmpty())
            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($existingImages as $image)
                <li class="overflow-hidden rounded-lg border border-slate-200">
                    <div class="relative aspect-video bg-slate-100">
                        <img src="{{ $image->url }}" alt="Image {{ $loop->iteration }}" class="h-full w-full object-cover" loading="lazy">
                        @if($loop->first)<span class="badge-blue absolute top-2 left-2">Vignette</span>@endif
                    </div>
                    <div class="flex flex-wrap gap-1 p-2">
                        @unless($loop->first)
                        <button type="submit" form="image-cover-{{ $image->id }}" class="btn-ghost btn-sm"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i>Mettre en premier</button>
                        @endunless
                        <button type="button" class="btn-ghost btn-sm"
                                onclick="openConfirmModal('image-remove-{{ $image->id }}', 'Cette image sera retirée de l\'événement.', 'Retirer l\'image', 'Retirer', 'fa-trash')">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i>Retirer
                        </button>
                    </div>
                </li>
                @endforeach
            </ul>
            @endif

            @if($freeImages > 0)
            <div>
                <label for="images" class="form-label">{{ $existingImages->isNotEmpty() ? 'Ajouter des images' : 'Choisir des images' }}</label>
                <input id="images" type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="form-input"
                       data-event-images data-max="{{ $freeImages }}" aria-describedby="images-hint" @if($errors->has('images') || $errors->has('images.*')) aria-invalid="true" @endif>
                <p id="images-hint" class="form-hint">{{ $freeImages }} image{{ $freeImages > 1 ? 's' : '' }} au plus.</p>
                @error('images')<p class="form-error">{{ $message }}</p>@enderror
                @foreach($errors->get('images.*') as $messages)
                    <p class="form-error">{{ $messages[0] }}</p>
                @endforeach
                <p class="form-error" data-event-images-error hidden></p>
                <ul class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-6" data-event-images-preview aria-label="Aperçu des images choisies"></ul>
            </div>
            @else
            <p class="text-sm text-slate-500">Six images au maximum : retirez-en une pour en ajouter une autre.</p>
            @endif
        </section>

        {{-- Commentaires --}}
        <section class="p-5 sm:p-6">
            <div class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 px-4 py-3">
                <div>
                    <p class="text-sm font-medium text-slate-900">Autoriser les commentaires</p>
                    <p class="text-xs text-slate-500">Les participants racontent ce qu'ils ont vécu ; vous pourrez enregistrer un commentaire comme témoignage officiel.</p>
                </div>
                @include('components.switch', ['name' => 'comments_enabled', 'checked' => (bool) old('comments_enabled', $event->comments_enabled ?? true), 'label' => 'Autoriser les commentaires', 'sendFalse' => true])
            </div>
        </section>

        <div class="flex flex-wrap justify-end gap-2 p-5 sm:p-6">
            <a href="{{ $editing ? route('events.show', $event->id) : route('events.index') }}" class="btn-secondary">Annuler</a>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-check" aria-hidden="true"></i>{{ $editing ? 'Enregistrer' : "Créer l'événement" }}</button>
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
    if (!window.L) {
        mapEl.textContent = 'La carte n’a pas pu être chargée (connexion ?).';
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
            marker = L.marker([lat, lng], { draggable: true, title: "Lieu de l'événement" }).addTo(map);
            marker.on('dragend', () => { const p = marker.getLatLng(); place(p.lat, p.lng); });
        }
        if (zoom) map.setView([lat, lng], zoom);
        clearBtn.hidden = false;
        say(`Repère placé : ${lat.toFixed(6)}, ${lng.toFixed(6)}`);
    };
    if (hasPoint) place(lat0, lng0);

    map.on('click', (e) => place(e.latlng.lat, e.latlng.lng));

    clearBtn.addEventListener('click', () => {
        if (marker) { map.removeLayer(marker); marker = null; }
        latInput.value = '';
        lngInput.value = '';
        clearBtn.hidden = true;
        say('Aucun repère placé.');
    });

    box.querySelector('[data-map-locate]').addEventListener('click', () => {
        if (!navigator.geolocation) { say('Votre navigateur ne permet pas de connaître votre position.'); return; }
        say('Recherche de votre position…');
        navigator.geolocation.getCurrentPosition(
            (pos) => place(pos.coords.latitude, pos.coords.longitude, 17),
            (err) => say(err.code === err.PERMISSION_DENIED
                ? 'Accès à la position refusé : autorisez la localisation pour ce site dans les réglages du navigateur, ou touchez la carte.'
                : 'Position introuvable pour le moment. Touchez la carte pour placer le repère.'),
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
        if (!q) { say('Saisissez d’abord le lieu, l’adresse ou la ville.'); return; }
        if (Date.now() - lastSearch < 1500) return;
        lastSearch = Date.now();
        searchBtn.disabled = true;
        say('Recherche de l’adresse…');
        try {
            const url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&accept-language=fr&q=' + encodeURIComponent(q);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const found = res.ok ? await res.json() : [];
            if (found.length) {
                place(parseFloat(found[0].lat), parseFloat(found[0].lon), 16);
                say(`Adresse trouvée : ${found[0].display_name}. Ajustez le repère si besoin.`);
            } else {
                say('Adresse introuvable sur la carte : touchez la carte pour placer le repère.');
            }
        } catch (e) {
            say('Recherche impossible pour le moment : touchez la carte pour placer le repère.');
        } finally {
            searchBtn.disabled = false;
        }
    });
})();
</script>
@endpush

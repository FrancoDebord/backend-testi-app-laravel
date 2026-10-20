@extends('layouts.app')
@section('title', __('testimonies.create.title'))
@php
    $header      = __('testimonies.create.title');
    $subheader   = __('testimonies.create.subheader');
    $breadcrumbs = [
        ['label' => __('testimonies.common.home'), 'url' => route('home')],
        ['label' => __('testimonies.create.breadcrumb_space'), 'url' => route('testimonies.mine')],
        ['label' => __('testimonies.create.breadcrumb_publish')],
    ];
    $types = ['text' => [__('testimonies.create.type_text'), 'fa-file-lines'], 'audio' => [__('testimonies.create.type_audio'), 'fa-microphone'], 'video' => [__('testimonies.create.type_video'), 'fa-video'],
              'youtube' => [__('testimonies.create.type_youtube'), 'fa-brands fa-youtube']];
    // Types autorisés pour ce compte (docs/fonctionnalites/types-de-temoignages.md) ; lien YouTube :
    // administrateurs par défaut, ou profils autorisés (docs/fonctionnalites/videos-youtube.md).
    $allowedTypes = $allowedTypes ?? app(\App\Services\TestimonyTypePolicy::class)->allowed(auth()->user());
    $types = array_intersect_key($types, array_flip($allowedTypes));
    $canYoutube = isset($types['youtube']);
    $visibilities = ['public' => __('testimonies.create.visibility_public'), 'followers' => __('testimonies.create.visibility_followers'), 'private' => __('testimonies.create.visibility_private')];
    $currentType = old('type', 'text');
    if (!isset($types[$currentType])) {
        $currentType = array_key_first($types) ?? 'text';
    }
    // « Nouvelle entrée » du carnet privé : /publish?visibility=private (docs/fonctionnalites/carnet-prive.md)
    $defaultVisibility = request('visibility') === 'private' && !($prophecy ?? null) ? 'private' : 'public';
@endphp

@section('content')
<div class="mx-auto max-w-3xl">

    <div class="alert-warning mb-4" data-offline-notice hidden role="status">
        <i class="fa-solid fa-wifi mt-0.5"></i>
        <p>{{ __('testimonies.create.offline') }}</p>
    </div>

    @if($errors->any())
    <div class="alert-error mb-4" role="alert">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
        <p>{{ __('testimonies.create.errors') }}</p>
    </div>
    @else
    {{-- Encouragement (config/encouragements.php), sans bouton : on est déjà sur la page de publication --}}
    <x-encouragement class="mb-4" verse="Psaume 66:16" :action="false" />
    @endif

    {{-- Pourquoi témoigner ? replié pour ne pas éloigner le formulaire (docs/fonctionnalites/pourquoi-temoigner.md) --}}
    <x-why-testify collapsible :action="false" class="mb-4" />

    @if($types === [])
    {{-- Aucun type ouvert pour ce compte (docs/fonctionnalites/types-de-temoignages.md) --}}
    @include('components.empty-state', ['title' => __('publishing.none_allowed_title'), 'text' => __('publishing.none_allowed_text')])
    @else
    <form method="POST" action="{{ route('testimonies.store') }}" enctype="multipart/form-data" id="testimony-form"
          class="card divide-y divide-slate-100"
          data-autosave="testimony-create" data-has-old="{{ session()->hasOldInput() ? '1' : '0' }}"
          data-online-only data-loading-label="{{ __('testimonies.create.sending') }}"
          @if($bunnyVideos) data-bunny-upload @endif>
        @csrf
        {{-- Témoignage officiel d'un événement (docs/fonctionnalites/evenements.md) --}}
        @if($event ?? null)
        <input type="hidden" name="event_id" value="{{ $event->id }}" data-autosave-ignore>
        <div class="p-5 sm:p-6">
            <div class="alert-info" role="status">
                <i class="fa-solid fa-calendar-days mt-0.5"></i>
                <p>{{ __('testimonies.create.event_before') }} <a href="{{ route('events.show', $event->id) }}" class="font-semibold underline">{{ $event->title }}</a>{{ __('testimonies.create.event_after') }}</p>
            </div>
        </div>
        @endif

        {{-- Témoignage sur un direct terminé (?live=, docs/fonctionnalites/lives.md) --}}
        @if($live ?? null)
        <div class="p-5 sm:p-6" data-live-link>
            <input type="hidden" name="live_id" value="{{ $live->id }}" data-autosave-ignore>
            <div class="alert-info" role="status">
                <i class="fa-solid fa-tower-broadcast mt-0.5" aria-hidden="true"></i>
                <p>{{ __('lives_replay.testify.banner') }} <a href="{{ route('lives.show', $live->id) }}" class="font-semibold underline">{{ $live->title }}</a>.</p>
            </div>
            @error('live_id')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        @endif

        {{-- Accomplissement d'une parole prophétique du carnet (docs/fonctionnalites/paroles-prophetiques.md) --}}
        @if($prophecy ?? null)
        <input type="hidden" name="prophecy_id" value="{{ $prophecy->id }}" data-autosave-ignore>
        <div class="space-y-3 p-5 sm:p-6">
            <div class="alert-info" role="status">
                <i class="fa-solid fa-scroll mt-0.5" aria-hidden="true"></i>
                <p>{{ __('testimonies.create.prophecy_before') }} <a href="{{ route('prophecies.show', $prophecy->id) }}" class="font-semibold underline">{{ $prophecy->title ?: __('testimonies.create.prophecy_received_on', ['date' => $prophecy->received_on?->translatedFormat(__('testimonies.create.date_format'))]) }}</a>{{ __('testimonies.create.prophecy_after') }}</p>
            </div>
            <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-4 text-sm text-slate-700 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50">
                <input type="checkbox" name="prophecy_public" value="1" class="mt-0.5 rounded" data-autosave-ignore @checked(session()->hasOldInput() ? old('prophecy_public') : true)>
                <span>
                    <span class="font-semibold text-slate-900">{{ __('testimonies.create.prophecy_public') }}</span>
                    <span class="mt-0.5 block text-xs text-slate-500">{{ __('testimonies.create.prophecy_public_help') }}</span>
                </span>
            </label>
        </div>
        @endif

        {{-- Témoignage de conversion : compte personnel, un seul par compte (docs/fonctionnalites/temoignage-de-conversion.md) --}}
        @if($canConversion)
        @php $currentKind = old('kind', $kind); @endphp
        <section class="space-y-4 p-5 sm:p-6" data-kind-picker>
            <fieldset>
                <legend class="form-label">{{ __('conversion.kind_label') }}</legend>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50">
                        <input type="radio" name="kind" value="standard" class="mt-1" @checked($currentKind !== 'conversion')>
                        <span>
                            <span class="block font-semibold text-slate-900">{{ __('conversion.kind_standard') }}</span>
                            <span class="block text-xs text-slate-500">{{ __('conversion.kind_standard_help') }}</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700 has-[:checked]:border-sun-400 has-[:checked]:bg-sun-50">
                        <input type="radio" name="kind" value="conversion" class="mt-1" @checked($currentKind === 'conversion')>
                        <span>
                            <span class="flex items-center gap-2 font-semibold text-slate-900"><i class="fa-solid fa-dove text-sun-700" aria-hidden="true"></i>{{ __('conversion.kind_conversion') }}</span>
                            <span class="block text-xs text-slate-500">{{ __('conversion.kind_conversion_help') }}</span>
                        </span>
                    </label>
                </div>
                @error('kind')<p class="form-error">{{ $message }}</p>@enderror
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-3" data-kind-section="conversion" @if($currentKind !== 'conversion') hidden @endif>
                <div>
                    <label for="conversion_year" class="form-label">{{ __('conversion.year') }} <span class="font-normal text-slate-500">({{ __('conversion.optional') }})</span></label>
                    <input id="conversion_year" type="number" name="conversion_year" value="{{ old('conversion_year') }}" min="{{ \App\Services\ConversionTestimonies::MIN_YEAR }}" max="{{ now()->year }}"
                           inputmode="numeric" class="form-input" placeholder="{{ __('conversion.year_placeholder') }}">
                    @error('conversion_year')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="conversion_place" class="form-label">{{ __('conversion.place') }} <span class="font-normal text-slate-500">({{ __('conversion.optional') }})</span></label>
                    <input id="conversion_place" type="text" name="conversion_place" value="{{ old('conversion_place') }}" maxlength="150" class="form-input"
                           placeholder="{{ __('conversion.place_placeholder') }}">
                    @error('conversion_place')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="conversion_verse" class="form-label">{{ __('conversion.verse') }} <span class="font-normal text-slate-500">({{ __('conversion.optional') }})</span></label>
                    <input id="conversion_verse" type="text" name="conversion_verse" value="{{ old('conversion_verse') }}" maxlength="100" class="form-input"
                           placeholder="{{ __('conversion.verse_placeholder') }}">
                    @error('conversion_verse')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>
        @elseif($conversionTestimony)
        <div class="p-5 sm:p-6">
            <div class="alert-info" role="status">
                <i class="fa-solid fa-dove mt-0.5" aria-hidden="true"></i>
                <p>{{ __('conversion.already_have') }} <a href="{{ route('testimonies.conversion.edit') }}" class="font-semibold underline">{{ __('conversion.edit_title') }}</a></p>
            </div>
        </div>
        @endif

        {{-- Contenu --}}
        <section class="space-y-4 p-5 sm:p-6">
            <h2 class="card-title">{{ __('testimonies.create.content') }}</h2>

            <div>
                <span class="form-label">{{ __('testimonies.create.type') }} *</span>
                <div class="{{ match (count($types)) { 4 => 'grid grid-cols-2 gap-2 sm:grid-cols-4', 3 => 'grid grid-cols-1 gap-2 sm:grid-cols-3', 2 => 'grid grid-cols-1 gap-2 sm:grid-cols-2', default => 'grid grid-cols-1 gap-2' } }}">
                    @foreach($types as $val => [$label, $icon])
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50 has-[:checked]:text-slate-900">
                        <input type="radio" name="type" value="{{ $val }}" @checked($currentType === $val)>
                        <i class="{{ str_starts_with($icon, 'fa-brands') ? $icon . ' text-error-500' : 'fa-solid ' . $icon . ' text-slate-400' }}" aria-hidden="true"></i>{{ $label }}
                    </label>
                    @endforeach
                </div>
                @error('type')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="title" class="form-label">{{ __('testimonies.create.title_label') }} *</label>
                <input id="title" type="text" name="title" value="{{ old('title') }}" required maxlength="200" class="form-input"
                       placeholder="{{ __('testimonies.create.title_placeholder') }}">
                @error('title')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="category" class="form-label">{{ __('testimonies.create.category') }} *</label>
                <select id="category" name="category" required class="form-input">
                    <option value="">{{ __('testimonies.create.category_choose') }}</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->slug }}" @selected(old('category') === $cat->slug)>{{ $cat->displayName() }}</option>
                    @endforeach
                </select>
                @error('category')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div data-type-section="text">
                <span id="body_text_label" class="form-label">{{ __('testimonies.create.body') }} <span data-type-required>*</span></span>
                @include('components.rich-editor', [
                    'name' => 'body_text',
                    'id' => 'body_text',
                    'value' => old('body_text'),
                    'labelId' => 'body_text_label',
                    'placeholder' => __('testimonies.create.body_placeholder'),
                    'requiredMessage' => __('testimonies.create.body_required'),
                    'mentions' => true,
                ])
                @include('components.mention-input')
                @foreach(array_filter((array) old('mention_ids', []), 'is_string') as $mentionId)
                <input type="hidden" name="mention_ids[]" value="{{ $mentionId }}">
                @endforeach
                <p class="form-hint">{{ __('testimonies.create.body_hint') }}</p>
                <p class="form-hint" data-media-summary-hint hidden>{{ __('testimonies.create.body_summary_hint') }}</p>
                @error('body_text')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div data-type-section="audio video">
                <label for="media_file" class="form-label" data-media-label>{{ __('testimonies.create.media_file') }}</label>
                <input id="media_file" type="file" name="media_file" accept="audio/*,video/*" class="form-input">
                <p class="form-hint" data-media-hint>{{ __('testimonies.create.media_hint') }}</p>
                @error('media_file')<p class="form-error">{{ $message }}</p>@enderror
                {{-- Progression de l'envoi vers Bunny Stream (resources/js/bunny-upload.js) --}}
                <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3" data-bunny-progress hidden aria-live="polite">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <p class="min-w-0 font-medium text-slate-800" data-bunny-state>{{ __('testimonies.create.upload_preparing') }}</p>
                        <span class="shrink-0 font-semibold text-primary-700 tabular-nums" data-bunny-percent>0 %</span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-label="{{ __('testimonies.create.upload_progress') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div class="h-full rounded-full bg-primary-600 transition-[width] duration-300" style="width: 0%" data-bunny-bar></div>
                    </div>
                    <p class="form-error mt-2" data-bunny-error hidden></p>
                    <p class="form-hint mt-1">{{ __('testimonies.create.upload_keep_open') }}</p>
                </div>
            </div>

            @if($canYoutube)
            {{-- Vidéo YouTube : administrateurs par défaut, ou profils autorisés (docs/fonctionnalites/videos-youtube.md) --}}
            <div data-type-section="youtube" class="rounded-lg border border-slate-200 p-4">
                <label for="youtube_url" class="form-label"><i class="fa-brands fa-youtube mr-1 text-error-500" aria-hidden="true"></i>{{ __('testimonies.create.youtube_url') }} *</label>
                <input id="youtube_url" type="url" name="youtube_url" value="{{ old('youtube_url') }}" maxlength="300" inputmode="url" class="form-input"
                       placeholder="https://www.youtube.com/watch?v=…" aria-describedby="youtube-hint">
                <p id="youtube-hint" class="form-hint">{{ __('testimonies.create.youtube_hint') }}</p>
                @error('youtube_url')<p class="form-error">{{ $message }}</p>@enderror

                {{-- Crédit de la vidéo d'origine (facultatif, affiché sous le lecteur) --}}
                <fieldset class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                    <legend class="text-sm font-semibold text-slate-900">{{ __('youtube_credit.section') }}</legend>
                    <p class="form-hint">{{ __('youtube_credit.section_help') }}</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="youtube_credit_author" class="form-label">{{ __('youtube_credit.author') }}</label>
                            <input id="youtube_credit_author" type="text" name="youtube_credit_author" value="{{ old('youtube_credit_author') }}" maxlength="150" class="form-input" aria-describedby="youtube-credit-author-hint">
                            <p id="youtube-credit-author-hint" class="form-hint">{{ __('youtube_credit.author_hint') }}</p>
                            @error('youtube_credit_author')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="youtube_credit_channel" class="form-label">{{ __('youtube_credit.channel') }}</label>
                            <input id="youtube_credit_channel" type="text" name="youtube_credit_channel" value="{{ old('youtube_credit_channel') }}" maxlength="150" class="form-input">
                            @error('youtube_credit_channel')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label for="youtube_credit_url" class="form-label">{{ __('youtube_credit.url') }}</label>
                        <input id="youtube_credit_url" type="url" name="youtube_credit_url" value="{{ old('youtube_credit_url') }}" maxlength="300" inputmode="url" class="form-input"
                               placeholder="https://www.youtube.com/@…" aria-describedby="youtube-credit-url-hint">
                        <p id="youtube-credit-url-hint" class="form-hint">{{ __('youtube_credit.url_hint') }}</p>
                        @error('youtube_credit_url')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </fieldset>
            </div>
            @endif

            <div>
                <label for="cover" class="form-label">{{ __('testimonies.create.cover') }}</label>
                <input id="cover" type="file" name="cover" accept="image/*" class="form-input">
                <p class="form-hint">{{ __('testimonies.create.cover_hint') }}</p>
                @error('cover')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </section>

        {{-- Référence biblique --}}
        <section class="space-y-4 p-5 sm:p-6">
            <h2 class="card-title">{{ __('testimonies.create.bible') }}</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="md:col-span-2">
                    <label for="bible_verse" class="form-label">{{ __('testimonies.create.bible_verse') }}</label>
                    <textarea id="bible_verse" name="bible_verse" rows="2" maxlength="500" class="form-input"
                              placeholder="{{ __('testimonies.create.bible_verse_placeholder') }}">{{ old('bible_verse', request('verset')) }}</textarea>
                    @error('bible_verse')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="bible_ref" class="form-label">{{ __('testimonies.create.bible_ref') }}</label>
                    <input id="bible_ref" type="text" name="bible_ref" value="{{ old('bible_ref', request('reference')) }}" maxlength="100" class="form-input" placeholder="{{ __('testimonies.create.bible_ref_placeholder') }}">
                    @error('bible_ref')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        {{-- Preuves (docs/fonctionnalites/preuves.md) --}}
        <section class="space-y-4 p-5 sm:p-6" aria-labelledby="proofs-title">
            <div>
                <h2 id="proofs-title" class="card-title">{{ __('testimonies.create.proofs') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('testimonies.create.proofs_help') }}</p>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach([1, 2] as $position)
                <div>
                    <label for="proof_{{ $position }}" class="form-label">{{ __('testimonies.proofs.item', ['position' => $position]) }}</label>
                    <input id="proof_{{ $position }}" type="file" name="proof_{{ $position }}" accept="image/jpeg,image/png,image/webp,application/pdf" class="form-input" aria-describedby="proof-hint">
                    @error('proof_' . $position)<p class="form-error">{{ $message }}</p>@enderror
                </div>
                @endforeach
            </div>
            <p id="proof-hint" class="form-hint">{{ __('testimonies.create.proofs_hint') }}</p>
            <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-4 text-sm text-slate-700 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50">
                <input type="checkbox" name="proofs_public" value="1" class="mt-0.5 rounded" @checked(old('proofs_public'))>
                <span>
                    <span class="font-semibold text-slate-900">{{ __('testimonies.create.proofs_public') }}</span>
                    <span class="mt-0.5 block text-xs text-slate-500">{{ __('testimonies.create.proofs_public_help') }}</span>
                </span>
            </label>
        </section>

        {{-- Diffusion --}}
        <section class="space-y-4 p-5 sm:p-6">
            <h2 class="card-title">{{ __('testimonies.create.sharing') }}</h2>
            <div>
                <label for="tags" class="form-label">{{ __('testimonies.create.tags') }}</label>
                <input id="tags" type="text" name="tags" value="{{ old('tags') }}" class="form-input" placeholder="{{ __('testimonies.create.tags_placeholder') }}">
                <p class="form-hint">{{ __('testimonies.create.tags_hint') }}</p>
            </div>
            <div>
                <span class="form-label">{{ __('testimonies.create.visibility') }}</span>
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    @foreach($visibilities as $val => $label)
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="radio" name="visibility" value="{{ $val }}" @checked(old('visibility', $defaultVisibility) === $val)>
                        {{ $label }}
                    </label>
                    @endforeach
                </div>
                @error('visibility')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </section>

        {{-- Engagement --}}
        <div class="space-y-4 p-5 sm:p-6">
            <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                <input type="checkbox" name="consent_given" value="1" required class="mt-0.5 rounded"
                       data-required-check data-autosave-ignore @checked(old('consent_given'))>
                <span>{{ __('testimonies.create.consent') }} *</span>
            </label>
            @error('consent_given')<p class="form-error">{{ $message }}</p>@enderror

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500" data-autosave-status>{{ __('testimonies.create.autosave') }}</p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('home') }}" class="btn-secondary">{{ __('testimonies.common.cancel') }}</a>
                    <button type="submit" class="btn-primary" data-submit-guard disabled>{{ __('testimonies.create.submit') }}</button>
                </div>
            </div>
        </div>
    </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('testimony-form');
    if (!form) return; // aucun type ouvert pour ce compte
    const bodyText = document.getElementById('body_text');
    const media = document.getElementById('media_file');
    const MEDIA = {
        audio: { label: @js(__('testimonies.create.audio_file')), accept: 'audio/*', hint: @js(__('testimonies.create.audio_hint')) },
        video: { label: @js(__('testimonies.create.video_file')), accept: 'video/*', hint: {{ \Illuminate\Support\Js::from($bunnyVideos ? __('testimonies.create.video_hint_bunny') : __('testimonies.create.video_hint')) }} },
    };

    function syncType() {
        const type = form.querySelector('input[name="type"]:checked')?.value || 'text';
        form.querySelectorAll('[data-type-section]').forEach(function (el) {
            // Le texte reste visible pour tous les types (résumé facultatif).
            const types = el.dataset.typeSection.split(' ');
            el.hidden = !(types.includes(type) || el.dataset.typeSection === 'text');
        });
        const isText = type === 'text';
        const youtube = form.querySelector('#youtube_url');
        if (youtube) youtube.required = type === 'youtube';
        // Champ obligatoire vérifié par l'éditeur au moment de l'envoi (le champ source est caché).
        bodyText.toggleAttribute('data-rt-required', isText);
        form.querySelector('[data-type-required]').hidden = !isText;
        form.querySelector('[data-media-summary-hint]').hidden = isText;
        if (MEDIA[type]) {
            form.querySelector('[data-media-label]').textContent = MEDIA[type].label;
            form.querySelector('[data-media-hint]').textContent = MEDIA[type].hint;
            media.accept = MEDIA[type].accept;
        }
    }

    // Témoignage de conversion : champs propres affichés, catégorie « Conversion » proposée.
    const conversionFields = form.querySelector('[data-kind-section="conversion"]');
    function syncKind() {
        if (!conversionFields) return;
        const isConversion = form.querySelector('input[name="kind"]:checked')?.value === 'conversion';
        conversionFields.hidden = !isConversion;
        const category = form.querySelector('#category');
        if (isConversion && category && !category.value && category.querySelector('option[value="conversion"]')) {
            category.value = 'conversion';
        }
    }

    form.addEventListener('change', function (e) {
        if (e.target.name === 'type') syncType();
        if (e.target.name === 'kind') syncKind();
    });
    form.addEventListener('autosave:ready', function () { syncType(); syncKind(); });
    syncType();
    syncKind();
})();
</script>
@endpush

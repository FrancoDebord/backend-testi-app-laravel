@extends('layouts.app')
@section('title', 'Publier un témoignage')
@php
    $header      = 'Publier un témoignage';
    $subheader   = 'Partagez comment Dieu a agi dans votre vie. Votre témoignage sera relu avant publication.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Mon espace', 'url' => route('testimonies.mine')],
        ['label' => 'Publier'],
    ];
    $types = ['text' => ['Texte', 'fa-file-lines'], 'audio' => ['Audio', 'fa-microphone'], 'video' => ['Vidéo', 'fa-video']];
    $visibilities = ['public' => 'Public', 'followers' => 'Abonnés uniquement', 'private' => 'Privé'];
    $currentType = old('type', 'text');
@endphp

@section('content')
<div class="mx-auto max-w-3xl">

    <div class="alert-warning mb-4" data-offline-notice hidden role="status">
        <i class="fa-solid fa-wifi mt-0.5"></i>
        <p>Vous êtes hors connexion. Votre brouillon est conservé sur cet appareil ; l'envoi sera possible dès le retour de la connexion.</p>
    </div>

    @if($errors->any())
    <div class="alert-error mb-4" role="alert">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
        <p>Le témoignage n'a pas pu être envoyé. Merci de corriger les champs signalés ci-dessous.</p>
    </div>
    @endif

    <form method="POST" action="{{ route('testimonies.store') }}" enctype="multipart/form-data" id="testimony-form"
          class="card divide-y divide-slate-100"
          data-autosave="testimony-create" data-has-old="{{ session()->hasOldInput() ? '1' : '0' }}"
          data-online-only data-loading-label="Envoi du témoignage…">
        @csrf

        {{-- Contenu --}}
        <section class="space-y-4 p-5 sm:p-6">
            <h2 class="card-title">Contenu</h2>

            <div>
                <span class="form-label">Type de témoignage *</span>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    @foreach($types as $val => [$label, $icon])
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50 has-[:checked]:text-slate-900">
                        <input type="radio" name="type" value="{{ $val }}" @checked($currentType === $val)>
                        <i class="fa-solid {{ $icon }} text-slate-400"></i>{{ $label }}
                    </label>
                    @endforeach
                </div>
                @error('type')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="title" class="form-label">Titre *</label>
                <input id="title" type="text" name="title" value="{{ old('title') }}" required maxlength="200" class="form-input"
                       placeholder="Ex. : Comment Dieu m'a guéri d'une maladie">
                @error('title')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="category" class="form-label">Catégorie *</label>
                <select id="category" name="category" required class="form-input">
                    <option value="">Choisir une catégorie</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->slug }}" @selected(old('category') === $cat->slug)>{{ $cat->name }}</option>
                    @endforeach
                </select>
                @error('category')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div data-type-section="text">
                <span id="body_text_label" class="form-label">Votre témoignage <span data-type-required>*</span></span>
                @include('components.rich-editor', [
                    'name' => 'body_text',
                    'id' => 'body_text',
                    'value' => old('body_text'),
                    'labelId' => 'body_text_label',
                    'placeholder' => 'Racontez comment Dieu a agi dans votre vie…',
                    'requiredMessage' => 'Merci de rédiger votre témoignage.',
                ])
                <p class="form-hint">Vous pouvez mettre des passages en gras ou en italique et ajouter des émojis.</p>
                <p class="form-hint" data-media-summary-hint hidden>Pour un témoignage audio ou vidéo, ce texte est facultatif et sert de résumé.</p>
                @error('body_text')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div data-type-section="audio video">
                <label for="media_file" class="form-label" data-media-label>Fichier média</label>
                <input id="media_file" type="file" name="media_file" accept="audio/*,video/*" class="form-input">
                <p class="form-hint" data-media-hint>Audio : MP3, M4A, WAV. Vidéo : MP4, MOV. 100 Mo maximum.</p>
                @error('media_file')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="cover" class="form-label">Image de couverture (facultatif)</label>
                <input id="cover" type="file" name="cover" accept="image/*" class="form-input">
                <p class="form-hint">JPG ou PNG, 5 Mo maximum.</p>
                @error('cover')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </section>

        {{-- Référence biblique --}}
        <section class="space-y-4 p-5 sm:p-6">
            <h2 class="card-title">Référence biblique (facultatif)</h2>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="md:col-span-2">
                    <label for="bible_verse" class="form-label">Verset</label>
                    <textarea id="bible_verse" name="bible_verse" rows="2" maxlength="500" class="form-input"
                              placeholder="« Car je connais les projets que j'ai formés sur vous… »">{{ old('bible_verse') }}</textarea>
                    @error('bible_verse')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="bible_ref" class="form-label">Référence</label>
                    <input id="bible_ref" type="text" name="bible_ref" value="{{ old('bible_ref') }}" maxlength="100" class="form-input" placeholder="Jérémie 29:11">
                    @error('bible_ref')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        {{-- Diffusion --}}
        <section class="space-y-4 p-5 sm:p-6">
            <h2 class="card-title">Diffusion</h2>
            <div>
                <label for="tags" class="form-label">Mots-clés</label>
                <input id="tags" type="text" name="tags" value="{{ old('tags') }}" class="form-input" placeholder="guérison, miracle, foi">
                <p class="form-hint">Séparez les mots-clés par des virgules.</p>
            </div>
            <div>
                <span class="form-label">Visibilité</span>
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    @foreach($visibilities as $val => $label)
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="radio" name="visibility" value="{{ $val }}" @checked(old('visibility', 'public') === $val)>
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
                <span>Je certifie que ce témoignage est authentique et personnel, et j'accepte qu'il soit partagé sur la plateforme TestiApp après relecture. *</span>
            </label>
            @error('consent_given')<p class="form-error">{{ $message }}</p>@enderror

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500" data-autosave-status>Votre brouillon est enregistré automatiquement sur cet appareil.</p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('home') }}" class="btn-secondary">Annuler</a>
                    <button type="submit" class="btn-primary" data-submit-guard disabled>Soumettre pour relecture</button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('testimony-form');
    const bodyText = document.getElementById('body_text');
    const media = document.getElementById('media_file');
    const MEDIA = {
        audio: { label: 'Fichier audio', accept: 'audio/*', hint: 'MP3, M4A ou WAV, 100 Mo maximum.' },
        video: { label: 'Fichier vidéo', accept: 'video/*', hint: 'MP4 ou MOV, 100 Mo maximum.' },
    };

    function syncType() {
        const type = form.querySelector('input[name="type"]:checked')?.value || 'text';
        form.querySelectorAll('[data-type-section]').forEach(function (el) {
            // Le texte reste visible pour tous les types (résumé facultatif).
            const types = el.dataset.typeSection.split(' ');
            el.hidden = !(types.includes(type) || el.dataset.typeSection === 'text');
        });
        const isText = type === 'text';
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

    form.addEventListener('change', function (e) {
        if (e.target.name === 'type') syncType();
    });
    form.addEventListener('autosave:ready', syncType);
    syncType();
})();
</script>
@endpush

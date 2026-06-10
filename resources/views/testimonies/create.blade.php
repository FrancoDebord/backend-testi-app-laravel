@extends('layouts.app')
@section('title', 'Publier un témoignage')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-7">

            <div class="mb-4">
                <h4 class="fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Publier un témoignage</h4>
                <p class="text-muted small">Partagez comment Dieu a agi dans votre vie. Il sera examiné avant publication.</p>
            </div>

            @if($errors->any())
            <div class="alert alert-danger small">
                @foreach($errors->all() as $e)<p class="mb-0">{{ $e }}</p>@endforeach
            </div>
            @endif

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('testimonies.store') }}" enctype="multipart/form-data" id="testimonyForm">
                        @csrf

                        {{-- Type selector --}}
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Type de témoignage *</label>
                            <div class="d-flex gap-3">
                                @foreach(['text' => ['📝', 'Texte'], 'audio' => ['🎵', 'Audio'], 'video' => ['🎬', 'Vidéo']] as $val => [$emoji, $label])
                                <div class="form-check form-check-inline border rounded-3 px-3 py-2 flex-fill text-center" style="cursor:pointer;" onclick="switchType('{{ $val }}')">
                                    <input class="form-check-input" type="radio" name="type" id="type_{{ $val }}" value="{{ $val }}" {{ old('type', 'text') === $val ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="type_{{ $val }}" style="cursor:pointer;">{{ $emoji }} {{ $label }}</label>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Title --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Titre *</label>
                            <input type="text" name="title" value="{{ old('title') }}" required class="form-control" placeholder="Ex: Comment Dieu m'a guéri d'une maladie incurable...">
                        </div>

                        {{-- Category --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Catégorie *</label>
                            <select name="category_id" required class="form-select">
                                <option value="">-- Choisir une catégorie --</option>
                                @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->icon }} {{ $cat->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Text content --}}
                        <div id="section_text" class="type-section mb-3">
                            <label class="form-label fw-semibold small">Votre témoignage *</label>
                            <textarea name="body_text" rows="8" class="form-control"
                                placeholder="Racontez comment Dieu a agi dans votre vie...">{{ old('body_text') }}</textarea>
                        </div>

                        {{-- Audio section --}}
                        <div id="section_audio" class="type-section mb-3 d-none">
                            <label class="form-label fw-semibold small">Fichier audio</label>
                            <input type="file" name="audio_file" accept="audio/*" class="form-control">
                            <div class="form-text">Format MP3, M4A, WAV — Max 100 Mo</div>
                        </div>

                        {{-- Video section --}}
                        <div id="section_video" class="type-section mb-3 d-none">
                            <label class="form-label fw-semibold small">Fichier vidéo</label>
                            <input type="file" name="video_file" accept="video/*" class="form-control">
                            <div class="form-text">Format MP4, MOV — Max 100 Mo</div>
                        </div>

                        {{-- Cover image --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Image de couverture (optionnel)</label>
                            <input type="file" name="cover_image" accept="image/*" class="form-control">
                        </div>

                        {{-- Bible verse --}}
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-8">
                                <label class="form-label fw-semibold small">Verset biblique (optionnel)</label>
                                <textarea name="bible_verse" rows="2" class="form-control" placeholder="« Car je connais les projets... »">{{ old('bible_verse') }}</textarea>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label fw-semibold small">Référence</label>
                                <input type="text" name="bible_ref" value="{{ old('bible_ref') }}" class="form-control" placeholder="Jérémie 29:11">
                            </div>
                        </div>

                        {{-- Tags --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Tags (séparés par des virgules)</label>
                            <input type="text" name="tags" value="{{ old('tags') }}" class="form-control" placeholder="guérison, miracle, foi">
                        </div>

                        {{-- Visibility --}}
                        <div class="mb-4">
                            <label class="form-label fw-semibold small">Visibilité</label>
                            <div class="d-flex gap-3">
                                @foreach(['public' => ['bi-globe', 'Public'], 'followers' => ['bi-people', 'Abonnés'], 'private' => ['bi-lock', 'Privé']] as $val => [$icon, $label])
                                <div class="form-check border rounded-3 px-3 py-2">
                                    <input class="form-check-input" type="radio" name="visibility" id="vis_{{ $val }}" value="{{ $val }}" {{ old('visibility', 'public') === $val ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="vis_{{ $val }}"><i class="bi {{ $icon }} me-1 text-primary"></i>{{ $label }}</label>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Consent --}}
                        <div class="mb-4 p-3 bg-light rounded-3">
                            <div class="form-check">
                                <input type="checkbox" name="consent_given" id="consent" value="1" required class="form-check-input @error('consent_given') is-invalid @enderror" {{ old('consent_given') ? 'checked' : '' }}>
                                <label for="consent" class="form-check-label small">
                                    J'accepte que mon témoignage soit partagé sur la plateforme TestiApp. Je certifie que ce témoignage est authentique et personnel.
                                </label>
                            </div>
                        </div>

                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary px-5 fw-semibold">
                                <i class="bi bi-send me-2"></i>Soumettre pour révision
                            </button>
                            <a href="{{ route('home') }}" class="btn btn-light">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function switchType(type) {
    document.querySelectorAll('.type-section').forEach(el => el.classList.add('d-none'));
    const section = document.getElementById('section_' + type);
    if (section) section.classList.remove('d-none');
}
// Init on load
switchType(document.querySelector('input[name="type"]:checked')?.value || 'text');
document.querySelectorAll('input[name="type"]').forEach(r => r.addEventListener('change', e => switchType(e.target.value)));
</script>
@endpush
@endsection

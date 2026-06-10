@extends('layouts.app')
@section('title', 'Paramètres du compte')

@section('content')
<div class="container-fluid px-4 py-4">
<div class="row justify-content-center">
<div class="col-12 col-lg-7 col-xl-6">

    <h4 class="fw-bold mb-4">
        <i class="bi bi-gear-fill text-primary me-2"></i>Paramètres du compte
    </h4>

    <form method="POST" action="{{ route('profile.settings.update') }}">
        @csrf @method('PUT')

        {{-- Privacy --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="fw-semibold mb-0"><i class="bi bi-lock me-2"></i>Confidentialité</h6>
            </div>
            <div class="card-body p-0">
                <div class="d-flex align-items-center justify-content-between px-4 py-3 border-bottom">
                    <div>
                        <p class="fw-medium mb-0">Compte privé</p>
                        <p class="text-muted small mb-0">Seuls vos abonnés verront vos témoignages</p>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input type="checkbox" class="form-check-input" role="switch"
                               name="is_private_account" value="1"
                               {{ $settings->is_private_account ? 'checked' : '' }}>
                    </div>
                </div>

                <div class="px-4 py-3">
                    <p class="fw-medium mb-2">Qui peut commenter</p>
                    <div class="d-flex gap-4">
                        @foreach(['everyone' => 'Tout le monde', 'followers' => 'Abonnés', 'nobody' => 'Personne'] as $val => $label)
                        <div class="form-check">
                            <input type="radio" name="comment_permission" value="{{ $val }}"
                                   id="comment_{{ $val }}"
                                   {{ ($settings->comment_permission ?? 'everyone') === $val ? 'checked' : '' }}
                                   class="form-check-input">
                            <label class="form-check-label small" for="comment_{{ $val }}">{{ $label }}</label>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Push notifications --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="fw-semibold mb-0"><i class="bi bi-bell me-2"></i>Notifications push</h6>
            </div>
            <div class="card-body p-0">
                @foreach([
                    ['push_comments', 'Nouveaux commentaires'],
                    ['push_likes', 'Réactions (likes, prières...)'],
                    ['push_prayers', 'Prières'],
                    ['push_approval', 'Approbation de témoignage'],
                    ['push_new_followed', "Nouveaux témoignages d'abonnements"],
                ] as [$key, $label])
                <div class="d-flex align-items-center justify-content-between px-4 py-3 border-bottom">
                    <span class="small">{{ $label }}</span>
                    <div class="form-check form-switch mb-0">
                        <input type="checkbox" class="form-check-input" role="switch"
                               name="{{ $key }}" value="1"
                               {{ ($settings->$key ?? true) ? 'checked' : '' }}>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Appearance --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="fw-semibold mb-0"><i class="bi bi-palette me-2"></i>Apparence</h6>
            </div>
            <div class="card-body px-4 py-3">
                <p class="small fw-medium mb-2">Thème</p>
                <div class="d-flex gap-4">
                    @foreach(['light' => 'Clair', 'dark' => 'Sombre', 'system' => 'Système'] as $val => $label)
                    <div class="form-check">
                        <input type="radio" name="app_theme" value="{{ $val }}"
                               id="theme_{{ $val }}"
                               {{ ($settings->app_theme ?? 'system') === $val ? 'checked' : '' }}
                               class="form-check-input">
                        <label class="form-check-label small" for="theme_{{ $val }}">{{ $label }}</label>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-3 fw-semibold">
            <i class="bi bi-floppy me-1"></i>Enregistrer les paramètres
        </button>
    </form>

</div>
</div>
</div>
@endsection

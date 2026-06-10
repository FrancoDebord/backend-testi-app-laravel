@extends('layouts.app')
@section('title', 'Modifier mon profil')

@section('content')
<div class="container-fluid px-4 py-4">
<div class="row justify-content-center">
<div class="col-12 col-lg-7 col-xl-6">

    <h4 class="fw-bold mb-4">
        <i class="bi bi-pencil-square text-primary me-2"></i>Modifier mon profil
    </h4>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">

            @if(session('success'))
            <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-check-circle-fill"></i>{{ session('success') }}
            </div>
            @endif

            @if($errors->any())
            <div class="alert alert-danger mb-4">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)<li class="small">{{ $error }}</li>@endforeach
                </ul>
            </div>
            @endif

            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                @csrf @method('PUT')

                {{-- Avatar --}}
                <div class="d-flex align-items-center gap-4 mb-4">
                    @if($user->avatar_url)
                        <img src="{{ $user->avatar_url }}" class="rounded-circle object-fit-cover flex-shrink-0"
                             style="width:72px;height:72px;" alt="">
                    @else
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:72px;height:72px;font-size:1.4rem;font-weight:700;">
                            {{ $user->initials }}
                        </div>
                    @endif
                    <div>
                        <label class="form-label small fw-medium mb-1">Photo de profil</label>
                        <input type="file" name="avatar" accept="image/*" class="form-control form-control-sm">
                    </div>
                </div>

                {{-- Display name --}}
                <div class="mb-3">
                    <label class="form-label fw-medium">Nom complet *</label>
                    <input type="text" name="display_name"
                           value="{{ old('display_name', $user->display_name) }}" required
                           class="form-control @error('display_name') is-invalid @enderror"
                           placeholder="Votre nom d'affichage">
                    @error('display_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Country --}}
                <div class="mb-3">
                    <label class="form-label fw-medium">Pays</label>
                    <input type="text" name="country"
                           value="{{ old('country', $user->country) }}" maxlength="100"
                           class="form-control @error('country') is-invalid @enderror"
                           placeholder="ex: Bénin, France, Côte d'Ivoire">
                    @error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Bio --}}
                <div class="mb-4">
                    <label class="form-label fw-medium">Biographie</label>
                    <textarea name="bio" rows="4" maxlength="500"
                              placeholder="Parlez de vous en quelques mots..."
                              class="form-control @error('bio') is-invalid @enderror"
                              style="resize:none;">{{ old('bio', $user->bio) }}</textarea>
                    @error('bio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="row g-3">
                    <div class="col-6">
                        <a href="{{ route('profiles.show', Auth::id()) }}"
                           class="btn btn-outline-secondary w-100">
                            Annuler
                        </a>
                    </div>
                    <div class="col-6">
                        <button type="submit" class="btn btn-primary w-100 fw-semibold">
                            <i class="bi bi-floppy me-1"></i>Enregistrer
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>
</div>
</div>
@endsection

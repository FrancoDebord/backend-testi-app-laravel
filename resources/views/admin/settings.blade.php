@extends('layouts.admin')
@section('title', 'Paramètres')
@section('page-title', "Paramètres de l'application")

@section('content')

<form method="POST" action="{{ route('admin.settings.update') }}">
    @csrf @method('PUT')

    @if($settings->isEmpty())
    <div class="alert alert-secondary text-center py-5">
        <i class="bi bi-gear fs-2 d-block mb-2"></i>
        Aucun paramètre configuré. Lancez le seeder pour initialiser les paramètres par défaut.
    </div>
    @else

    @foreach($settings as $group => $groupSettings)
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="fw-semibold mb-0 text-capitalize">{{ $group }}</h6>
        </div>
        <div class="card-body p-0">
            @foreach($groupSettings as $setting)
            <div class="d-flex align-items-center justify-content-between px-4 py-3 border-bottom">
                <div>
                    <p class="fw-medium small mb-0">{{ $setting->label ?? $setting->key }}</p>
                    <code class="text-muted" style="font-size:.72rem;">{{ $setting->key }}</code>
                </div>
                <div style="min-width:200px;">
                    @if($setting->type === 'boolean')
                    <div class="form-check form-switch d-flex justify-content-end mb-0">
                        <input type="checkbox" class="form-check-input" role="switch"
                               name="{{ $setting->key }}" value="1"
                               {{ $setting->typedValue() ? 'checked' : '' }}>
                    </div>
                    @elseif($setting->type === 'integer')
                    <input type="number" name="{{ $setting->key }}" value="{{ $setting->value }}"
                           class="form-control form-control-sm text-end">
                    @else
                    <input type="text" name="{{ $setting->key }}" value="{{ $setting->value }}"
                           class="form-control form-control-sm">
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach

    <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary px-5">
            <i class="bi bi-floppy me-1"></i>Enregistrer les paramètres
        </button>
    </div>

    @endif
</form>
@endsection

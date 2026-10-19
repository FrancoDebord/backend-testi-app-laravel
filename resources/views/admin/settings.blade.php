@extends('layouts.app')
@section('title', 'Paramètres')
@php
    $header      = 'Paramètres de l’application';
    $subheader   = 'Réglages généraux partagés par le site et l’application mobile.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Administration', 'url' => route('admin.dashboard')],
        ['label' => 'Paramètres'],
    ];
@endphp

@section('content')
{{-- Groupes, ordre, libellés et descriptions : App\Services\SettingsSchema, partagés avec l'application
     (GET /api/v1/admin/settings/schema ─ docs/fonctionnalites/parametres-globaux.md). --}}
@if(empty($groups))
    @include('components.empty-state', [
        'title' => 'Aucun paramètre configuré',
        'text' => 'Lancez le seeder des paramètres pour initialiser les valeurs par défaut.',
    ])
@else
<form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-4xl space-y-6" data-loading-label="Enregistrement des paramètres…">
    @csrf @method('PUT')

    @if($errors->any())
    <div class="alert-error" role="alert">
        <i class="fa-solid fa-circle-exclamation mt-0.5" aria-hidden="true"></i>
        <ul class="space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
    @endif

    @foreach($groups as $group)
    <section class="card">
        <h2 class="card-title border-b border-slate-100 px-5 py-4">{{ $group['label'] }}</h2>
        <div class="divide-y divide-slate-100">
            @foreach($group['settings'] as $setting)
            @php
                $fieldId = 'setting-' . $loop->parent->index . '-' . $loop->index;
                $value = $setting['value'];
                $old = old($setting['key']);
            @endphp
            <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <label for="{{ $fieldId }}" class="text-sm font-medium text-slate-900">{{ $setting['label'] }}</label>
                    @if($setting['description'])
                    <p class="text-xs text-slate-500">{{ $setting['description'] }}</p>
                    @endif
                    <p class="font-mono text-xs break-all text-slate-400">{{ $setting['key'] }}</p>
                </div>
                <div class="sm:w-60 sm:shrink-0 {{ $setting['type'] === 'boolean' ? 'flex sm:justify-end' : '' }}">
                    @if($setting['type'] === 'boolean')
                        @include('components.switch', ['name' => $setting['key'], 'checked' => (bool) $value, 'label' => $setting['label'], 'sendFalse' => true])
                    @elseif($setting['type'] === 'integer')
                        <input id="{{ $fieldId }}" type="number" step="1" name="{{ $setting['key'] }}" value="{{ $old ?? $value }}" class="form-input sm:text-right">
                    @elseif($setting['type'] === 'json')
                        <textarea id="{{ $fieldId }}" name="{{ $setting['key'] }}" rows="3" class="form-input font-mono text-xs">{{ $old ?? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</textarea>
                    @else
                        <input id="{{ $fieldId }}" type="text" name="{{ $setting['key'] }}" value="{{ $old ?? $value }}" class="form-input">
                    @endif
                    @error($setting['key'])<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
            @endforeach
        </div>
    </section>
    @endforeach

    <div class="flex justify-end">
        <button type="submit" class="btn-primary">Enregistrer les paramètres</button>
    </div>
</form>
@endif
@endsection

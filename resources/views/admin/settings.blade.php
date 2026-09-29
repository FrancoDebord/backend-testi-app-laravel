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
@if($settings->isEmpty())
    @include('components.empty-state', [
        'title' => 'Aucun paramètre configuré',
        'text' => 'Lancez le seeder des paramètres pour initialiser les valeurs par défaut.',
    ])
@else
<form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-4xl space-y-6" data-loading-label="Enregistrement des paramètres…">
    @csrf @method('PUT')

    @foreach($settings as $group => $groupSettings)
    <section class="card">
        <h2 class="card-title border-b border-slate-100 px-5 py-4">{{ Str::ucfirst($group) }}</h2>
        <div class="divide-y divide-slate-100">
            @foreach($groupSettings as $setting)
            <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <label for="setting-{{ $loop->parent->index }}-{{ $loop->index }}" class="text-sm font-medium text-slate-900">{{ $setting->label ?? $setting->key }}</label>
                    <p class="font-mono text-xs break-all text-slate-400">{{ $setting->key }}</p>
                </div>
                <div class="sm:w-60 sm:shrink-0 {{ $setting->type === 'boolean' ? 'flex sm:justify-end' : '' }}">
                    @if($setting->type === 'boolean')
                        @include('components.switch', ['name' => $setting->key, 'checked' => (bool) $setting->typedValue(), 'label' => $setting->label ?? $setting->key, 'sendFalse' => true])
                    @elseif($setting->type === 'integer')
                        <input id="setting-{{ $loop->parent->index }}-{{ $loop->index }}" type="number" name="{{ $setting->key }}" value="{{ $setting->value }}" class="form-input sm:text-right">
                    @else
                        <input id="setting-{{ $loop->parent->index }}-{{ $loop->index }}" type="text" name="{{ $setting->key }}" value="{{ $setting->value }}" class="form-input">
                    @endif
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

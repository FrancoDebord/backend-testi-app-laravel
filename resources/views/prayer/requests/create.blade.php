@extends('layouts.app')
@php
    $header      = 'Confier une requête de prière';
    $subheader   = 'Publiée tout de suite. La modération peut la retirer si elle est signalée.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Requêtes de prière', 'url' => route('prayer.requests.index')],
        ['label' => 'Nouvelle'],
    ];
    $visibility = old('visibility', 'public');
    $hints = [
        'public'    => 'Visible de tous, même sans compte.',
        'followers' => 'Visible des personnes qui vous suivent.',
        'private'   => 'Visible de vous seul (un carnet de prière personnel).',
    ];
@endphp
@section('title', $header)

@section('content')
<div class="mx-auto max-w-3xl">
    @if($errors->any())
    <div class="alert-error mb-4" role="alert">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
        <p>La requête n'a pas pu être publiée. Merci de corriger les champs signalés ci-dessous.</p>
    </div>
    @endif

    <form method="POST" action="{{ route('prayer.requests.store') }}" class="card divide-y divide-slate-100" data-loading-label="Publication…">
        @csrf
        @if($event)<input type="hidden" name="event_id" value="{{ $event->id }}">@endif

        <section class="space-y-4 p-5 sm:p-6">
            @if($event)
            <p class="alert-info text-sm"><i class="fa-regular fa-calendar mt-0.5" aria-hidden="true"></i><span>Rattachée à l'événement <strong>{{ $event->title }}</strong> : elle apparaîtra sur sa page.</span></p>
            @endif
            <div>
                <label for="body" class="form-label">Votre requête *</label>
                <textarea id="body" name="body" rows="7" minlength="10" maxlength="2000" required class="form-input"
                          placeholder="Pour quoi souhaitez-vous que l'on prie ?" @error('body') aria-invalid="true" @enderror>{{ old('body') }}</textarea>
                <p class="form-hint">10 à 2 000 caractères. N'indiquez pas d'informations trop personnelles (adresse, téléphone…).</p>
                @error('body')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="space-y-4 p-5 sm:p-6">
            <fieldset>
                <legend class="form-label">Qui peut la voir ?</legend>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    @foreach(\App\Models\PrayerRequest::VISIBILITY_LABELS as $value => $label)
                    <label class="flex cursor-pointer flex-col gap-1 rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50 has-[:checked]:text-slate-900">
                        <span class="flex items-center gap-2 font-medium"><input type="radio" name="visibility" value="{{ $value }}" @checked($visibility === $value)>{{ $label }}</span>
                        <span class="text-xs text-slate-500">{{ $hints[$value] }}</span>
                    </label>
                    @endforeach
                </div>
                @error('visibility')<p class="form-error">{{ $message }}</p>@enderror
            </fieldset>

            <label class="flex items-start gap-3 text-sm text-slate-700">
                <input type="checkbox" name="is_anonymous" value="1" class="mt-1 rounded" @checked(old('is_anonymous'))>
                <span><span class="font-medium text-slate-900">Publier anonymement</span><br>
                <span class="text-xs text-slate-500">Votre nom est masqué pour les autres. Il reste visible de vous et de l'équipe de modération.</span></span>
            </label>
        </section>

        <div class="flex flex-col-reverse gap-2 p-5 sm:flex-row sm:justify-end sm:p-6">
            <a href="{{ $event ? route('events.show', $event->id) : route('prayer.requests.index') }}" class="btn-secondary">Annuler</a>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>Publier ma requête</button>
        </div>
    </form>
</div>
@endsection

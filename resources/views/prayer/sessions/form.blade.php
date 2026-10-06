@extends('layouts.app')
@php
    $editing     = $session->exists;
    $header      = $editing ? 'Modifier la session' : 'Programmer une session de prière';
    $subheader   = "À l'heure prévue, vous ouvrez la salle (caméra ou micro) ; les inscrits rejoignent, commentent et peuvent prendre la parole à tour de rôle.";
    $breadcrumbs = array_values(array_filter([
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Sessions de prière', 'url' => route('prayer.sessions.index')],
        $editing ? ['label' => Str::limit($session->title, 40), 'url' => route('prayer.sessions.show', $session->id)] : null,
        ['label' => $editing ? 'Modifier' : 'Nouvelle'],
    ]));
    $startsAt   = $session->starts_at ?? now()->addDay()->setTime(20, 0);
    $visibility = old('visibility', $session->visibility ?? 'public');
    $topicsText = old('topics_text', implode("\n", $session->topics ?? []));
@endphp
@section('title', $header)

@section('content')
<div class="mx-auto max-w-3xl">
    @if($errors->any())
    <div class="alert-error mb-4" role="alert">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
        <p>La session n'a pas pu être enregistrée. Merci de corriger les champs signalés ci-dessous.</p>
    </div>
    @endif

    <form method="POST" action="{{ $editing ? route('prayer.sessions.update', $session->id) : route('prayer.sessions.store') }}"
          class="card divide-y divide-slate-100" data-loading-label="Enregistrement…">
        @csrf
        @if($editing) @method('PUT') @endif
        @if($event)<input type="hidden" name="event_id" value="{{ $event->id }}">@endif

        <section class="space-y-4 p-5 sm:p-6">
            <h2 class="card-title">La session</h2>
            @if($event)
            <p class="alert-info text-sm"><i class="fa-regular fa-calendar mt-0.5" aria-hidden="true"></i><span>Session de l'événement <strong>{{ $event->title }}</strong>.</span></p>
            @endif
            <div>
                <label for="title" class="form-label">Titre *</label>
                <input id="title" type="text" name="title" value="{{ old('title', $session->title) }}" maxlength="150" required class="form-input" placeholder="Ex. : Veillée de prière pour les familles" @error('title') aria-invalid="true" @enderror>
                @error('title')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="description" class="form-label">Présentation</label>
                <textarea id="description" name="description" rows="4" maxlength="3000" class="form-input" @error('description') aria-invalid="true" @enderror>{{ old('description', $session->description) }}</textarea>
                @error('description')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="topics_text" class="form-label">Sujets de prière</label>
                <textarea id="topics_text" name="topics_text" rows="4" class="form-input" aria-describedby="topics-hint" placeholder="Un sujet par ligne">{{ $topicsText }}</textarea>
                <p id="topics-hint" class="form-hint">Un sujet par ligne, 10 au plus.</p>
                @error('topics')<p class="form-error">{{ $message }}</p>@enderror
                @error('topics.*')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="space-y-4 p-5 sm:p-6">
            <h2 class="card-title">Quand ?</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label for="date" class="form-label">Date *</label>
                    <input id="date" type="date" name="date" value="{{ old('date', $startsAt->toDateString()) }}" required class="form-input" @error('date') aria-invalid="true" @enderror>
                    @error('date')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="time" class="form-label">Heure *</label>
                    <input id="time" type="time" name="time" value="{{ old('time', $startsAt->format('H:i')) }}" required class="form-input" @error('time') aria-invalid="true" @enderror>
                    @error('time')<p class="form-error">{{ $message }}</p>@enderror
                    @error('starts_at')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="duration_minutes" class="form-label">Durée</label>
                    <select id="duration_minutes" name="duration_minutes" class="form-input">
                        @foreach([15 => '15 min', 30 => '30 min', 45 => '45 min', 60 => '1 h', 90 => '1 h 30', 120 => '2 h', 180 => '3 h', 240 => '4 h', 360 => '6 h', 480 => '8 h'] as $value => $label)
                        <option value="{{ $value }}" @selected((int) old('duration_minutes', $session->duration_minutes ?? 60) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('duration_minutes')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="form-hint">Heure de {{ config('app.timezone') }}. Les inscrits reçoivent un rappel 15 minutes avant.</p>

            <fieldset>
                <legend class="form-label">Qui peut la voir ?</legend>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach(\App\Models\PrayerSession::VISIBILITY_LABELS as $value => $label)
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50 has-[:checked]:text-slate-900">
                        <input type="radio" name="visibility" value="{{ $value }}" @checked($visibility === $value)>{{ $label }}
                    </label>
                    @endforeach
                </div>
            </fieldset>
        </section>

        <div class="flex flex-col-reverse gap-2 p-5 sm:flex-row sm:justify-end sm:p-6">
            <a href="{{ $editing ? route('prayer.sessions.show', $session->id) : route('prayer.sessions.index') }}" class="btn-secondary">Annuler</a>
            <button type="submit" class="btn-primary">{{ $editing ? 'Enregistrer' : 'Programmer la session' }}</button>
        </div>
    </form>
</div>
@endsection

@extends('layouts.app')
@php
    $editing     = $prophecy->exists;
    $header      = $editing ? 'Modifier la parole' : 'Garder une parole prophétique';
    $subheader   = 'Visible de vous seul. Elle ne sera montrée que si vous la publiez avec le témoignage de son accomplissement.';
    $breadcrumbs = array_values(array_filter([
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Carnet privé', 'url' => route('journal.index')],
        ['label' => 'Paroles prophétiques', 'url' => route('prophecies.index')],
        $editing ? ['label' => Str::limit($prophecy->title ?: 'La parole', 40), 'url' => route('prophecies.show', $prophecy->id)] : null,
        ['label' => $editing ? 'Modifier' : 'Nouvelle'],
    ]));
    $weekdays    = \App\Http\Controllers\Web\ProphecyController::WEEKDAYS;
    $frequency   = old('reminder_frequency', $prophecy->reminder_frequency);
    $audioLength = $prophecy->audio_duration ? sprintf('%d:%02d', intdiv($prophecy->audio_duration, 60), $prophecy->audio_duration % 60) : null;
@endphp
@section('title', $header)

@section('content')
<div class="mx-auto max-w-3xl">
    @if($errors->any())
    <div class="alert-error mb-4" role="alert">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
        <p>La parole n'a pas pu être enregistrée. Merci de corriger les champs signalés ci-dessous.</p>
    </div>
    @endif

    <form method="POST" action="{{ $editing ? route('prophecies.update', $prophecy->id) : route('prophecies.store') }}" enctype="multipart/form-data"
          id="prophecy-form" class="card divide-y divide-slate-100" data-loading-label="Enregistrement de la parole…">
        @csrf
        @if($editing) @method('PUT') @endif

        {{-- La parole --}}
        <section class="space-y-4 p-5 sm:p-6">
            <h2 class="card-title">La parole</h2>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="received_on" class="form-label">Reçue le *</label>
                    <input id="received_on" type="date" name="received_on" value="{{ old('received_on', $prophecy->received_on?->toDateString()) }}" required class="form-input" @error('received_on') aria-invalid="true" @enderror>
                    @error('received_on')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="given_by" class="form-label">Donnée par</label>
                    <input id="given_by" type="text" name="given_by" value="{{ old('given_by', $prophecy->given_by) }}" maxlength="150" class="form-input" placeholder="Ex. : Pasteur Jean, lors d'un rêve…" @error('given_by') aria-invalid="true" @enderror>
                    @error('given_by')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="title" class="form-label">Titre</label>
                <input id="title" type="text" name="title" value="{{ old('title', $prophecy->title) }}" maxlength="150" class="form-input" placeholder="Ex. : Une porte ouverte pour mon travail" @error('title') aria-invalid="true" @enderror>
                @error('title')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="body_text" class="form-label">Texte de la parole</label>
                <textarea id="body_text" name="body_text" rows="7" maxlength="20000" class="form-input" aria-describedby="body_text-hint"
                          placeholder="Écrivez la parole telle que vous l'avez reçue…" @error('body_text') aria-invalid="true" @enderror>{{ old('body_text', $prophecy->body_text) }}</textarea>
                <p id="body_text-hint" class="form-hint">Écrivez la parole, ajoutez un enregistrement audio, ou les deux.</p>
                @error('body_text')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="audio" class="form-label">{{ $prophecy->audio_url ? "Remplacer l'enregistrement" : 'Enregistrement audio' }}</label>
                @if($prophecy->audio_url)
                <div class="mb-3 rounded-lg border border-slate-200 p-3">
                    <p class="mb-1 text-xs text-slate-500">Enregistrement actuel @if($audioLength)<span class="tabular-nums">({{ $audioLength }})</span>@endif</p>
                    <audio controls preload="none" src="{{ $prophecy->audio_url }}" class="w-full" aria-label="Écouter l'enregistrement actuel"></audio>
                    <label class="mt-2 flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="remove_audio" value="1" class="rounded" @checked(old('remove_audio'))>Retirer l'enregistrement
                    </label>
                </div>
                @endif
                <input id="audio" type="file" name="audio" accept="audio/*" class="form-input" aria-describedby="audio-hint" @error('audio') aria-invalid="true" @enderror>
                <input type="hidden" name="audio_duration" value="" data-audio-duration>
                <p id="audio-hint" class="form-hint">MP3, M4A, WAV ou OGG, 20 Mo maximum.</p>
                @error('audio')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div class="sm:w-1/2">
                <label for="due_on" class="form-label">Échéance annoncée</label>
                <input id="due_on" type="date" name="due_on" value="{{ old('due_on', $prophecy->due_on?->toDateString()) }}" class="form-input" aria-describedby="due_on-hint" @error('due_on') aria-invalid="true" @enderror>
                <p id="due_on-hint" class="form-hint">Facultatif : si une date a été donnée pour l'accomplissement.</p>
                @error('due_on')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </section>

        {{-- Rappel de prière (programmé par l'application sur le téléphone) --}}
        <section class="space-y-4 p-5 sm:p-6" aria-labelledby="reminder-title">
            <div>
                <h2 id="reminder-title" class="card-title">Rappel de prière</h2>
                <p class="mt-1 text-sm text-slate-500">Le rappel est envoyé par l'application TestiApp sur votre téléphone, à l'heure du téléphone. Il s'arrête quand la parole est accomplie.</p>
            </div>
            <fieldset>
                <legend class="sr-only">Fréquence du rappel</legend>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    @foreach(['' => 'Aucun rappel', 'daily' => 'Chaque jour', 'weekly' => 'Chaque semaine'] as $value => $label)
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50 has-[:checked]:text-slate-900">
                        <input type="radio" name="reminder_frequency" value="{{ $value }}" @checked((string) $frequency === $value)>{{ $label }}
                    </label>
                    @endforeach
                </div>
                @error('reminder_frequency')<p class="form-error">{{ $message }}</p>@enderror
            </fieldset>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2" data-reminder-fields>
                <div data-reminder-weekday>
                    <label for="reminder_weekday" class="form-label">Jour *</label>
                    <select id="reminder_weekday" name="reminder_weekday" class="form-input" @error('reminder_weekday') aria-invalid="true" @enderror>
                        @foreach($weekdays as $value => $label)
                        <option value="{{ $value }}" @selected((int) old('reminder_weekday', $prophecy->reminder_weekday ?? 7) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reminder_weekday')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="reminder_time" class="form-label">Heure *</label>
                    <input id="reminder_time" type="time" name="reminder_time" value="{{ old('reminder_time', $prophecy->reminder_time ?? '07:00') }}" class="form-input" @error('reminder_time') aria-invalid="true" @enderror>
                    @error('reminder_time')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-2 p-5 sm:flex-row sm:justify-end sm:p-6">
            <a href="{{ $editing ? route('prophecies.show', $prophecy->id) : route('prophecies.index') }}" class="btn-secondary">Annuler</a>
            <button type="submit" class="btn-primary">{{ $editing ? 'Enregistrer' : 'Garder dans mon carnet' }}</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('prophecy-form');
    const fields = form.querySelector('[data-reminder-fields]');
    const weekday = form.querySelector('[data-reminder-weekday]');
    const time = document.getElementById('reminder_time');

    // Jour et heure : seulement avec un rappel ; le jour, seulement chaque semaine.
    function syncReminder() {
        const value = form.querySelector('input[name="reminder_frequency"]:checked')?.value || '';
        fields.hidden = value === '';
        weekday.hidden = value !== 'weekly';
        time.required = value !== '';
    }
    form.addEventListener('change', function (e) {
        if (e.target.name === 'reminder_frequency') syncReminder();
    });
    syncReminder();

    // Durée de l'enregistrement choisi, lue par le navigateur.
    const audio = document.getElementById('audio');
    const duration = form.querySelector('[data-audio-duration]');
    audio.addEventListener('change', function () {
        duration.value = '';
        const file = audio.files[0];
        if (!file) return;
        const url = URL.createObjectURL(file);
        const probe = new Audio();
        probe.preload = 'metadata';
        probe.addEventListener('loadedmetadata', function () {
            if (isFinite(probe.duration)) duration.value = Math.round(probe.duration);
            URL.revokeObjectURL(url);
        });
        probe.src = url;
    });
})();
</script>
@endpush

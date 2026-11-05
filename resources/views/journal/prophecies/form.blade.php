@extends('layouts.app')
@php
    $editing     = $prophecy->exists;
    $header      = $editing ? __('prophecies.form.edit_title') : __('prophecies.form.create_title');
    $subheader   = __('prophecies.form.subheader');
    $breadcrumbs = array_values(array_filter([
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.journal'), 'url' => route('journal.index')],
        ['label' => __('layout.nav.prophecies'), 'url' => route('prophecies.index')],
        $editing ? ['label' => Str::limit($prophecy->title ?: __('prophecies.word'), 40), 'url' => route('prophecies.show', $prophecy->id)] : null,
        ['label' => $editing ? __('prophecies.form.breadcrumb_edit') : __('prophecies.form.breadcrumb_new')],
    ]));
    $weekdays    = \App\Http\Controllers\Web\ProphecyController::weekdays();
    $frequency   = old('reminder_frequency', $prophecy->reminder_frequency);
    $audioLength = $prophecy->audio_duration ? sprintf('%d:%02d', intdiv($prophecy->audio_duration, 60), $prophecy->audio_duration % 60) : null;
@endphp
@section('title', $header)

@section('content')
<div class="mx-auto max-w-3xl">
    @if($errors->any())
    <div class="alert-error mb-4" role="alert">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
        <p>{{ __('prophecies.form.errors') }}</p>
    </div>
    @endif

    <form method="POST" action="{{ $editing ? route('prophecies.update', $prophecy->id) : route('prophecies.store') }}" enctype="multipart/form-data"
          id="prophecy-form" class="card divide-y divide-slate-100" data-loading-label="{{ __('prophecies.form.saving') }}">
        @csrf
        @if($editing) @method('PUT') @endif

        {{-- La parole --}}
        <section class="space-y-4 p-5 sm:p-6">
            <h2 class="card-title">{{ __('prophecies.word') }}</h2>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-2">
                    <div class="min-w-0">
                        <label for="received_on" class="form-label">{{ __('prophecies.labels.received') }} *</label>
                        <input id="received_on" type="date" name="received_on" value="{{ old('received_on', $prophecy->received_on?->toDateString()) }}" required class="form-input" @error('received_on') aria-invalid="true" @enderror>
                    </div>
                    <div>
                        <label for="received_time" class="form-label">{{ __('prophecies.form.at') }}</label>
                        {{-- Heure de réception (facultative) ; l'heure actuelle pour une nouvelle parole. --}}
                        <input id="received_time" type="time" name="received_time" value="{{ old('received_time', $prophecy->exists ? $prophecy->received_time : now()->format('H:i')) }}" class="form-input" @error('received_time') aria-invalid="true" @enderror>
                    </div>
                    @error('received_on')<p class="form-error col-span-2">{{ $message }}</p>@enderror
                    @error('received_time')<p class="form-error col-span-2">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="given_by" class="form-label">{{ __('prophecies.labels.given_by') }}</label>
                    <input id="given_by" type="text" name="given_by" value="{{ old('given_by', $prophecy->given_by) }}" maxlength="150" class="form-input" placeholder="{{ __('prophecies.form.given_by_placeholder') }}" @error('given_by') aria-invalid="true" @enderror>
                    @error('given_by')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="title" class="form-label">{{ __('prophecies.form.title') }}</label>
                <input id="title" type="text" name="title" value="{{ old('title', $prophecy->title) }}" maxlength="150" class="form-input" placeholder="{{ __('prophecies.form.title_placeholder') }}" @error('title') aria-invalid="true" @enderror>
                @error('title')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="body_text" class="form-label">{{ __('prophecies.form.body') }}</label>
                <textarea id="body_text" name="body_text" rows="7" maxlength="20000" class="form-input" aria-describedby="body_text-hint"
                          placeholder="{{ __('prophecies.form.body_placeholder') }}" @error('body_text') aria-invalid="true" @enderror>{{ old('body_text', $prophecy->body_text) }}</textarea>
                <p id="body_text-hint" class="form-hint">{{ __('prophecies.form.body_hint') }}</p>
                @error('body_text')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="audio" class="form-label">{{ $prophecy->audio_url ? __('prophecies.form.audio_replace') : __('prophecies.form.audio') }}</label>
                @if($prophecy->audio_url)
                <div class="mb-3 rounded-lg border border-slate-200 p-3">
                    <p class="mb-1 text-xs text-slate-500">{{ __('prophecies.form.audio_current') }} @if($audioLength)<span class="tabular-nums">({{ $audioLength }})</span>@endif</p>
                    <audio controls preload="none" src="{{ $prophecy->audio_url }}" class="w-full" aria-label="{{ __('prophecies.form.audio_current_listen') }}"></audio>
                    <label class="mt-2 flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="remove_audio" value="1" class="rounded" @checked(old('remove_audio'))>{{ __('prophecies.form.audio_remove') }}
                    </label>
                </div>
                @endif
                <input id="audio" type="file" name="audio" accept="audio/*" class="form-input" aria-describedby="audio-hint" @error('audio') aria-invalid="true" @enderror>
                <input type="hidden" name="audio_duration" value="" data-audio-duration>
                <p id="audio-hint" class="form-hint">{{ __('prophecies.form.audio_hint') }}</p>
                @error('audio')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            <div class="sm:w-1/2">
                <label for="due_on" class="form-label">{{ __('prophecies.labels.due') }}</label>
                <input id="due_on" type="date" name="due_on" value="{{ old('due_on', $prophecy->due_on?->toDateString()) }}" class="form-input" aria-describedby="due_on-hint" @error('due_on') aria-invalid="true" @enderror>
                <p id="due_on-hint" class="form-hint">{{ __('prophecies.form.due_hint') }}</p>
                @error('due_on')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </section>

        {{-- Rappel de prière (programmé par l'application sur le téléphone) --}}
        <section class="space-y-4 p-5 sm:p-6" aria-labelledby="reminder-title">
            <div>
                <h2 id="reminder-title" class="card-title">{{ __('prophecies.reminder.title') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('prophecies.form.reminder_help') }}</p>
            </div>
            <fieldset>
                <legend class="sr-only">{{ __('prophecies.form.reminder_frequency') }}</legend>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    @foreach(['' => __('prophecies.form.reminder_none'), 'daily' => __('prophecies.form.reminder_daily'), 'weekly' => __('prophecies.form.reminder_weekly')] as $value => $label)
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50 has-[:checked]:text-slate-900">
                        <input type="radio" name="reminder_frequency" value="{{ $value }}" @checked((string) $frequency === $value)>{{ $label }}
                    </label>
                    @endforeach
                </div>
                @error('reminder_frequency')<p class="form-error">{{ $message }}</p>@enderror
            </fieldset>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2" data-reminder-fields>
                <div data-reminder-weekday>
                    <label for="reminder_weekday" class="form-label">{{ __('prophecies.form.day') }} *</label>
                    <select id="reminder_weekday" name="reminder_weekday" class="form-input" @error('reminder_weekday') aria-invalid="true" @enderror>
                        @foreach($weekdays as $value => $label)
                        <option value="{{ $value }}" @selected((int) old('reminder_weekday', $prophecy->reminder_weekday ?? 7) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('reminder_weekday')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="reminder_time" class="form-label">{{ __('prophecies.form.time') }} *</label>
                    <input id="reminder_time" type="time" name="reminder_time" value="{{ old('reminder_time', $prophecy->reminder_time ?? '07:00') }}" class="form-input" @error('reminder_time') aria-invalid="true" @enderror>
                    @error('reminder_time')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-2 p-5 sm:flex-row sm:justify-end sm:p-6">
            <a href="{{ $editing ? route('prophecies.show', $prophecy->id) : route('prophecies.index') }}" class="btn-secondary">{{ __('prophecies.form.cancel') }}</a>
            <button type="submit" class="btn-primary">{{ $editing ? __('prophecies.form.save') : __('prophecies.form.keep') }}</button>
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

@extends('layouts.app')
@php
    $editing     = $session->exists;
    $header      = $editing ? __('prayer.sessions.form.header_edit') : __('prayer.sessions.form.header_create');
    $subheader   = __('prayer.sessions.form.subheader');
    $breadcrumbs = array_values(array_filter([
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.prayer_sessions'), 'url' => route('prayer.sessions.index')],
        $editing ? ['label' => Str::limit($session->title, 40), 'url' => route('prayer.sessions.show', $session->id)] : null,
        ['label' => $editing ? __('prayer.common.edit') : __('prayer.common.new')],
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
        <p>{{ __('prayer.sessions.form.errors') }}</p>
    </div>
    @endif

    <form method="POST" action="{{ $editing ? route('prayer.sessions.update', $session->id) : route('prayer.sessions.store') }}"
          class="card divide-y divide-slate-100" data-loading-label="{{ __('prayer.common.saving') }}">
        @csrf
        @if($editing) @method('PUT') @endif
        @if($event)<input type="hidden" name="event_id" value="{{ $event->id }}">@endif

        <section class="space-y-4 p-5 sm:p-6">
            <h2 class="card-title">{{ __('prayer.sessions.form.section') }}</h2>
            @if($event)
            <p class="alert-info text-sm"><i class="fa-regular fa-calendar mt-0.5" aria-hidden="true"></i><span>{!! __('prayer.sessions.form.event', ['title' => '<strong>' . e($event->title) . '</strong>']) !!}</span></p>
            @endif
            <div>
                <label for="title" class="form-label">{{ __('prayer.sessions.form.title') }}</label>
                <input id="title" type="text" name="title" value="{{ old('title', $session->title) }}" maxlength="150" required class="form-input" placeholder="{{ __('prayer.sessions.form.title_placeholder') }}" @error('title') aria-invalid="true" @enderror>
                @error('title')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="description" class="form-label">{{ __('prayer.sessions.form.description') }}</label>
                <textarea id="description" name="description" rows="4" maxlength="3000" class="form-input" @error('description') aria-invalid="true" @enderror>{{ old('description', $session->description) }}</textarea>
                @error('description')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="topics_text" class="form-label">{{ __('prayer.sessions.form.topics') }}</label>
                <textarea id="topics_text" name="topics_text" rows="4" class="form-input" aria-describedby="topics-hint" placeholder="{{ __('prayer.sessions.form.topics_placeholder') }}">{{ $topicsText }}</textarea>
                <p id="topics-hint" class="form-hint">{{ __('prayer.sessions.form.topics_hint') }}</p>
                @error('topics')<p class="form-error">{{ $message }}</p>@enderror
                @error('topics.*')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="space-y-4 p-5 sm:p-6">
            <h2 class="card-title">{{ __('prayer.sessions.form.when') }}</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label for="date" class="form-label">{{ __('prayer.sessions.form.date') }}</label>
                    <input id="date" type="date" name="date" value="{{ old('date', $startsAt->toDateString()) }}" required class="form-input" @error('date') aria-invalid="true" @enderror>
                    @error('date')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="time" class="form-label">{{ __('prayer.sessions.form.time') }}</label>
                    <input id="time" type="time" name="time" value="{{ old('time', $startsAt->format('H:i')) }}" required class="form-input" @error('time') aria-invalid="true" @enderror>
                    @error('time')<p class="form-error">{{ $message }}</p>@enderror
                    @error('starts_at')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="duration_minutes" class="form-label">{{ __('prayer.sessions.form.duration') }}</label>
                    <select id="duration_minutes" name="duration_minutes" class="form-input">
                        @foreach([15 => __('prayer.sessions.form.minutes', ['count' => 15]), 30 => __('prayer.sessions.form.minutes', ['count' => 30]), 45 => __('prayer.sessions.form.minutes', ['count' => 45]), 60 => __('prayer.sessions.form.hours', ['count' => 1]), 90 => __('prayer.sessions.form.hour_half'), 120 => __('prayer.sessions.form.hours', ['count' => 2]), 180 => __('prayer.sessions.form.hours', ['count' => 3]), 240 => __('prayer.sessions.form.hours', ['count' => 4]), 360 => __('prayer.sessions.form.hours', ['count' => 6]), 480 => __('prayer.sessions.form.hours', ['count' => 8])] as $value => $label)
                        <option value="{{ $value }}" @selected((int) old('duration_minutes', $session->duration_minutes ?? 60) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('duration_minutes')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="form-hint">{{ __('prayer.sessions.form.timezone_hint', ['timezone' => config('app.timezone')]) }}</p>

            <fieldset>
                <legend class="form-label">{{ __('prayer.sessions.form.visibility') }}</legend>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach(\App\Models\PrayerSession::visibilityLabels() as $value => $label)
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50 has-[:checked]:text-slate-900">
                        <input type="radio" name="visibility" value="{{ $value }}" @checked($visibility === $value)>{{ $label }}
                    </label>
                    @endforeach
                </div>
            </fieldset>
        </section>

        <div class="flex flex-col-reverse gap-2 p-5 sm:flex-row sm:justify-end sm:p-6">
            <a href="{{ $editing ? route('prayer.sessions.show', $session->id) : route('prayer.sessions.index') }}" class="btn-secondary">{{ __('prayer.common.cancel') }}</a>
            <button type="submit" class="btn-primary">{{ $editing ? __('prayer.common.save') : __('prayer.sessions.form.submit_create') }}</button>
        </div>
    </form>
</div>
@endsection

@extends('layouts.app')
@php
    $header      = __('prayer.requests.create.header');
    $subheader   = __('prayer.requests.create.subheader');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.prayer_requests'), 'url' => route('prayer.requests.index')],
        ['label' => __('prayer.common.new')],
    ];
    $visibility = old('visibility', 'public');
    $hints = [
        'public'    => __('prayer.requests.create.hint_public'),
        'followers' => __('prayer.requests.create.hint_followers'),
        'private'   => __('prayer.requests.create.hint_private'),
    ];
@endphp
@section('title', $header)

@section('content')
<div class="mx-auto max-w-3xl">
    @if($errors->any())
    <div class="alert-error mb-4" role="alert">
        <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
        <p>{{ __('prayer.requests.create.errors') }}</p>
    </div>
    @endif

    <form method="POST" action="{{ route('prayer.requests.store') }}" class="card divide-y divide-slate-100" data-loading-label="{{ __('prayer.common.publishing') }}">
        @csrf
        @if($event)<input type="hidden" name="event_id" value="{{ $event->id }}">@endif
        @if($testimony)<input type="hidden" name="source_testimony_id" value="{{ $testimony->id }}">@endif

        <section class="space-y-4 p-5 sm:p-6">
            @if($event)
            <p class="alert-info text-sm"><i class="fa-regular fa-calendar mt-0.5" aria-hidden="true"></i><span>{!! __('prayer.requests.create.linked_event', ['title' => '<strong>' . e($event->title) . '</strong>']) !!}</span></p>
            @endif
            @if($testimony)
            <p class="alert-info text-sm"><i class="fa-solid fa-book-open mt-0.5" aria-hidden="true"></i><span>{!! __('prayer.requests.create.linked_testimony', ['title' => '<strong class="break-words">' . e($testimony->title) . '</strong>', 'name' => e($testimony->user?->display_name)]) !!}</span></p>
            @endif
            <div>
                <label for="body" class="form-label">{{ __('prayer.requests.create.body') }}</label>
                <textarea id="body" name="body" rows="7" minlength="10" maxlength="2000" required class="form-input"
                          placeholder="{{ __('prayer.requests.create.body_placeholder') }}" data-mention-input @error('body') aria-invalid="true" @enderror>{{ \App\Services\Mentions::plain(old('body')) }}</textarea>
                @include('components.mention-input')
                <p class="form-hint">{{ __('prayer.requests.create.body_hint') }}</p>
                @error('body')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="space-y-4 p-5 sm:p-6">
            <fieldset>
                <legend class="form-label">{{ __('prayer.requests.create.visibility') }}</legend>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    @foreach(\App\Models\PrayerRequest::visibilityLabels() as $value => $label)
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
                <span><span class="font-medium text-slate-900">{{ __('prayer.requests.create.anonymous') }}</span><br>
                <span class="text-xs text-slate-500">{{ __('prayer.requests.create.anonymous_hint') }}</span></span>
            </label>
        </section>

        <div class="flex flex-col-reverse gap-2 p-5 sm:flex-row sm:justify-end sm:p-6">
            <a href="{{ $event ? route('events.show', $event->id) : ($testimony ? route('testimonies.show', $testimony->id) : route('prayer.requests.index')) }}" class="btn-secondary">{{ __('prayer.common.cancel') }}</a>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>{{ __('prayer.requests.create.submit') }}</button>
        </div>
    </form>
</div>
@endsection

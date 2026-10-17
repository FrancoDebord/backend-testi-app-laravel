@extends('layouts.app')
@section('title', __('conversion.edit_title'))
@php
    $header      = __('conversion.edit_title');
    $subheader   = __('conversion.edit_subtitle');
    $breadcrumbs = [
        ['label' => __('conversion.section_title'), 'url' => route('testimonies.conversions')],
        ['label' => $testimony->title, 'url' => route('testimonies.show', $testimony->id)],
        ['label' => __('conversion.edit')],
    ];
    $details = $testimony->conversionDetails() ?? [];
@endphp

@section('content')
<form method="POST" action="{{ route('testimonies.conversion.update') }}" class="card mx-auto max-w-3xl divide-y divide-slate-100"
      data-loading-label="{{ __('conversion.saving') }}">
    @csrf
    @method('PUT')
    <section class="space-y-4 p-5 sm:p-6">
        <p><span class="badge-yellow"><i class="fa-solid fa-dove" aria-hidden="true"></i>{{ __('conversion.badge') }}</span></p>
        @error('kind')<p class="form-error">{{ $message }}</p>@enderror

        <div>
            <label for="title" class="form-label">{{ __('conversion.title_label') }} *</label>
            <input id="title" type="text" name="title" value="{{ old('title', $testimony->title) }}" required maxlength="200" class="form-input">
            @error('title')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="body_text" class="form-label">{{ __('conversion.body_label') }} @if($testimony->type->value === 'text')*@endif</label>
            <textarea id="body_text" name="body_text" rows="10" class="form-input" @required($testimony->type->value === 'text')>{{ old('body_text', \App\Services\Mentions::plain($testimony->body_text)) }}</textarea>
            @error('body_text')<p class="form-error">{{ $message }}</p>@enderror
        </div>
    </section>

    <section class="space-y-4 p-5 sm:p-6">
        <h2 class="card-title">{{ __('conversion.details') }}</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="conversion_year" class="form-label">{{ __('conversion.year') }}</label>
                <input id="conversion_year" type="number" name="conversion_year" value="{{ old('conversion_year', $details['year'] ?? null) }}"
                       min="{{ \App\Services\ConversionTestimonies::MIN_YEAR }}" max="{{ now()->year }}" inputmode="numeric" class="form-input"
                       placeholder="{{ __('conversion.year_placeholder') }}">
                @error('conversion_year')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="conversion_place" class="form-label">{{ __('conversion.place') }}</label>
                <input id="conversion_place" type="text" name="conversion_place" value="{{ old('conversion_place', $details['place'] ?? null) }}" maxlength="150"
                       class="form-input" placeholder="{{ __('conversion.place_placeholder') }}">
                @error('conversion_place')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="conversion_verse" class="form-label">{{ __('conversion.verse') }}</label>
                <input id="conversion_verse" type="text" name="conversion_verse" value="{{ old('conversion_verse', $details['verse'] ?? null) }}" maxlength="100"
                       class="form-input" placeholder="{{ __('conversion.verse_placeholder') }}">
                @error('conversion_verse')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    <div class="flex flex-wrap justify-end gap-2 p-5 sm:p-6">
        <a href="{{ route('testimonies.show', $testimony->id) }}" class="btn-secondary">{{ __('layout.confirm.cancel') }}</a>
        <button type="submit" class="btn-primary">{{ __('conversion.save') }}</button>
    </div>
</form>
@endsection

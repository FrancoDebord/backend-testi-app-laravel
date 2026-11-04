@extends('layouts.app')
@section('title', $testimony->conversionLabel('edit_title'))
@php
    $header      = $testimony->conversionLabel('edit_title');
    $subheader   = $testimony->conversionLabel('edit_subtitle');
    $breadcrumbs = [
        ['label' => $testimony->conversionLabel('section_title'), 'url' => route('testimonies.conversions', $testimony->isMinistryGenesis() ? ['onglet' => 'ministeres'] : [])],
        ['label' => $testimony->title, 'url' => route('testimonies.show', $testimony->id)],
        ['label' => $testimony->conversionLabel('edit')],
    ];
    $details = $testimony->conversionDetails() ?? [];
@endphp

@section('content')
<form method="POST" action="{{ route('testimonies.conversion.update') }}" class="card mx-auto max-w-3xl divide-y divide-slate-100"
      data-loading-label="{{ $testimony->conversionLabel('saving') }}">
    @csrf
    @method('PUT')
    <section class="space-y-4 p-5 sm:p-6">
        <p><span class="badge-yellow"><i class="{{ $testimony->conversionIcon() }}" aria-hidden="true"></i>{{ $testimony->conversionLabel('badge') }}</span></p>
        @error('kind')<p class="form-error">{{ $message }}</p>@enderror

        <div>
            <label for="title" class="form-label">{{ $testimony->conversionLabel('title_label') }} *</label>
            <input id="title" type="text" name="title" value="{{ old('title', $testimony->title) }}" required maxlength="200" class="form-input">
            @error('title')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="body_text" class="form-label">{{ $testimony->conversionLabel('body_label') }} @if($testimony->type->value === 'text')*@endif</label>
            <textarea id="body_text" name="body_text" rows="10" class="form-input" @required($testimony->type->value === 'text')>{{ old('body_text', \App\Services\Mentions::plain($testimony->body_text)) }}</textarea>
            @error('body_text')<p class="form-error">{{ $message }}</p>@enderror
        </div>
    </section>

    <section class="space-y-4 p-5 sm:p-6">
        <h2 class="card-title">{{ $testimony->conversionLabel('details') }}</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="conversion_year" class="form-label">{{ $testimony->conversionLabel('year') }}</label>
                <input id="conversion_year" type="number" name="conversion_year" value="{{ old('conversion_year', $details['year'] ?? null) }}"
                       min="{{ \App\Services\ConversionTestimonies::MIN_YEAR }}" max="{{ now()->year }}" inputmode="numeric" class="form-input"
                       placeholder="{{ $testimony->conversionLabel('year_placeholder') }}">
                @error('conversion_year')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="conversion_place" class="form-label">{{ $testimony->conversionLabel('place') }}</label>
                <input id="conversion_place" type="text" name="conversion_place" value="{{ old('conversion_place', $details['place'] ?? null) }}" maxlength="150"
                       class="form-input" placeholder="{{ $testimony->conversionLabel('place_placeholder') }}">
                @error('conversion_place')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="conversion_verse" class="form-label">{{ $testimony->conversionLabel('verse') }}</label>
                <input id="conversion_verse" type="text" name="conversion_verse" value="{{ old('conversion_verse', $details['verse'] ?? null) }}" maxlength="100"
                       class="form-input" placeholder="{{ $testimony->conversionLabel('verse_placeholder') }}">
                @error('conversion_verse')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    <div class="flex flex-wrap justify-end gap-2 p-5 sm:p-6">
        <a href="{{ route('testimonies.show', $testimony->id) }}" class="btn-secondary">{{ __('layout.confirm.cancel') }}</a>
        <button type="submit" class="btn-primary">{{ $testimony->conversionLabel('save') }}</button>
    </div>
</form>
@endsection

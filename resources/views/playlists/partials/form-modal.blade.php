{{-- Création ou modification d'une liste : @include('playlists.partials.form-modal', ['modalId' => …, 'playlist' => $p ou null]) --}}
@php $editing = $playlist !== null; @endphp
<div id="{{ $modalId }}" data-modal class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="{{ $modalId }}-title" @unless($errors->hasAny(['title', 'description'])) hidden @endunless>
    <div class="absolute inset-0 bg-slate-900/50" data-modal-close></div>
    <form method="POST" action="{{ $editing ? route('playlists.update', $playlist->id) : route('playlists.store') }}"
          class="card relative w-full max-w-lg shadow-xl" data-loading-label="{{ __('playlists.form.save') }}">
        @csrf
        @if($editing) @method('PUT') @endif
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
            <h2 id="{{ $modalId }}-title" class="text-base font-semibold text-primary-600">{{ $editing ? __('playlists.show.edit') : __('playlists.mine.create') }}</h2>
            <button type="button" class="btn-ghost btn-sm" data-modal-close aria-label="{{ __('playlists.form.cancel') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="space-y-4 px-5 py-4">
            <div>
                <label for="{{ $modalId }}-name" class="form-label">{{ __('playlists.form.title') }} *</label>
                <input id="{{ $modalId }}-name" type="text" name="title" required maxlength="{{ \App\Models\TestimonyPlaylist::TITLE_MAX }}"
                       value="{{ old('title', $playlist?->title) }}" class="form-input" placeholder="{{ __('playlists.form.title_placeholder') }}">
                @error('title')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="{{ $modalId }}-description" class="form-label">{{ __('playlists.form.description') }}</label>
                <textarea id="{{ $modalId }}-description" name="description" rows="3" maxlength="{{ \App\Models\TestimonyPlaylist::DESCRIPTION_MAX }}" class="form-input">{{ old('description', $playlist?->description) }}</textarea>
                <p class="form-hint">{{ __('playlists.form.description_hint') }}</p>
                @error('description')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <button type="button" class="btn-secondary" data-modal-close>{{ __('playlists.form.cancel') }}</button>
            <button type="submit" class="btn-primary">{{ $editing ? __('playlists.form.save') : __('playlists.form.create') }}</button>
        </div>
    </form>
</div>

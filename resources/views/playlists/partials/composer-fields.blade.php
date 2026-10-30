{{--
    Choix facultatif d'une liste : liste existante (playlist_id) ou nouvelle liste (playlist_title, prioritaire).
    @include('playlists.partials.composer-fields', ['playlists' => $listes, 'idPrefix' => 'compose'])
    Voir docs/fonctionnalites/listes-de-temoignages.md
--}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    @if($playlists->isNotEmpty())
    <div class="min-w-0">
        <label for="{{ $idPrefix }}-playlist-id" class="form-label">{{ __('playlists.composer.choose') }}</label>
        <select id="{{ $idPrefix }}-playlist-id" name="playlist_id" class="form-input" @error('playlist_id') aria-invalid="true" @enderror>
            <option value="">{{ __('playlists.composer.none') }}</option>
            @foreach($playlists as $option)
            <option value="{{ $option->id }}" @selected(old('playlist_id') === $option->id)>{{ Str::limit($option->title, 80) }}</option>
            @endforeach
        </select>
        @error('playlist_id')<p class="form-error">{{ $message }}</p>@enderror
    </div>
    @endif
    <div class="min-w-0 {{ $playlists->isEmpty() ? 'sm:col-span-2' : '' }}">
        <label for="{{ $idPrefix }}-playlist-title" class="form-label">{{ $playlists->isEmpty() ? __('playlists.form.title') : __('playlists.composer.new') }}</label>
        <input id="{{ $idPrefix }}-playlist-title" type="text" name="playlist_title" value="{{ old('playlist_title') }}"
               maxlength="{{ \App\Models\TestimonyPlaylist::TITLE_MAX }}" class="form-input" placeholder="{{ __('playlists.form.title_placeholder') }}">
        <p class="form-hint">{{ __('playlists.composer.new_hint') }}</p>
        @error('playlist_title')<p class="form-error">{{ $message }}</p>@enderror
        @error('playlist')<p class="form-error">{{ $message }}</p>@enderror
    </div>
</div>

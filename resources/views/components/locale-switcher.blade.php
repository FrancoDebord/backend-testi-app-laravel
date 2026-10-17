{{--
    Sélecteur de langue (français / anglais) : enregistre le choix en session et, pour un membre
    connecté, dans users.locale (langue de ses push et e-mails). Voir docs/fonctionnalites/langues.md
    @include('components.locale-switcher')
--}}
@php $current = app()->getLocale(); @endphp
<form method="POST" action="{{ route('locale.update') }}" class="flex items-center gap-1" role="group" aria-label="{{ __('layout.language.choose') }}">
    @csrf
    @foreach(\App\Support\Locales::labels() as $code => $name)
    <button type="submit" name="locale" value="{{ $code }}" lang="{{ $code }}" title="{{ $name }}"
            class="{{ $current === $code ? 'chip-active' : 'chip' }} px-2.5 uppercase"
            @if($current === $code) aria-pressed="true" @else aria-pressed="false" @endif>
        <span aria-hidden="true">{{ $code }}</span><span class="sr-only">{{ $name }}</span>
    </button>
    @endforeach
</form>

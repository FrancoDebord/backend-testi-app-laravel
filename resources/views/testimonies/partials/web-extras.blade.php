{{--
    Page d'un témoignage : texte écrit d'une vidéo ou d'un audio (résumé, transcription), verset du thème
    correspondant du verset du jour, partage WhatsApp / Facebook et lien vers l'application.
    Lisible sans JavaScript. Voir docs/fonctionnalites/referencement.md
--}}
@php
    $webTheme  = \App\Support\Seo::themeForCategory($testimony->category_slug);
    $themeVerse = $webTheme ? \App\Support\Seo::keyVerse($webTheme) : null;
    $shareText = rawurlencode(trim((string) $testimony->title) . ' — ' . $testimony->share_url);
@endphp

@if($testimony->resume || $testimony->transcription)
<section class="card mt-4 p-5 sm:p-6" aria-labelledby="testimony-text-title">
    <h2 id="testimony-text-title" class="card-title">{{ __('seo.testimony.written') }}</h2>
    @if($testimony->resume)
    <div class="rich-text mt-3 text-[15px] leading-relaxed text-slate-800">{{ \App\Support\RichText::toHtml($testimony->resume) }}</div>
    @endif
    @if($testimony->transcription)
    <details class="mt-4 rounded-lg border border-slate-200 p-4" @if(!$testimony->resume) open @endif>
        <summary class="cursor-pointer text-sm font-semibold text-primary-700">{{ __('seo.testimony.transcription') }}</summary>
        <div class="rich-text mt-3 text-sm leading-relaxed text-slate-700">{{ \App\Support\RichText::toHtml($testimony->transcription) }}</div>
    </details>
    @endif
</section>
@endif

@if($webTheme && $themeVerse)
<section class="card mt-4 p-5 sm:p-6" aria-labelledby="testimony-theme-title">
    <h2 id="testimony-theme-title" class="card-title">{{ __('seo.testimony.theme_verse') }}</h2>
    <blockquote class="mt-3 border-l-4 border-sun-400 pl-4 text-[15px] leading-relaxed text-slate-800">
        <p>« {{ $themeVerse->verse_text }} »</p>
        <footer class="mt-2 text-sm font-semibold text-slate-600">{{ $themeVerse->reference }}</footer>
    </blockquote>
    <a href="{{ route('themes.show', $webTheme->slug) }}" class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-primary-700 hover:underline">
        {{ __('seo.testimony.theme_link', ['theme' => $webTheme->thematique]) }}<i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
    </a>
</section>
@endif

@if($testimony->isOnPublicWeb())
<section class="card mt-4 flex flex-wrap items-center gap-2 p-4" aria-label="{{ __('seo.testimony.share') }}">
    <span class="text-sm font-semibold text-slate-700">{{ __('seo.testimony.share') }}</span>
    <a href="https://wa.me/?text={{ $shareText }}" target="_blank" rel="noopener" class="chip"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i>WhatsApp</a>
    <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($testimony->share_url) }}" target="_blank" rel="noopener" class="chip"><i class="fa-brands fa-facebook" aria-hidden="true"></i>Facebook</a>
    @include('components.app-banner', ['compact' => true])
</section>
@endif

{{--
    Aperçu du premier lien d'un texte (miniature, site, titre, description) :
    <x-link-preview :text="$comment->body" />  ou  <x-link-preview url="https://…" />
    Déjà en cache : rendu ici. Pas encore cherché : bloc caché rempli par resources/js/link-previews.js
    (GET /liens/apercu), retiré si la page n'a pas d'aperçu. Rien si le texte n'a pas de lien.
    Voir docs/fonctionnalites/liens-et-apercus.md
--}}
@props(['text' => null, 'url' => null])
@php
    $url ??= \App\Support\Linkify::firstUrl($text);
    $preview = $url && \App\Services\LinkPreviews::isAllowedUrl($url) ? \App\Services\LinkPreviews::cached($url) : false;
    $internal = $url && in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), array_filter([
        parse_url((string) config('app.url'), PHP_URL_HOST),
        parse_url((string) config('app.share_url'), PHP_URL_HOST),
    ]), true);
@endphp
@if($url && $preview !== false)
<a href="{{ $url }}" @unless($internal) target="_blank" rel="nofollow noopener noreferrer ugc" @endunless
   {{ $attributes->merge(['class' => 'mt-2 flex max-w-xl overflow-hidden rounded-xl border border-slate-200 bg-white text-left hover:bg-slate-50']) }}
   data-link-preview @if($preview === null) data-link-preview-url="{{ $url }}" hidden @endif>
    <img src="{{ $preview['image'] ?? '' }}" alt="" loading="lazy" referrerpolicy="no-referrer"
         class="min-h-20 w-24 shrink-0 bg-slate-100 object-cover sm:w-36" data-lp-image @if(empty($preview['image'])) hidden @endif>
    <span class="flex w-14 shrink-0 items-center justify-center bg-primary-50 text-primary-600" aria-hidden="true" data-lp-icon @unless(empty($preview['image'])) hidden @endunless>
        <i class="fa-solid fa-link"></i>
    </span>
    <span class="flex min-w-0 flex-1 flex-col justify-center gap-0.5 px-3 py-2.5">
        <span class="truncate text-xs text-slate-500" data-lp-site>{{ $preview ? ($preview['siteName'] ?? $preview['host']) : '' }}</span>
        <span class="line-clamp-2 text-sm font-semibold break-words text-slate-900" data-lp-title>{{ $preview ? ($preview['title'] ?? $preview['host']) : '' }}</span>
        <span class="line-clamp-2 text-xs break-words text-slate-600" data-lp-description @if(empty($preview['description'])) hidden @endif>{{ $preview['description'] ?? '' }}</span>
    </span>
</a>
@endif

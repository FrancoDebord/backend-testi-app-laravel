{{--
    Aperçu de partage (WhatsApp, Facebook, Telegram, X, LinkedIn) : balises Open Graph et Twitter, lien canonique.
    Voir docs/fonctionnalites/apercu-de-partage.md. À placer dans @push('meta') (tôt dans le <head>).

    Usage : <x-share-meta :meta="\App\Support\ShareMeta::testimony($testimony)" />
       ou   <x-share-meta title="…" description="…" url="…" image="…" type="article" :video="['url' => …]" />
    Image : JPEG 1200 × 630 servie par /partage/… (jamais /storage, refusé par Apache en production).
--}}
@props([
    'meta' => [],
    'title' => null,
    'description' => null,
    'url' => null,
    'image' => null,
    'imageAlt' => null,
    'type' => null,
    'video' => null,
])
@php
    $m = array_merge(\App\Support\ShareMeta::site(), array_filter((array) $meta, fn ($v) => $v !== null), array_filter(compact('title', 'description', 'url', 'image', 'imageAlt', 'type', 'video'), fn ($v) => $v !== null));
    $siteName = __('share.site_name');
    $locale = \App\Support\ShareMeta::locale();
@endphp
    <link rel="canonical" href="{{ $m['url'] }}">
    <meta name="description" content="{{ $m['description'] }}">
    <meta property="og:type" content="{{ $m['type'] ?? 'website' }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $m['title'] }}">
    <meta property="og:description" content="{{ $m['description'] }}">
    <meta property="og:url" content="{{ $m['url'] }}">
    <meta property="og:locale" content="{{ $locale }}">
    <meta property="og:locale:alternate" content="{{ $locale === 'fr_FR' ? 'en_US' : 'fr_FR' }}">
    <meta property="og:image" content="{{ $m['image'] }}">
    <meta property="og:image:secure_url" content="{{ $m['image'] }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="{{ \App\Services\ShareImage::WIDTH }}">
    <meta property="og:image:height" content="{{ \App\Services\ShareImage::HEIGHT }}">
    <meta property="og:image:alt" content="{{ $m['imageAlt'] ?? $m['title'] }}">
@if(!empty($m['video']))
    <meta property="og:video:url" content="{{ $m['video']['url'] }}">
    <meta property="og:video:secure_url" content="{{ $m['video']['url'] }}">
    <meta property="og:video:type" content="{{ $m['video']['type'] ?? 'text/html' }}">
    <meta property="og:video:width" content="{{ $m['video']['width'] ?? 1280 }}">
    <meta property="og:video:height" content="{{ $m['video']['height'] ?? 720 }}">
@endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $m['title'] }}">
    <meta name="twitter:description" content="{{ $m['description'] }}">
    <meta name="twitter:image" content="{{ $m['image'] }}">
    <meta name="twitter:image:alt" content="{{ $m['imageAlt'] ?? $m['title'] }}">

@extends('layouts.app')
@section('title', $title)
@php
    // Politique de confidentialité, publique : texte de resources/legal/privacy.{fr,en}.md
    // (docs/fonctionnalites/confidentialite-et-suppression.md)
    $header      = $title;
    $subheader   = __('privacy.updated', ['date' => $updatedAt->locale($locale)->translatedFormat($locale === 'en' ? 'F j, Y' : 'j F Y')]);
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('privacy.breadcrumb')],
    ];
@endphp

@push('meta')
<meta name="description" content="{{ $title }}">
<link rel="alternate" hreflang="fr" href="{{ route('legal.privacy', ['lang' => 'fr']) }}">
<link rel="alternate" hreflang="en" href="{{ route('legal.privacy.en', ['lang' => 'en']) }}">
@endpush

@section('content')
<div class="mx-auto max-w-3xl">
    <p class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ $otherUrl }}" lang="{{ $locale === 'en' ? 'fr' : 'en' }}" class="btn-ghost btn-sm">
            <i class="fa-solid fa-language" aria-hidden="true"></i>{{ __('privacy.other_language') }}
        </a>
        <a href="{{ $deletionUrl }}" class="text-sm font-semibold text-primary-600 hover:text-primary-700 hover:underline">{{ __('privacy.delete_link') }}</a>
    </p>

    <article class="card p-5 sm:p-8" lang="{{ $locale }}">
        <div class="doc-content break-words">
            {!! $html !!}
        </div>
    </article>
</div>
@endsection

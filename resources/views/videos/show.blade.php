@extends('layouts.app')
@section('title', $testimony->title)

{{-- Aperçu de partage (WhatsApp, Facebook…) : docs/fonctionnalites/apercu-de-partage.md --}}
{{-- Référencement : titre, robots, données structurées (docs/fonctionnalites/referencement.md) --}}
@section('page_title', \App\Support\Seo::title((string) $testimony->title, __('seo.testimony.kind')))
@section('robots', $testimony->isOnPublicWeb() ? \App\Support\Seo::INDEX : 'noindex, nofollow')
@push('meta')
    @include('testimonies.partials.seo')
@endpush

@section('content')
    @include('videos.partials.watch')
@endsection

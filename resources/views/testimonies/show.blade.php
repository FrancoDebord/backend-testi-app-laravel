@extends('layouts.app')
@section('title', $testimony->title)

{{-- Aperçu de partage (WhatsApp, Facebook…) : docs/fonctionnalites/apercu-de-partage.md --}}
@push('meta')
    <x-share-meta :meta="\App\Support\ShareMeta::testimony($testimony)" />
@endpush

{{-- Présentation de plateforme vidéo, commune avec /videos/{id} : docs/fonctionnalites/videos.md --}}
@section('content')
    @include('videos.partials.watch')
@endsection

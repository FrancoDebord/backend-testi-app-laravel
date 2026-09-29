@extends('layouts.app')
@section('title', $testimony->title)

{{-- Présentation de plateforme vidéo, commune avec /videos/{id} : docs/fonctionnalites/videos.md --}}
@section('content')
    @include('videos.partials.watch')
@endsection

@extends('layouts.app')
{{-- Page publique d'un thème du verset du jour (docs/fonctionnalites/referencement.md) --}}
@php
    $header      = $theme->thematique;
    $subheader   = __('seo.theme.week', ['start' => $theme->date_debut->translatedFormat('j F Y'), 'end' => $theme->date_fin->translatedFormat('j F Y')]);
    $breadcrumbs = [
        ['label' => __('seo.breadcrumb.home'), 'url' => route('home')],
        ['label' => __('seo.breadcrumb.daily_verse'), 'url' => route('daily-verse.show')],
        ['label' => $theme->thematique],
    ];
    $description = \App\Support\ShareMeta::excerpt(__('seo.theme.description', ['theme' => $theme->thematique, 'verse' => $theme->verset_cle]), 155);
@endphp
@section('title', $header)
@section('page_title', \App\Support\Seo::title($header, __('seo.theme.kind')))
@section('robots', \App\Support\Seo::INDEX)
@push('meta')
    <x-share-meta :title="$header" :description="$description" :url="$canonical" type="article" />
    {{ \App\Support\StructuredData::script(\App\Support\StructuredData::breadcrumb([
        [__('seo.breadcrumb.home'), \App\Support\ShareMeta::absolute('/')],
        [__('seo.breadcrumb.daily_verse'), \App\Support\ShareMeta::absolute(route('daily-verse.show', [], false))],
        [$theme->thematique, $canonical],
    ])) }}
@endpush

@section('content')
<div class="space-y-6">
    {{-- Titre : en-tête de page du gabarit ($header), seul <h1> de la page. --}}
    <section class="card p-5 sm:p-6">
        <p class="text-sm text-slate-500">
            {{ __('seo.theme.key_verse') }} <strong class="text-slate-800">{{ $theme->verset_cle }}</strong>
            @if($theme->periode) · {{ $theme->periode }}@endif
        </p>
    </section>

    @if($verses->isNotEmpty())
    <section class="card divide-y divide-slate-100" aria-labelledby="theme-verses">
        <h2 id="theme-verses" class="card-title px-5 pt-5 pb-3">{{ __('seo.theme.verses') }}</h2>
        @foreach($verses as $verse)
        <article class="px-5 py-4">
            <a href="{{ route('daily-verse.show', ['date' => $verse->scheduled_date->toDateString()]) }}" class="text-xs font-semibold text-primary-700 hover:underline">
                {{ \Illuminate\Support\Str::ucfirst($verse->scheduled_date->translatedFormat('l j F Y')) }}
            </a>
            <blockquote class="mt-1 text-[15px] leading-relaxed text-slate-800">« {{ $verse->verse_text }} »</blockquote>
            <p class="mt-1 text-sm font-semibold text-slate-600">{{ $verse->reference }}</p>
        </article>
        @endforeach
    </section>
    @endif

    {{-- Maillage : derniers témoignages publiés --}}
    @if($testimonies->isNotEmpty())
    <section aria-labelledby="theme-testimonies">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 id="theme-testimonies" class="card-title">{{ __('seo.theme.testimonies') }}</h2>
            @include('components.layout-toggle')
        </div>
        {{-- 6 témoignages : 3 colonnes au plus (deux rangées pleines). --}}
        @include('components.testimony-list', ['items' => $testimonies, 'routeName' => 'testimonies.show',
            'gridClass' => 'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:gap-5'])
    </section>
    @endif
</div>
@endsection

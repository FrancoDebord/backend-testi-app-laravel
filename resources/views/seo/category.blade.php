@extends('layouts.app')
{{-- Page publique d'une catégorie de témoignages (docs/fonctionnalites/referencement.md) --}}
@php
    $name        = $category->displayName();
    $header      = \App\Support\Seo::categoryTitle($category);
    $subheader   = trans_choice('seo.category.count', $testimonies->total(), ['count' => \App\Support\Locales::number($testimonies->total())]);
    $breadcrumbs = [
        ['label' => __('seo.breadcrumb.home'), 'url' => route('home')],
        ['label' => __('seo.breadcrumb.testimonies'), 'url' => route('videos.index')],
        ['label' => $name],
    ];
    $description = $category->intro ? \App\Support\ShareMeta::excerpt($category->intro, 155) : __('seo.category.description', ['category' => $name]);
@endphp
@section('title', $header)
@section('page_title', \App\Support\Seo::title($header))
@section('robots', \App\Support\Seo::INDEX)
@push('meta')
    <x-share-meta :title="$header" :description="$description" :url="$canonical" type="website" />
    {{ \App\Support\StructuredData::script(\App\Support\StructuredData::collection($category, $canonical, collect($testimonies->items()))) }}
    {{ \App\Support\StructuredData::script(\App\Support\StructuredData::breadcrumb([
        [__('seo.breadcrumb.home'), \App\Support\ShareMeta::absolute('/')],
        [$name, $canonical],
    ])) }}
    @if($testimonies->previousPageUrl())<link rel="prev" href="{{ $testimonies->previousPageUrl() }}">@endif
    @if($testimonies->nextPageUrl())<link rel="next" href="{{ $testimonies->nextPageUrl() }}">@endif
@endpush

@section('content')
<div class="space-y-6">
    {{-- Titre : en-tête de page du gabarit ($header), seul <h1> de la page. --}}
    <section class="card p-5 sm:p-6">
        <div class="rich-text text-[15px] leading-relaxed text-slate-700">
            {{ $category->intro ? \App\Support\RichText::toHtml($category->intro) : __('seo.category.description', ['category' => $name]) }}
        </div>
    </section>

    @if($testimonies->isEmpty())
        @include('components.empty-state', ['title' => __('seo.category.empty'), 'text' => __('seo.category.empty_text')])
    @else
    {{-- Même présentation que l'accueil et Explorer : cartes encadrées ou liste compacte, au choix. --}}
    <section aria-labelledby="category-testimonies">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 id="category-testimonies" class="card-title">{{ __('seo.breadcrumb.testimonies') }}</h2>
            @include('components.layout-toggle')
        </div>
        @include('components.testimony-list', ['items' => $testimonies, 'routeName' => 'testimonies.show'])
        <div class="mt-6">{{ $testimonies->links() }}</div>
    </section>
    @endif

    {{-- Maillage : les autres catégories --}}
    <nav class="card p-5" aria-labelledby="other-categories">
        <h2 id="other-categories" class="card-title">{{ __('seo.category.others') }}</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach($categories->where('id', '!=', $category->id) as $other)
            <a href="{{ route('testimonies.category', $other->slug) }}" class="chip">{{ $other->displayName() }}</a>
            @endforeach
        </div>
    </nav>
</div>
@endsection

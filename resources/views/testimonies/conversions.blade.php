@extends('layouts.app')
@php
    // Onglets : conversions des personnes, genèse des ministères (organisations). docs/fonctionnalites/temoignage-de-conversion.md
    $isGenesis   = $tab === 'ministeres';
    $header      = $isGenesis ? __('conversion.genesis_section_title') : __('conversion.section_title');
    $subheader   = $isGenesis ? __('conversion.genesis_section_subtitle') : __('conversion.section_subtitle');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('conversion.section_title'), 'url' => $isGenesis ? route('testimonies.conversions') : null],
        $isGenesis ? ['label' => __('conversion.genesis_section_title')] : null,
    ];
    $breadcrumbs = array_values(array_filter($breadcrumbs, fn ($b) => $b !== null));
    $shareLabel  = \App\Services\ConversionTestimonies::label('share_yours', auth()->user());
@endphp
@section('title', $header)
{{-- Page publique indexable (docs/fonctionnalites/referencement.md) --}}
@section('robots', \App\Support\Seo::INDEX)

@section('headerActions')
    @if($canCreate)
    <a href="{{ route('publish', ['kind' => 'conversion']) }}" class="btn-cta"><i class="fa-solid fa-dove" aria-hidden="true"></i>{{ $shareLabel }}</a>
    @elseif($mine)
    <a href="{{ route('testimonies.show', $mine->id) }}" class="btn-secondary"><i class="fa-solid fa-dove" aria-hidden="true"></i>{{ \App\Services\ConversionTestimonies::label('view_mine', auth()->user()) }}</a>
    @endif
    @guest
    <a href="{{ route('login') }}" class="btn-cta"><i class="fa-solid fa-dove" aria-hidden="true"></i>{{ __('conversion.sign_in_to_share') }}</a>
    @endguest
@endsection

@section('content')
<nav class="mb-6 flex gap-6 overflow-x-auto border-b border-slate-200" aria-label="{{ __('conversion.section_title') }}">
    <a href="{{ route('testimonies.conversions') }}" class="{{ $isGenesis ? 'tab' : 'tab-active' }}" @if(!$isGenesis) aria-current="page" @endif>
        <i class="fa-solid fa-dove" aria-hidden="true"></i>{{ __('conversion.tab_conversions') }}
    </a>
    <a href="{{ route('testimonies.conversions', ['onglet' => 'ministeres']) }}" class="{{ $isGenesis ? 'tab-active' : 'tab' }}" @if($isGenesis) aria-current="page" @endif>
        <i class="fa-solid fa-church" aria-hidden="true"></i>{{ __('conversion.tab_genesis') }}
    </a>
</nav>

@if($testimonies->isEmpty())
    @include('components.empty-state', [
        'title'       => $isGenesis ? __('conversion.genesis_empty') : __('conversion.empty'),
        'text'        => $subheader,
        'actionUrl'   => $canCreate ? route('publish', ['kind' => 'conversion']) : (auth()->guest() ? route('login') : null),
        'actionLabel' => $canCreate ? $shareLabel : __('conversion.sign_in_to_share'),
    ])
@else
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
        @foreach($testimonies as $testimony)
            @include('components.conversion-card', ['testimony' => $testimony, 'showAuthor' => true, 'large' => false])
        @endforeach
    </div>
    <div class="mt-6">{{ $testimonies->links() }}</div>
@endif
@endsection

@extends('layouts.app')
@section('title', __('conversion.section_title'))
@php
    $header      = __('conversion.section_title');
    $subheader   = __('conversion.section_subtitle');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('conversion.section_title')],
    ];
@endphp

@section('headerActions')
    @if($canCreate)
    <a href="{{ route('publish', ['kind' => 'conversion']) }}" class="btn-cta"><i class="fa-solid fa-dove" aria-hidden="true"></i>{{ __('conversion.share_yours') }}</a>
    @elseif($mine)
    <a href="{{ route('testimonies.show', $mine->id) }}" class="btn-secondary"><i class="fa-solid fa-dove" aria-hidden="true"></i>{{ __('conversion.view_mine') }}</a>
    @endif
    @guest
    <a href="{{ route('login') }}" class="btn-cta"><i class="fa-solid fa-dove" aria-hidden="true"></i>{{ __('conversion.sign_in_to_share') }}</a>
    @endguest
@endsection

@section('content')
@if($testimonies->isEmpty())
    @include('components.empty-state', [
        'title'       => __('conversion.empty'),
        'text'        => __('conversion.section_subtitle'),
        'actionUrl'   => $canCreate ? route('publish', ['kind' => 'conversion']) : (auth()->guest() ? route('login') : null),
        'actionLabel' => $canCreate ? __('conversion.share_yours') : __('conversion.sign_in_to_share'),
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

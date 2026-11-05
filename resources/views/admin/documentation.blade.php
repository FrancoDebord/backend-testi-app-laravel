@extends('layouts.app')
@section('title', __('layout.nav.documentation') . ' — ' . $title)
@php
    $header      = $title;
    $subheader   = __('admin.documentation.subheader', ['date' => $updated]);
    $breadcrumbs = array_values(array_filter([
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.administration'), 'url' => route('admin.dashboard')],
        $current === 'README' ? ['label' => __('layout.nav.documentation')] : ['label' => __('layout.nav.documentation'), 'url' => route('admin.documentation')],
        $current === 'README' ? null : ['label' => $title],
    ]));
    $docUrl = fn (string $key) => $key === 'README' ? route('admin.documentation') : route('admin.documentation', ['page' => $key]);
    $groups = collect($pages)->groupBy('group', preserveKeys: true);
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">

    {{-- ── Sommaire ────────────────────────────────────────────────────── --}}
    <nav aria-label="{{ __('admin.documentation.nav_label') }}" class="min-w-0">
        <button type="button" class="btn-secondary w-full justify-between lg:hidden" data-toggle="doc-nav" aria-controls="doc-nav" aria-expanded="false">
            <span class="truncate">{{ __('admin.documentation.contents') }}</span><i class="fa-solid fa-chevron-down"></i>
        </button>
        <div id="doc-nav" class="card mt-2 hidden p-3 lg:sticky lg:top-20 lg:mt-0 lg:block">
            @foreach($groups as $group => $groupPages)
            <p class="section-title mb-1 px-2 pt-2 first:pt-0">{{ $group }}</p>
            <ul class="mb-2 space-y-0.5">
                @foreach($groupPages as $key => $page)
                <li>
                    <a href="{{ $docUrl($key) }}"
                       @if($key === $current) aria-current="page" @endif
                       class="{{ $key === $current ? 'bg-primary-50 font-semibold text-primary-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }} block rounded-lg px-2 py-1.5 text-sm">
                        {{ $page['title'] }}
                    </a>
                </li>
                @endforeach
            </ul>
            @endforeach
        </div>
    </nav>

    {{-- ── Contenu ─────────────────────────────────────────────────────── --}}
    <article class="card doc-content min-w-0 p-5 sm:p-8">
        {!! $html !!}
    </article>
</div>
@endsection

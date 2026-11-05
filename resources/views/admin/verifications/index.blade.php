@extends('layouts.app')
@section('title', __('layout.nav.verifications'))
@php
    // docs/fonctionnalites/verification-des-comptes.md
    $header      = __('layout.nav.verifications');
    $subheader   = __('admin.verifications.subheader');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.administration'), 'url' => route('admin.dashboard')],
        ['label' => __('layout.nav.verifications')],
    ];
    $tabs = [
        'pending'  => __('admin.verifications.tab_pending'),
        'approved' => __('admin.verifications.tab_approved'),
        'rejected' => __('admin.verifications.tab_rejected'),
        'revoked'  => __('admin.verifications.tab_revoked'),
        'all'      => __('admin.verifications.tab_all'),
    ];
@endphp

@section('content')
<nav class="mb-6 overflow-x-auto border-b border-slate-200" aria-label="{{ __('admin.verifications.tabs_label') }}">
    <ul class="flex gap-4 sm:gap-6">
        @foreach($tabs as $val => $label)
        <li>
            <a href="{{ route('admin.verifications.index', $val === 'pending' ? [] : ['status' => $val]) }}"
               class="{{ $tab === $val ? 'tab-active' : 'tab' }} whitespace-nowrap"
               @if($tab === $val) aria-current="page" @endif>
                {{ $label }}
                @if($val === 'pending' && $pendingCount > 0)
                <span class="rounded-full bg-accent-500 px-1.5 text-[11px] leading-5 text-white">{{ $pendingCount }}</span>
                @endif
            </a>
        </li>
        @endforeach
    </ul>
</nav>

<p class="mb-4 text-sm text-slate-600">{{ __('admin.verifications.orgs_note') }} <a href="{{ route('admin.users.index', ['tab' => 'pending']) }}" class="font-semibold text-primary-600 hover:underline">{{ __('admin.verifications.orgs_note_link') }}</a>.</p>

@if($requests->isEmpty())
    @include('components.empty-state', ['title' => __('admin.verifications.empty_title'), 'text' => $tab === 'pending' ? __('admin.verifications.empty_pending') : __('admin.verifications.empty_other')])
@else
<ul class="card divide-y divide-slate-100">
    @foreach($requests as $item)
    <li>
        <a href="{{ route('admin.verifications.show', $item->id) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 sm:px-5">
            @if($item->user)
            @include('components.avatar', ['user' => $item->user, 'size' => 'sm'])
            @endif
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold text-slate-900">{{ $item->full_name }}</span>
                <span class="block truncate text-xs text-slate-500">{{ $item->roleLabel() }} · {{ $item->church }}</span>
                <span class="block text-xs text-slate-400">{{ $item->created_at->translatedFormat(__('admin.formats.datetime_long')) }} · {{ __('admin.verifications.documents_count', ['count' => count($item->documents ?? [])]) }}</span>
            </span>
            <span class="{{ $item->statusBadgeClass() }} shrink-0">{{ $item->statusLabel() }}</span>
        </a>
    </li>
    @endforeach
</ul>
<div class="mt-6">{{ $requests->links() }}</div>
@endif
@endsection

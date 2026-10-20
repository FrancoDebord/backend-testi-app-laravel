@extends('layouts.app')
@section('title', __('privacy.admin.title'))
@php
    // docs/fonctionnalites/confidentialite-et-suppression.md
    $header      = __('privacy.admin.title');
    $subheader   = __('privacy.admin.subheader');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.administration'), 'url' => route('admin.dashboard')],
        ['label' => __('privacy.admin.title')],
    ];
    $isSuperAdmin = auth()->user()->isSuperAdmin();
@endphp

@section('content')
@if($requests->isEmpty())
    @include('components.empty-state', ['title' => __('privacy.admin.empty'), 'text' => __('privacy.admin.subheader')])
@else
<ul class="card divide-y divide-slate-100">
    @foreach($requests as $item)
    <li class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:px-5">
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-slate-900">
                <i class="fa-solid {{ $item->contact_type === 'email' ? 'fa-envelope' : 'fa-phone' }} mr-1 text-slate-400" aria-hidden="true"></i>{{ $item->contact }}
            </p>
            <p class="truncate text-xs text-slate-500">
                {{ __('privacy.admin.account') }} :
                @if($item->user)
                <a href="{{ route('admin.users.show', $item->user->id) }}" class="font-semibold text-primary-600 hover:underline">{{ $item->user->display_name }}</a>
                @else
                {{ __('privacy.admin.no_account') }}
                @endif
            </p>
            <p class="text-xs text-slate-400">{{ __('privacy.admin.received') }} {{ $item->created_at->translatedFormat('j M Y H:i') }}@if($item->handler) · {{ $item->handler->display_name }}@endif</p>
            @if($item->message)
            <p class="mt-1 text-sm break-words text-slate-600">{{ $item->message }}</p>
            @endif
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <span class="{{ match ($item->status) { 'done' => 'badge-validated', 'rejected' => 'badge-rejected', default => 'badge-pending' } }}">{{ __('privacy.admin.statuses.' . $item->status) }}</span>
            @if($isSuperAdmin && $item->status === 'pending')
                @if($item->user)
                <form id="delete-account-{{ $item->id }}" method="POST" action="{{ route('admin.deletion-requests.delete-account', $item->id) }}">
                    @csrf @method('DELETE')
                    <button type="button" class="btn-secondary btn-sm"
                            onclick="openConfirmModal('delete-account-{{ $item->id }}', @js(__('privacy.admin.confirm_delete', ['name' => $item->user->display_name])), @js(__('privacy.admin.delete')), @js(__('privacy.admin.delete')), 'fa-trash-can')">
                        <i class="fa-solid fa-trash-can text-error-600" aria-hidden="true"></i>{{ __('privacy.admin.delete') }}
                    </button>
                </form>
                @endif
                <form method="POST" action="{{ route('admin.deletion-requests.reject', $item->id) }}">
                    @csrf
                    <button type="submit" class="btn-ghost btn-sm">{{ __('privacy.admin.reject') }}</button>
                </form>
            @endif
        </div>
    </li>
    @endforeach
</ul>
<div class="mt-6">{{ $requests->links() }}</div>
@endif
@endsection

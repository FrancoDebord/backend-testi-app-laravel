@extends('layouts.app')
@section('title', __('layout.nav.users'))
@php
    $header      = __('layout.nav.users');
    $subheader   = __('admin.users.subheader');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.administration'), 'url' => route('admin.dashboard')],
        ['label' => __('layout.nav.users')],
    ];
    $roles    = collect(\App\Enums\UserRole::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all();
    $statuses = collect(\App\Enums\UserAccountStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
    $activeFilters = (request('role') ? 1 : 0) + (request('status') ? 1 : 0);
    // Suppression définitive : super-administrateurs (docs/fonctionnalites/confidentialite-et-suppression.md)
    $canDeleteUsers = auth()->user()->isSuperAdmin();
    // Onglets : docs/fonctionnalites/comptes-organisation.md
    // [libellé, libellé court affiché sous 640 px]
    $tabs = [
        'all'           => [__('admin.users.tab_all'), __('admin.users.tab_all_short')],
        'organizations' => [__('admin.users.tab_organizations'), __('admin.users.tab_organizations')],
        'pending'       => [__('admin.users.tab_pending'), __('admin.users.tab_pending_short')],
        // Badge « Approuvé » (docs/fonctionnalites/badge-approuve.md)
        'approved'      => [__('admin.users.tab_approved'), __('admin.users.tab_approved_short')],
    ];
@endphp

@section('content')
<nav class="mb-6 overflow-x-auto border-b border-slate-200" aria-label="{{ __('admin.users.tabs_label') }}">
    <ul class="flex gap-4 sm:gap-6">
        @foreach($tabs as $val => [$label, $shortLabel])
        <li>
            <a href="{{ route('admin.users.index', $val === 'all' ? [] : ['tab' => $val]) }}"
               class="{{ $tab === $val ? 'tab-active' : 'tab' }} whitespace-nowrap"
               @if($tab === $val) aria-current="page" @endif>
                <span class="hidden sm:inline">{{ $label }}</span><span class="sm:hidden">{{ $shortLabel }}</span>
                @if($val === 'pending' && $pendingOrganizations > 0)
                <span class="rounded-full bg-accent-500 px-1.5 text-[11px] leading-5 text-white">{{ $pendingOrganizations }}</span>
                @endif
            </a>
        </li>
        @endforeach
    </ul>
</nav>

<form method="GET" action="{{ route('admin.users.index') }}" class="mb-6 space-y-3" data-loading-inline data-loading-label="{{ __('admin.common.filtering') }}">
    @if($tab !== 'all')<input type="hidden" name="tab" value="{{ $tab }}">@endif
    <div class="flex flex-wrap gap-2">
        <label for="users-q" class="sr-only">{{ __('layout.search') }}</label>
        <div class="relative min-w-0 flex-1 basis-full sm:basis-60">
            <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400"></i>
            <input id="users-q" type="search" name="q" value="{{ request('q') }}" data-search-autocomplete="users" data-search-recent-key="admin-users" autocomplete="off" class="form-input pl-9" placeholder="{{ __('admin.users.search_placeholder') }}">
        </div>
        <button type="button" class="btn-secondary md:hidden" data-toggle="users-filters" aria-controls="users-filters" aria-expanded="false">
            <i class="fa-solid fa-sliders"></i>{{ __('admin.common.filters') }}
            @if($activeFilters)<span class="rounded-full bg-accent-500 px-1.5 text-[11px] leading-5 text-white">{{ $activeFilters }}</span>@endif
        </button>
        <button type="submit" class="btn-primary">{{ __('admin.common.filter') }}</button>
    </div>
    <div id="users-filters" class="hidden md:block">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:flex md:flex-wrap">
        <div class="md:w-52">
            <label for="users-role" class="form-label">{{ __('admin.common.role') }}</label>
            <select id="users-role" name="role" class="form-input">
                <option value="">{{ __('admin.users.all_roles') }}</option>
                @foreach($roles as $v => $l)
                <option value="{{ $v }}" @selected(request('role') === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:w-52">
            <label for="users-status" class="form-label">{{ __('admin.common.status') }}</label>
            <select id="users-status" name="status" class="form-input">
                <option value="">{{ __('admin.common.all_statuses') }}</option>
                @foreach($statuses as $v => $l)
                <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
    </div>
    </div>
</form>

@if($users->isEmpty())
    @include('components.empty-state', match (true) {
        $tab === 'pending' && !request()->anyFilled(['q', 'role', 'status']) => ['title' => __('admin.users.empty_pending_title'), 'text' => __('admin.users.empty_pending_text')],
        default => ['title' => __('admin.users.empty_title'), 'text' => __('admin.users.empty_text')],
    })
@else
    {{-- Mobile : fiches --}}
    <ul class="space-y-3 md:hidden">
        @foreach($users as $user)
        <li class="card p-4">
            <div class="flex items-center gap-3">
                @include('components.avatar', ['user' => $user, 'size' => 'md'])
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium text-slate-900">{{ $user->display_name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                </div>
                <span class="{{ $user->status->badgeClass() }}">{{ $user->status->label() }}</span>
            </div>
            <dl class="mt-3 space-y-1 text-sm">
                @if($user->isOrganization())
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('admin.common.organization') }}</dt><dd class="text-right text-slate-900">{{ $user->organization_type?->label() ?? __('admin.common.organization') }}</dd></div>
                @if($user->verification_status)
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('admin.common.verification') }}</dt><dd><span class="{{ $user->verification_status->badgeClass() }}">{{ $user->verification_status->label() }}</span></dd></div>
                @endif
                @endif
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('admin.common.role') }}</dt><dd class="text-slate-900">{{ $user->role->label() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('admin.common.registered_on') }}</dt><dd class="text-slate-900">{{ $user->created_at->format(__('admin.formats.date')) }}</dd></div>
            </dl>
            <div class="mt-3 flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-3">
                <a href="{{ route('admin.users.show', $user->id) }}" class="action-btn-edit"><i class="fa-solid fa-pen"></i>{{ __('admin.common.manage') }}</a>
                @include('admin.users.partials.delete-button', ['formId' => 'delete-user-m-' . $user->id])
            </div>
        </li>
        @endforeach
    </ul>

    {{-- Tablette et ordinateur : tableau --}}
    <div class="card hidden overflow-hidden md:block">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="table-th">{{ __('admin.users.user') }}</th>
                        <th class="table-th hidden xl:table-cell">{{ __('admin.users.email') }}</th>
                        <th class="table-th">{{ __('admin.common.role') }}</th>
                        <th class="table-th">{{ __('admin.common.status') }}</th>
                        <th class="table-th hidden xl:table-cell">{{ __('admin.common.registered_on') }}</th>
                        <th class="table-th text-right">{{ __('admin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    <tr class="hover:bg-slate-50">
                        <td class="table-td">
                            <div class="flex items-center gap-3">
                                @include('components.avatar', ['user' => $user, 'size' => 'sm'])
                                <div class="min-w-0">
                                    <p class="font-medium break-words text-slate-900">{{ $user->display_name }}</p>
                                    <p class="text-xs break-all text-slate-500 xl:hidden">{{ $user->email }}</p>
                                    @if($user->isOrganization())
                                    <p class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                        <span>{{ $user->organization_type?->label() ?? __('admin.common.organization') }}@if($user->organization_city) · {{ $user->organization_city }}@endif</span>
                                        @if($user->verification_status)<span class="{{ $user->verification_status->badgeClass() }}">{{ $user->verification_status->label() }}</span>@endif
                                    </p>
                                    @elseif($user->country)<p class="text-xs text-slate-500">{{ $user->country }}</p>@endif
                                </div>
                            </div>
                        </td>
                        <td class="table-td hidden text-slate-500 xl:table-cell">{{ $user->email }}</td>
                        <td class="table-td whitespace-nowrap">{{ $user->role->label() }}</td>
                        <td class="table-td"><span class="{{ $user->status->badgeClass() }}">{{ $user->status->label() }}</span></td>
                        <td class="table-td hidden whitespace-nowrap text-slate-500 xl:table-cell">{{ $user->created_at->format(__('admin.formats.date')) }}</td>
                        <td class="table-td">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.users.show', $user->id) }}" class="action-btn-edit"><i class="fa-solid fa-pen"></i>{{ __('admin.common.manage') }}</a>
                                @include('admin.users.partials.delete-button', ['formId' => 'delete-user-d-' . $user->id])
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $users->withQueryString()->links() }}</div>
@endif
@endsection

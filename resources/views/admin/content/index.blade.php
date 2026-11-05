@extends('layouts.app')
@section('title', __('layout.nav.content'))
@php
    $header      = __('layout.nav.content');
    $subheader   = __('admin.content.subheader');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.administration'), 'url' => route('admin.dashboard')],
        ['label' => __('layout.nav.content')],
    ];
    $statuses = collect(['pending', 'approved', 'rejected', 'draft'])->mapWithKeys(fn ($v) => [$v => \App\Enums\TestimonyStatus::from($v)->label()])->all();
    $types    = collect(['text', 'audio', 'video'])->mapWithKeys(fn ($v) => [$v => \App\Enums\TestimonyType::from($v)->label()])->all();
    $activeFilters = (request('status') ? 1 : 0) + (request('type') ? 1 : 0);
@endphp

@section('content')
<form method="GET" action="{{ route('admin.content.index') }}" class="mb-6 space-y-3" data-loading-inline data-loading-label="{{ __('admin.common.filtering') }}">
    <div class="flex flex-wrap gap-2">
        <label for="content-q" class="sr-only">{{ __('layout.search') }}</label>
        <div class="relative min-w-0 flex-1 basis-60">
            <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400"></i>
            <input id="content-q" type="search" name="q" value="{{ request('q') }}" data-search-autocomplete="testimonies" data-search-recent-key="admin-content" autocomplete="off" class="form-input pl-9" placeholder="{{ __('admin.content.search_placeholder') }}">
        </div>
        <button type="button" class="btn-secondary md:hidden" data-toggle="content-filters" aria-controls="content-filters" aria-expanded="false">
            <i class="fa-solid fa-sliders"></i>{{ __('admin.common.filters') }}
            @if($activeFilters)<span class="rounded-full bg-slate-900 px-1.5 text-[11px] leading-5 text-white">{{ $activeFilters }}</span>@endif
        </button>
        <button type="submit" class="btn-primary">{{ __('admin.common.filter') }}</button>
    </div>
    <div id="content-filters" class="hidden md:block">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:flex md:flex-wrap">
            <div class="md:w-52">
                <label for="content-status" class="form-label">{{ __('admin.common.status') }}</label>
                <select id="content-status" name="status" class="form-input">
                    <option value="">{{ __('admin.common.all_statuses') }}</option>
                    @foreach($statuses as $v => $l)
                    <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:w-52">
                <label for="content-type" class="form-label">{{ __('admin.common.type') }}</label>
                <select id="content-type" name="type" class="form-input">
                    <option value="">{{ __('admin.content.all_types') }}</option>
                    @foreach($types as $v => $l)
                    <option value="{{ $v }}" @selected(request('type') === $v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</form>

@if($testimonies->isEmpty())
    @include('components.empty-state', ['title' => __('admin.content.empty_title'), 'text' => __('admin.content.empty_text')])
@else
    {{-- Mobile : fiches --}}
    <ul class="space-y-3 md:hidden">
        @foreach($testimonies as $t)
        <li class="card p-4">
            <div class="flex items-start justify-between gap-3">
                <p class="min-w-0 font-medium break-words text-slate-900">{{ $t->title }}</p>
                <span class="{{ $t->status->badgeClass() }}">{{ $t->status->label() }}</span>
            </div>
            <dl class="mt-3 space-y-1 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('admin.content.author') }}</dt><dd class="truncate text-slate-900">{{ $t->user->display_name }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('admin.common.type') }}</dt><dd class="text-slate-900">{{ $t->type->label() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('admin.content.views') }}</dt><dd class="text-slate-900">{{ number_format($t->views_count, 0, ',', ' ') }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('admin.common.date') }}</dt><dd class="text-slate-900">{{ $t->created_at->format(__('admin.formats.date')) }}</dd></div>
            </dl>
            <div class="mt-3 flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-3">
                <a href="{{ route('testimonies.show', $t->id) }}" target="_blank" rel="noopener" class="action-btn-view"><i class="fa-regular fa-eye"></i>{{ __('admin.common.view') }}</a>
                @if(in_array($t->status->value, ['pending', 'rejected'], true))
                <a href="{{ route('moderation.show', $t->id) }}" class="action-btn-edit"><i class="fa-solid fa-shield-halved"></i>{{ __('admin.common.moderate') }}</a>
                @endif
                @include('moderation.partials.approve-rejected', ['item' => $t, 'formId' => 'approve-m-' . $t->id])
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
                        <th class="table-th">{{ __('admin.content.title') }}</th>
                        <th class="table-th">{{ __('admin.content.author') }}</th>
                        <th class="table-th">{{ __('admin.common.type') }}</th>
                        <th class="table-th">{{ __('admin.common.status') }}</th>
                        <th class="table-th text-right">{{ __('admin.content.views') }}</th>
                        <th class="table-th">{{ __('admin.common.date') }}</th>
                        <th class="table-th text-right">{{ __('admin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($testimonies as $t)
                    <tr class="hover:bg-slate-50">
                        <td class="table-td max-w-xs">
                            <p class="truncate font-medium text-slate-900">{{ $t->title }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $t->category_slug }}</p>
                        </td>
                        <td class="table-td whitespace-nowrap">{{ $t->user->display_name }}</td>
                        <td class="table-td">{{ $t->type->label() }}</td>
                        <td class="table-td"><span class="{{ $t->status->badgeClass() }}">{{ $t->status->label() }}</span></td>
                        <td class="table-td text-right">{{ number_format($t->views_count, 0, ',', ' ') }}</td>
                        <td class="table-td whitespace-nowrap text-slate-500">{{ $t->created_at->format(__('admin.formats.date')) }}</td>
                        <td class="table-td">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('testimonies.show', $t->id) }}" target="_blank" rel="noopener" class="action-btn-view" title="{{ __('admin.common.view') }}" aria-label="{{ __('admin.common.view') }}"><i class="fa-regular fa-eye"></i></a>
                                @if(in_array($t->status->value, ['pending', 'rejected'], true))
                                <a href="{{ route('moderation.show', $t->id) }}" class="action-btn-edit"><i class="fa-solid fa-shield-halved"></i>{{ __('admin.common.moderate') }}</a>
                                @endif
                                @include('moderation.partials.approve-rejected', ['item' => $t, 'formId' => 'approve-d-' . $t->id])
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $testimonies->withQueryString()->links() }}</div>
@endif
@endsection

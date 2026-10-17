@extends('layouts.app')
{{-- Back-office des témoignages (docs/fonctionnalites/gestion-des-temoignages.md) : onglets, recherche, filtres, actions groupées. --}}
@section('title', __('moderation.index.title'))
@php
    $header      = __('moderation.index.header');
    $subheader   = __('moderation.index.subheader');
    $breadcrumbs = [['label' => __('layout.nav.home'), 'url' => route('home')], ['label' => __('layout.nav.moderation')]];
    $statCards = [
        [__('moderation.index.stat_pending'),        $stats['pending'],        'bg-amber-500'],
        [__('moderation.index.stat_approved_today'), $stats['approvedToday'],  'bg-emerald-500'],
        [__('moderation.index.stat_rejected_today'), $stats['rejectedToday'],  'bg-red-500'],
        [__('moderation.index.stat_month'),          $stats['totalThisMonth'], 'bg-slate-400'],
    ];
    $tabUrl = fn (string $t) => route('moderation.index', array_filter(['onglet' => $t, 'q' => request('q'), 'type' => request('type'), 'categorie' => request('categorie'), 'label' => request('label'), 'conversion' => request('conversion')]));
    $bulkActions = match ($tab) {
        'pending'  => ['approve' => __('moderation.bulk_actions.approve'), 'reject' => __('moderation.bulk_actions.reject'), 'trash' => __('moderation.bulk_actions.trash')],
        'approved' => ['feature' => __('moderation.bulk_actions.feature'), 'reject' => __('moderation.bulk_actions.unpublish'), 'trash' => __('moderation.bulk_actions.trash')],
        'rejected' => ['approve' => __('moderation.bulk_actions.approve'), 'trash' => __('moderation.bulk_actions.trash')],
        'featured' => ['unfeature' => __('moderation.bulk_actions.unfeature'), 'trash' => __('moderation.bulk_actions.trash')],
        'trash'    => ['restore' => __('moderation.bulk_actions.restore')],
    };
    $isSuper = auth()->user()->isSuperAdmin();
@endphp

@section('headerActions')
    <a href="{{ route('moderation.dashboard') }}" class="btn-secondary btn-sm"><i class="fa-solid fa-chart-line" aria-hidden="true"></i>{{ __('layout.nav.dashboard') }}</a>
@endsection

@section('content')
<div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach($statCards as [$label, $value, $dot])
    <div class="card p-4">
        <p class="flex items-center gap-2 text-sm text-slate-500"><span class="h-2 w-2 shrink-0 rounded-full {{ $dot }}"></span>{{ $label }}</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900 tabular-nums">{{ \App\Support\Locales::number($value) }}</p>
    </div>
    @endforeach
</div>

<nav class="mb-4 overflow-x-auto border-b border-slate-200" aria-label="{{ __('moderation.index.tabs_label') }}">
    <ul class="flex gap-6">
        @foreach($tabs as $val => $label)
        <li>
            <a href="{{ $tabUrl($val) }}" class="{{ $tab === $val ? 'tab-active' : 'tab' }} whitespace-nowrap" @if($tab === $val) aria-current="page" @endif>
                {{ $label }}@isset($counts[$val])<span class="ml-1 text-xs tabular-nums">({{ $counts[$val] }})</span>@endisset
            </a>
        </li>
        @endforeach
    </ul>
</nav>

{{-- Recherche et filtres --}}
<form method="GET" action="{{ route('moderation.index') }}" class="card mb-4 grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5" role="search">
    <input type="hidden" name="onglet" value="{{ $tab }}">
    <div class="sm:col-span-2">
        <label for="mod-q" class="form-label">{{ __('moderation.index.search') }}</label>
        <input id="mod-q" type="search" name="q" value="{{ request('q') }}" class="form-input" placeholder="{{ __('moderation.index.search_placeholder') }}">
    </div>
    <div>
        <label for="mod-type" class="form-label">{{ __('moderation.common.type') }}</label>
        <select id="mod-type" name="type" class="form-input">
            <option value="">{{ __('moderation.index.all_types') }}</option>
            @foreach(\App\Enums\TestimonyType::cases() as $t)<option value="{{ $t->value }}" @selected(request('type') === $t->value)>{{ $t->label() }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="mod-cat" class="form-label">{{ __('moderation.index.category') }}</label>
        <select id="mod-cat" name="categorie" class="form-input">
            <option value="">{{ __('moderation.index.all_categories') }}</option>
            @foreach($categories as $c)<option value="{{ $c->slug }}" @selected(request('categorie') === $c->slug)>{{ $c->name }}</option>@endforeach
        </select>
    </div>
    <div class="flex flex-wrap items-end gap-3">
        <label class="flex min-h-12 items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="label" value="1" class="rounded" @checked(request('label'))>{{ __('moderation.common.label_filter') }}</label>
        <label class="flex min-h-12 items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="conversion" value="1" class="rounded" @checked(request('conversion'))>{{ __('conversion.moderation_filter') }}</label>
        <button type="submit" class="btn-primary btn-sm ml-auto"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>{{ __('moderation.index.filter') }}</button>
    </div>
</form>

@if($tab === 'trash')
<p class="alert-info mb-4" role="status"><i class="fa-solid fa-circle-info mt-0.5" aria-hidden="true"></i><span>{{ __('moderation.index.trash_info', ['days' => $trashDays]) }}@unless($isSuper) {{ __('moderation.index.trash_super_only') }}@endunless</span></p>
@endif

@if($items->isEmpty())
    @include('components.empty-state', ['title' => __('moderation.index.empty_title'), 'text' => __('moderation.index.empty_text')])
@else
    {{-- Actions groupées --}}
    <form id="bulk-form" method="POST" action="{{ route('moderation.bulk') }}" class="card mb-4 flex flex-wrap items-end gap-3 p-4" data-loading-label="{{ __('moderation.index.processing') }}">
        @csrf
        <label class="flex min-h-10 items-center gap-2 text-sm font-medium text-slate-700"><input type="checkbox" class="rounded" data-check-all>{{ __('moderation.index.check_all') }}</label>
        <div>
            <label for="bulk-action" class="form-label">{{ __('moderation.index.action') }}</label>
            <select id="bulk-action" name="action" class="form-input" data-bulk-action>
                @foreach($bulkActions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        </div>
        @if(isset($bulkActions['reject']))
        <div data-bulk-reject hidden>
            <label for="bulk-reason" class="form-label">{{ __('moderation.index.reject_reason') }}</label>
            <select id="bulk-reason" name="reason" class="form-input">
                @foreach($reasons as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach
            </select>
        </div>
        <div class="min-w-0 flex-1" data-bulk-reject hidden>
            <label for="bulk-text" class="form-label">{{ __('moderation.index.reject_text') }}</label>
            <input id="bulk-text" type="text" name="rejection_reason" maxlength="1000" class="form-input" placeholder="{{ __('moderation.common.optional') }}">
        </div>
        @endif
        <button type="button" class="btn-primary btn-sm"
                onclick="openConfirmModal('bulk-form', @js(__('moderation.index.bulk_confirm')), @js(__('moderation.index.bulk_confirm_title')), @js(__('moderation.index.bulk_confirm_button')), 'fa-list-check')">
            {{ __('moderation.index.bulk_apply') }}
        </button>
        @error('ids')<p class="form-error w-full">{{ $message }}</p>@enderror
        @error('reason')<p class="form-error w-full">{{ $message }}</p>@enderror
    </form>

    {{-- Mobile : fiches --}}
    <ul class="space-y-3 md:hidden">
        @foreach($items as $item)
        <li class="card p-4">
            <div class="flex items-start gap-3">
                <input type="checkbox" name="ids[]" value="{{ $item->id }}" form="bulk-form" class="mt-1 rounded" aria-label="{{ __('moderation.index.select', ['title' => $item->title]) }}" data-bulk-item>
                <div class="min-w-0 flex-1">
                    <p class="font-medium break-words text-slate-900">{{ $item->title }}</p>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <span class="{{ $item->status->badgeClass() }}">{{ $item->status->label() }}</span>
                        @if($item->is_verified)<span class="badge-blue"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ __('moderation.common.label') }}</span>@endif
                        @if($item->isManuallyFeatured())<span class="badge-orange"><i class="fa-solid fa-star" aria-hidden="true"></i>{{ __('moderation.tabs.featured') }}</span>@endif
                        @if($item->isConversion())<span class="badge-yellow"><i class="fa-solid fa-dove" aria-hidden="true"></i>{{ __('conversion.badge') }}</span>@endif
                    </div>
                </div>
            </div>
            <dl class="mt-3 space-y-1 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('moderation.common.author') }}</dt><dd class="truncate text-slate-900">{{ $item->user?->display_name }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('moderation.common.type') }}</dt><dd class="text-slate-900">{{ $item->type->label() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ $tab === 'trash' ? __('moderation.index.deleted_on') : __('moderation.common.submitted_on') }}</dt><dd class="text-slate-900">{{ ($tab === 'trash' ? $item->deleted_at : $item->created_at)->format('d/m/Y') }}</dd></div>
                @if($tab === 'featured')<div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('moderation.common.order') }}</dt><dd class="text-slate-900">{{ $item->featured_order ?? '—' }}</dd></div>@endif
            </dl>
            <div class="mt-3 flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-3">
                <a href="{{ route('moderation.show', $item->id) }}" class="action-btn-view"><i class="fa-regular fa-eye"></i>{{ __('moderation.index.review') }}</a>
                @if($tab !== 'pending' && $tab !== 'trash')<a href="{{ route('moderation.stats', $item->id) }}" class="action-btn-view"><i class="fa-solid fa-chart-line"></i>{{ __('moderation.common.statistics') }}</a>@endif
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
                        <th class="table-th w-10"><span class="sr-only">{{ __('moderation.index.selection') }}</span></th>
                        <th class="table-th">{{ __('moderation.common.testimony') }}</th>
                        <th class="table-th">{{ __('moderation.common.author') }}</th>
                        <th class="table-th">{{ __('moderation.common.status') }}</th>
                        <th class="table-th">{{ match($tab) { 'trash' => __('moderation.index.deleted_on'), 'featured' => __('moderation.index.order_period'), default => __('moderation.common.submitted_on') } }}</th>
                        <th class="table-th text-right">{{ __('moderation.index.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    <tr class="hover:bg-slate-50">
                        <td class="table-td"><input type="checkbox" name="ids[]" value="{{ $item->id }}" form="bulk-form" class="rounded" aria-label="{{ __('moderation.index.select', ['title' => $item->title]) }}" data-bulk-item></td>
                        <td class="table-td max-w-xs">
                            <p class="truncate font-medium text-slate-900">{{ $item->title }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $item->type->label() }} · {{ $item->category_slug }} · {{ __('moderation.common.views_count', ['count' => \App\Support\Locales::number($item->views_count)]) }}</p>
                        </td>
                        <td class="table-td whitespace-nowrap">{{ $item->user?->display_name }}</td>
                        <td class="table-td">
                            <div class="flex flex-wrap gap-1.5">
                                <span class="{{ $item->status->badgeClass() }}">{{ $item->status->label() }}</span>
                                @if($item->is_verified)<span class="badge-blue" title="{{ __('moderation.common.label_title') }}"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ __('moderation.common.label') }}</span>@endif
                                @if($item->isManuallyFeatured())<span class="badge-orange"><i class="fa-solid fa-star" aria-hidden="true"></i>{{ __('moderation.index.featured_short') }}</span>@endif
                                @if($item->isConversion())<span class="badge-yellow"><i class="fa-solid fa-dove" aria-hidden="true"></i>{{ __('conversion.badge') }}</span>@endif
                            </div>
                        </td>
                        <td class="table-td whitespace-nowrap text-slate-500">
                            @if($tab === 'trash')
                                {{ $item->deleted_at->format('d/m/Y') }}
                                <span class="block text-xs">{{ \App\Services\TestimonyModeration::restorable($item) ? __('moderation.index.restorable_days', ['days' => max(0, $trashDays - (int) $item->deleted_at->diffInDays(now()))]) : __('moderation.index.purge_soon') }}</span>
                            @elseif($tab === 'featured')
                                {{ __('moderation.index.order_number', ['order' => $item->featured_order ?? '—']) }}
                                <span class="block text-xs">{{ $item->featured_from?->format('d/m/Y') ?? '…' }} → {{ $item->featured_until?->format('d/m/Y') ?? '…' }}</span>
                            @else
                                {{ $item->created_at->format('d/m/Y') }}
                            @endif
                        </td>
                        <td class="table-td">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('moderation.show', $item->id) }}" class="action-btn-view"><i class="fa-regular fa-eye"></i>{{ __('moderation.index.review') }}</a>
                                @if($tab !== 'pending' && $tab !== 'trash')
                                <a href="{{ route('moderation.stats', $item->id) }}" class="action-btn-view" title="{{ __('moderation.common.statistics') }}" aria-label="{{ __('moderation.common.statistics') }}"><i class="fa-solid fa-chart-line"></i></a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $items->links() }}</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const all = document.querySelector('[data-check-all]');
    all?.addEventListener('change', () => document.querySelectorAll('[data-bulk-item]').forEach((c) => { c.checked = all.checked; }));
    const action = document.querySelector('[data-bulk-action]');
    const toggle = () => document.querySelectorAll('[data-bulk-reject]').forEach((el) => { el.hidden = action.value !== 'reject'; });
    action?.addEventListener('change', toggle);
    if (action) toggle();
})();
</script>
@endpush

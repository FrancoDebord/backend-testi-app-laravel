@extends('layouts.app')
{{-- Back-office des témoignages (docs/fonctionnalites/gestion-des-temoignages.md) : onglets, recherche, filtres, actions groupées. --}}
@section('title', 'Témoignages — modération')
@php
    $header      = 'Témoignages';
    $subheader   = 'Modération, mise à la une et corbeille.';
    $breadcrumbs = [['label' => 'Accueil', 'url' => route('home')], ['label' => 'Modération']];
    $statCards = [
        ['En attente',              $stats['pending'],        'bg-amber-500'],
        ['Approuvés aujourd’hui',   $stats['approvedToday'],  'bg-emerald-500'],
        ['Refusés aujourd’hui',     $stats['rejectedToday'],  'bg-red-500'],
        ['Décisions ce mois',       $stats['totalThisMonth'], 'bg-slate-400'],
    ];
    $tabUrl = fn (string $t) => route('moderation.index', array_filter(['onglet' => $t, 'q' => request('q'), 'type' => request('type'), 'categorie' => request('categorie'), 'label' => request('label')]));
    $bulkActions = match ($tab) {
        'pending'  => ['approve' => 'Approuver', 'reject' => 'Refuser', 'trash' => 'Mettre à la corbeille'],
        'approved' => ['feature' => 'Mettre à la une', 'reject' => 'Refuser (dépublier)', 'trash' => 'Mettre à la corbeille'],
        'rejected' => ['approve' => 'Approuver', 'trash' => 'Mettre à la corbeille'],
        'featured' => ['unfeature' => 'Retirer de la une', 'trash' => 'Mettre à la corbeille'],
        'trash'    => ['restore' => 'Restaurer'],
    };
    $isSuper = auth()->user()->isSuperAdmin();
@endphp

@section('headerActions')
    <a href="{{ route('moderation.dashboard') }}" class="btn-secondary btn-sm"><i class="fa-solid fa-chart-line" aria-hidden="true"></i>Tableau de bord</a>
@endsection

@section('content')
<div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach($statCards as [$label, $value, $dot])
    <div class="card p-4">
        <p class="flex items-center gap-2 text-sm text-slate-500"><span class="h-2 w-2 shrink-0 rounded-full {{ $dot }}"></span>{{ $label }}</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900 tabular-nums">{{ number_format($value, 0, ',', ' ') }}</p>
    </div>
    @endforeach
</div>

<nav class="mb-4 overflow-x-auto border-b border-slate-200" aria-label="Onglets">
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
        <label for="mod-q" class="form-label">Rechercher</label>
        <input id="mod-q" type="search" name="q" value="{{ request('q') }}" class="form-input" placeholder="Titre, texte, auteur…">
    </div>
    <div>
        <label for="mod-type" class="form-label">Type</label>
        <select id="mod-type" name="type" class="form-input">
            <option value="">Tous</option>
            @foreach(\App\Enums\TestimonyType::cases() as $t)<option value="{{ $t->value }}" @selected(request('type') === $t->value)>{{ $t->label() }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="mod-cat" class="form-label">Catégorie</label>
        <select id="mod-cat" name="categorie" class="form-input">
            <option value="">Toutes</option>
            @foreach($categories as $c)<option value="{{ $c->slug }}" @selected(request('categorie') === $c->slug)>{{ $c->name }}</option>@endforeach
        </select>
    </div>
    <div class="flex flex-wrap items-end gap-3">
        <label class="flex min-h-12 items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="label" value="1" class="rounded" @checked(request('label'))>Label « approuvé »</label>
        <button type="submit" class="btn-primary btn-sm ml-auto"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>Filtrer</button>
    </div>
</form>

@if($tab === 'trash')
<p class="alert-info mb-4" role="status"><i class="fa-solid fa-circle-info mt-0.5" aria-hidden="true"></i><span>Les témoignages restent restaurables {{ $trashDays }} jours, puis sont supprimés définitivement (vidéo comprise).@unless($isSuper) La suppression immédiate est réservée aux super-administrateurs.@endunless</span></p>
@endif

@if($items->isEmpty())
    @include('components.empty-state', ['title' => 'Aucun témoignage', 'text' => 'Aucun témoignage ne correspond à cet onglet ou à ces filtres.'])
@else
    {{-- Actions groupées --}}
    <form id="bulk-form" method="POST" action="{{ route('moderation.bulk') }}" class="card mb-4 flex flex-wrap items-end gap-3 p-4" data-loading-label="Traitement…">
        @csrf
        <label class="flex min-h-10 items-center gap-2 text-sm font-medium text-slate-700"><input type="checkbox" class="rounded" data-check-all>Tout cocher</label>
        <div>
            <label for="bulk-action" class="form-label">Action</label>
            <select id="bulk-action" name="action" class="form-input" data-bulk-action>
                @foreach($bulkActions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        </div>
        @if(isset($bulkActions['reject']))
        <div data-bulk-reject hidden>
            <label for="bulk-reason" class="form-label">Motif du refus *</label>
            <select id="bulk-reason" name="reason" class="form-input">
                @foreach($reasons as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach
            </select>
        </div>
        <div class="min-w-0 flex-1" data-bulk-reject hidden>
            <label for="bulk-text" class="form-label">Précision pour l’auteur</label>
            <input id="bulk-text" type="text" name="rejection_reason" maxlength="1000" class="form-input" placeholder="Facultatif">
        </div>
        @endif
        <button type="button" class="btn-primary btn-sm"
                onclick="openConfirmModal('bulk-form', 'L’action choisie sera appliquée à tous les témoignages cochés.', 'Action groupée', 'Appliquer', 'fa-list-check')">
            Appliquer à la sélection
        </button>
        @error('ids')<p class="form-error w-full">{{ $message }}</p>@enderror
        @error('reason')<p class="form-error w-full">{{ $message }}</p>@enderror
    </form>

    {{-- Mobile : fiches --}}
    <ul class="space-y-3 md:hidden">
        @foreach($items as $item)
        <li class="card p-4">
            <div class="flex items-start gap-3">
                <input type="checkbox" name="ids[]" value="{{ $item->id }}" form="bulk-form" class="mt-1 rounded" aria-label="Sélectionner « {{ $item->title }} »" data-bulk-item>
                <div class="min-w-0 flex-1">
                    <p class="font-medium break-words text-slate-900">{{ $item->title }}</p>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <span class="{{ $item->status->badgeClass() }}">{{ $item->status->label() }}</span>
                        @if($item->is_verified)<span class="badge-blue"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Label</span>@endif
                        @if($item->isManuallyFeatured())<span class="badge-orange"><i class="fa-solid fa-star" aria-hidden="true"></i>À la une</span>@endif
                    </div>
                </div>
            </div>
            <dl class="mt-3 space-y-1 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Auteur</dt><dd class="truncate text-slate-900">{{ $item->user?->display_name }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Type</dt><dd class="text-slate-900">{{ $item->type->label() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ $tab === 'trash' ? 'Supprimé le' : 'Soumis le' }}</dt><dd class="text-slate-900">{{ ($tab === 'trash' ? $item->deleted_at : $item->created_at)->format('d/m/Y') }}</dd></div>
                @if($tab === 'featured')<div class="flex justify-between gap-4"><dt class="text-slate-500">Ordre</dt><dd class="text-slate-900">{{ $item->featured_order ?? '—' }}</dd></div>@endif
            </dl>
            <div class="mt-3 flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-3">
                <a href="{{ route('moderation.show', $item->id) }}" class="action-btn-view"><i class="fa-regular fa-eye"></i>Examiner</a>
                @if($tab !== 'pending' && $tab !== 'trash')<a href="{{ route('moderation.stats', $item->id) }}" class="action-btn-view"><i class="fa-solid fa-chart-line"></i>Statistiques</a>@endif
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
                        <th class="table-th w-10"><span class="sr-only">Sélection</span></th>
                        <th class="table-th">Témoignage</th>
                        <th class="table-th">Auteur</th>
                        <th class="table-th">Statut</th>
                        <th class="table-th">{{ match($tab) { 'trash' => 'Supprimé le', 'featured' => 'Ordre / période', default => 'Soumis le' } }}</th>
                        <th class="table-th text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    <tr class="hover:bg-slate-50">
                        <td class="table-td"><input type="checkbox" name="ids[]" value="{{ $item->id }}" form="bulk-form" class="rounded" aria-label="Sélectionner « {{ $item->title }} »" data-bulk-item></td>
                        <td class="table-td max-w-xs">
                            <p class="truncate font-medium text-slate-900">{{ $item->title }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $item->type->label() }} · {{ $item->category_slug }} · {{ number_format($item->views_count, 0, ',', ' ') }} vues</p>
                        </td>
                        <td class="table-td whitespace-nowrap">{{ $item->user?->display_name }}</td>
                        <td class="table-td">
                            <div class="flex flex-wrap gap-1.5">
                                <span class="{{ $item->status->badgeClass() }}">{{ $item->status->label() }}</span>
                                @if($item->is_verified)<span class="badge-blue" title="Label « Témoignage approuvé »"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Label</span>@endif
                                @if($item->isManuallyFeatured())<span class="badge-orange"><i class="fa-solid fa-star" aria-hidden="true"></i>Une</span>@endif
                            </div>
                        </td>
                        <td class="table-td whitespace-nowrap text-slate-500">
                            @if($tab === 'trash')
                                {{ $item->deleted_at->format('d/m/Y') }}
                                <span class="block text-xs">{{ \App\Services\TestimonyModeration::restorable($item) ? 'restaurable ' . max(0, $trashDays - (int) $item->deleted_at->diffInDays(now())) . ' j' : 'purge imminente' }}</span>
                            @elseif($tab === 'featured')
                                n° {{ $item->featured_order ?? '—' }}
                                <span class="block text-xs">{{ $item->featured_from?->format('d/m/Y') ?? '…' }} → {{ $item->featured_until?->format('d/m/Y') ?? '…' }}</span>
                            @else
                                {{ $item->created_at->format('d/m/Y') }}
                            @endif
                        </td>
                        <td class="table-td">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('moderation.show', $item->id) }}" class="action-btn-view"><i class="fa-regular fa-eye"></i>Examiner</a>
                                @if($tab !== 'pending' && $tab !== 'trash')
                                <a href="{{ route('moderation.stats', $item->id) }}" class="action-btn-view" title="Statistiques" aria-label="Statistiques"><i class="fa-solid fa-chart-line"></i></a>
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

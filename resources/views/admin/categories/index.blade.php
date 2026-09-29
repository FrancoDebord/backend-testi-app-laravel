@extends('layouts.app')
@section('title', 'Catégories')
@php
    $header      = 'Catégories';
    $subheader   = 'Catégories proposées lors de la publication d’un témoignage.';
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Administration', 'url' => route('admin.dashboard')],
        ['label' => 'Catégories'],
    ];
    // Les champs du formulaire d'ajout ne sont repris que si l'erreur vient de lui.
    $fromCreate = old('_form') === 'create';
@endphp

@section('content')

{{-- ── Ajout ─────────────────────────────────────────────────────────── --}}
<section class="card mb-6 p-5 sm:p-6">
    <h2 class="card-title mb-4">Nouvelle catégorie</h2>
    <form method="POST" action="{{ route('admin.categories.store') }}" data-loading-label="Création de la catégorie…">
        @csrf
        <input type="hidden" name="_form" value="create">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <label for="new-name" class="form-label">Nom *</label>
                <input id="new-name" type="text" name="name" value="{{ $fromCreate ? old('name') : '' }}" required maxlength="50" class="form-input">
                @if($fromCreate)@error('name')<p class="form-error">{{ $message }}</p>@enderror @endif
            </div>
            <div>
                <label for="new-slug" class="form-label">Identifiant *</label>
                <input id="new-slug" type="text" name="slug" value="{{ $fromCreate ? old('slug') : '' }}" required maxlength="30" pattern="[a-z0-9\-]+"
                       class="form-input" placeholder="guerison">
                <p class="form-hint">Minuscules, chiffres et tirets.</p>
                @if($fromCreate)@error('slug')<p class="form-error">{{ $message }}</p>@enderror @endif
            </div>
            <div>
                <label for="new-icon" class="form-label">Icône (appli mobile)</label>
                <input id="new-icon" type="text" name="icon" value="{{ $fromCreate ? old('icon') : '' }}" class="form-input">
            </div>
            <div>
                <label for="new-color" class="form-label">Couleur (appli mobile)</label>
                <input id="new-color" type="text" name="color" value="{{ $fromCreate ? old('color') : '' }}" maxlength="10" class="form-input" placeholder="#C10202">
                @if($fromCreate)@error('color')<p class="form-error">{{ $message }}</p>@enderror @endif
            </div>
        </div>
        <div class="mt-5 flex justify-end border-t border-slate-100 pt-5">
            <button type="submit" class="btn-primary">Créer la catégorie</button>
        </div>
    </form>
</section>

{{-- ── Liste ─────────────────────────────────────────────────────────── --}}
@if($categories->isEmpty())
    @include('components.empty-state', ['title' => 'Aucune catégorie', 'text' => 'Créez une première catégorie ci-dessus.'])
@else
    @foreach($categories as $cat)
    <form id="delete-cat-{{ $cat->id }}" method="POST" action="{{ route('admin.categories.destroy', $cat->id) }}" data-loading-label="Suppression…" hidden>
        @csrf @method('DELETE')
    </form>
    @endforeach

    @php
        $catEditData = fn ($cat) => [
            'action' => route('admin.categories.update', $cat->id),
            'name' => $cat->name,
            'icon' => $cat->icon,
            'color' => $cat->color,
            'display_order' => $cat->display_order,
            'is_active' => (bool) $cat->is_active,
        ];
    @endphp

    {{-- Mobile : fiches --}}
    <ul class="space-y-3 md:hidden">
        @foreach($categories as $cat)
        <li class="card p-4">
            <div class="flex items-start justify-between gap-3">
                <p class="min-w-0 font-medium break-words text-slate-900">{{ $cat->name }}</p>
                <span class="{{ $cat->is_active ? 'badge-active' : 'badge-draft' }}">{{ $cat->is_active ? 'Active' : 'Inactive' }}</span>
            </div>
            <dl class="mt-3 space-y-1 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Identifiant</dt><dd class="truncate font-mono text-xs text-slate-900">{{ $cat->slug }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Ordre</dt><dd class="text-slate-900">{{ $cat->display_order }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Témoignages</dt><dd class="text-slate-900">{{ number_format($cat->testimony_count, 0, ',', ' ') }}</dd></div>
            </dl>
            <div class="mt-3 flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-3">
                <button type="button" class="action-btn-edit" data-category-edit='@json($catEditData($cat))'><i class="fa-solid fa-pen"></i>Modifier</button>
                <button type="button" class="action-btn-delete"
                        onclick="openConfirmModal('delete-cat-{{ $cat->id }}', @js('La catégorie « ' . $cat->name . ' » sera supprimée définitivement.'), 'Supprimer la catégorie', 'Supprimer', 'fa-trash-can')">
                    <i class="fa-regular fa-trash-can"></i>Supprimer
                </button>
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
                        <th class="table-th">Ordre</th>
                        <th class="table-th">Catégorie</th>
                        <th class="table-th">Identifiant</th>
                        <th class="table-th text-right">Témoignages</th>
                        <th class="table-th">Statut</th>
                        <th class="table-th text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categories as $cat)
                    <tr class="hover:bg-slate-50">
                        <td class="table-td text-slate-500">{{ $cat->display_order }}</td>
                        <td class="table-td font-medium text-slate-900">{{ $cat->name }}</td>
                        <td class="table-td font-mono text-xs text-slate-500">{{ $cat->slug }}</td>
                        <td class="table-td text-right">{{ number_format($cat->testimony_count, 0, ',', ' ') }}</td>
                        <td class="table-td"><span class="{{ $cat->is_active ? 'badge-active' : 'badge-draft' }}">{{ $cat->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="table-td">
                            <div class="flex justify-end gap-2">
                                <button type="button" class="action-btn-edit" title="Modifier" aria-label="Modifier" data-category-edit='@json($catEditData($cat))'>
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="button" class="action-btn-delete" title="Supprimer" aria-label="Supprimer"
                                        onclick="openConfirmModal('delete-cat-{{ $cat->id }}', @js('La catégorie « ' . $cat->name . ' » sera supprimée définitivement.'), 'Supprimer la catégorie', 'Supprimer', 'fa-trash-can')">
                                    <i class="fa-regular fa-trash-can"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $categories->links() }}</div>
@endif

{{-- ── Modale de modification ─────────────────────────────────────────── --}}
<div id="category-edit-modal" data-modal class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="category-edit-title" hidden>
    <div class="absolute inset-0 bg-slate-900/50" data-modal-close></div>
    <form id="category-edit-form" method="POST" action="" class="card relative w-full max-w-lg shadow-xl" data-loading-label="Enregistrement…">
        @csrf @method('PUT')
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
            <h2 id="category-edit-title" class="text-base font-semibold text-primary-600">Modifier la catégorie</h2>
            <button type="button" class="btn-ghost btn-sm" data-modal-close aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="edit-name" class="form-label">Nom *</label>
                <input id="edit-name" type="text" name="name" required maxlength="50" class="form-input">
            </div>
            <div>
                <label for="edit-icon" class="form-label">Icône (appli mobile)</label>
                <input id="edit-icon" type="text" name="icon" class="form-input">
            </div>
            <div>
                <label for="edit-color" class="form-label">Couleur (appli mobile)</label>
                <input id="edit-color" type="text" name="color" maxlength="10" class="form-input">
            </div>
            <div>
                <label for="edit-order" class="form-label">Ordre d’affichage</label>
                <input id="edit-order" type="number" name="display_order" class="form-input">
            </div>
            <label class="flex items-center gap-2 self-end pb-2 text-sm text-slate-700">
                <input id="edit-active" type="checkbox" name="is_active" value="1" class="rounded">
                Catégorie active
            </label>
        </div>
        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <button type="button" class="btn-secondary" data-modal-close>Annuler</button>
            <button type="submit" class="btn-primary">Enregistrer</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-category-edit]');
    if (!btn) return;
    const data = JSON.parse(btn.dataset.categoryEdit);
    const form = document.getElementById('category-edit-form');
    form.action = data.action;
    document.getElementById('edit-name').value = data.name ?? '';
    document.getElementById('edit-icon').value = data.icon ?? '';
    document.getElementById('edit-color').value = data.color ?? '';
    document.getElementById('edit-order').value = data.display_order ?? '';
    document.getElementById('edit-active').checked = !!data.is_active;
    window.openModal('category-edit-modal');
});
</script>
@endpush

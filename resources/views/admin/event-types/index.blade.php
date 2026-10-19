@extends('layouts.app')
@section('title', __('event_types.admin.title'))
@php
    // Types d'événements gérés par les administrateurs ─ docs/fonctionnalites/types-evenements.md
    $header      = __('event_types.admin.title');
    $subheader   = __('event_types.admin.subtitle');
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.administration'), 'url' => route('admin.dashboard')],
        ['label' => __('event_types.admin.title')],
    ];
    // Champs du formulaire d'ajout repris seulement si l'erreur vient de lui.
    $fromCreate  = old('_form') === 'create';
    $actionError = $errors->first('is_active') ?: ($errors->first('event_type') ?: ($errors->first('replace_with') ?: $errors->first('ids')));
    $activeTypes = $types->where('is_active', true);
    $typeDeleteData = fn ($t, $count) => [
        'action'  => route('admin.event-types.destroy', $t->id),
        'slug'    => $t->slug,
        'count'   => $count,
        'message' => __('event_types.admin.delete_confirm', ['name' => $t->name_fr]),
        'inUse'   => $count > 0 ? trans_choice('event_types.admin.delete_in_use', $count, ['count' => $count]) : null,
    ];
    $typeEditData = fn ($t) => [
        'action'    => route('admin.event-types.update', $t->id),
        'name_fr'   => $t->name_fr,
        'name_en'   => $t->name_en,
        'icon'      => $t->icon,
        'slug'      => $t->slug,
        'is_active' => (bool) $t->is_active,
    ];
@endphp

@section('content')

@if($actionError && !$fromCreate)
<div class="alert-error mb-6" role="alert">
    <i class="fa-solid fa-circle-exclamation mt-0.5" aria-hidden="true"></i>
    <p class="min-w-0 flex-1">{{ $actionError }}</p>
</div>
@endif

{{-- ── Ajout ─────────────────────────────────────────────────────────── --}}
<section class="card mb-6 p-5 sm:p-6">
    <h2 class="card-title mb-4">{{ __('event_types.admin.new') }}</h2>
    <form method="POST" action="{{ route('admin.event-types.store') }}" data-loading-label="{{ __('event_types.admin.creating') }}">
        @csrf
        <input type="hidden" name="_form" value="create">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label for="new-name-fr" class="form-label">{{ __('event_types.admin.name_fr') }} *</label>
                <input id="new-name-fr" type="text" name="name_fr" value="{{ $fromCreate ? old('name_fr') : '' }}" required maxlength="80" class="form-input">
                @if($fromCreate)@error('name_fr')<p class="form-error">{{ $message }}</p>@enderror @endif
            </div>
            <div>
                <label for="new-name-en" class="form-label">{{ __('event_types.admin.name_en') }}</label>
                <input id="new-name-en" type="text" name="name_en" value="{{ $fromCreate ? old('name_en') : '' }}" maxlength="80" class="form-input">
                <p class="form-hint">{{ __('event_types.admin.name_en_hint') }}</p>
                @if($fromCreate)@error('name_en')<p class="form-error">{{ $message }}</p>@enderror @endif
            </div>
            <div>
                <label for="new-slug" class="form-label">{{ __('event_types.admin.slug') }}</label>
                <input id="new-slug" type="text" name="slug" value="{{ $fromCreate ? old('slug') : '' }}" maxlength="40" pattern="[a-z0-9\-]+" class="form-input font-mono">
                <p class="form-hint">{{ __('event_types.admin.slug_hint') }}</p>
                @if($fromCreate)@error('slug')<p class="form-error">{{ $message }}</p>@enderror @endif
            </div>
        </div>
        <fieldset class="mt-4">
            <legend class="form-label">{{ __('event_types.admin.icon') }}</legend>
            @include('admin.event-types.partials.icon-picker', ['prefix' => 'new', 'selected' => $fromCreate ? old('icon', 'calendar') : 'calendar'])
            @if($fromCreate)@error('icon')<p class="form-error">{{ $message }}</p>@enderror @endif
        </fieldset>
        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-5">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="rounded" @checked(!$fromCreate || old('is_active', '1') === '1')>
                {{ __('event_types.admin.active') }}
            </label>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>{{ __('event_types.admin.create') }}</button>
        </div>
    </form>
</section>

{{-- ── Liste (glisser-déposer ou flèches) ─────────────────────────────── --}}
@if($types->isEmpty())
    @include('components.empty-state', ['title' => __('event_types.admin.empty'), 'text' => ''])
@else
    <p class="mb-3 text-sm text-slate-500"><i class="fa-solid fa-grip-vertical mr-1" aria-hidden="true"></i>{{ __('event_types.admin.reorder_hint') }}</p>

    @foreach($types as $t)
    <form id="toggle-type-{{ $t->id }}" method="POST" action="{{ route('admin.event-types.toggle', $t->id) }}" hidden>@csrf</form>
    <form id="up-type-{{ $t->id }}" method="POST" action="{{ route('admin.event-types.move', $t->id) }}" hidden>@csrf<input type="hidden" name="direction" value="up"></form>
    <form id="down-type-{{ $t->id }}" method="POST" action="{{ route('admin.event-types.move', $t->id) }}" hidden>@csrf<input type="hidden" name="direction" value="down"></form>
    @endforeach

    <ul id="event-types-list" class="card divide-y divide-slate-100 overflow-hidden" data-reorder-url="{{ route('admin.event-types.reorder') }}">
        @foreach($types as $t)
        @php $count = $counts[$t->slug] ?? 0; @endphp
        <li class="flex flex-wrap items-center gap-3 bg-white px-4 py-3 hover:bg-slate-50" draggable="true" data-type-id="{{ $t->id }}">
            <span class="cursor-grab text-slate-400" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600">
                <i class="fa-solid {{ $t->faIcon() }}" aria-hidden="true"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-medium break-words text-slate-900">{{ $t->name_fr }}</p>
                <p class="text-xs text-slate-500">
                    @if($t->name_en)<span>{{ $t->name_en }}</span> · @endif
                    <span class="font-mono">{{ $t->slug }}</span> ·
                    <span>{{ __('event_types.admin.events') }} : {{ number_format($count, 0, ',', ' ') }}</span>
                </p>
            </div>
            <span class="{{ $t->is_active ? 'badge-active' : 'badge-draft' }}">{{ $t->is_active ? __('event_types.admin.active') : __('event_types.admin.inactive') }}</span>
            <div class="flex flex-wrap justify-end gap-2">
                <button type="submit" form="up-type-{{ $t->id }}" class="btn-ghost btn-sm" title="{{ __('event_types.admin.move_up') }}" aria-label="{{ __('event_types.admin.move_up') }}" @disabled($loop->first)>
                    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
                </button>
                <button type="submit" form="down-type-{{ $t->id }}" class="btn-ghost btn-sm" title="{{ __('event_types.admin.move_down') }}" aria-label="{{ __('event_types.admin.move_down') }}" @disabled($loop->last)>
                    <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
                </button>
                <button type="submit" form="toggle-type-{{ $t->id }}" class="btn-secondary btn-sm">
                    <i class="fa-solid {{ $t->is_active ? 'fa-eye-slash' : 'fa-eye' }}" aria-hidden="true"></i>{{ $t->is_active ? __('event_types.admin.deactivate') : __('event_types.admin.activate') }}
                </button>
                <button type="button" class="action-btn-edit" title="{{ __('event_types.admin.edit') }}" aria-label="{{ __('event_types.admin.edit') }}" data-event-type-edit='@json($typeEditData($t))'>
                    <i class="fa-solid fa-pen" aria-hidden="true"></i>
                </button>
                <button type="button" class="action-btn-delete" title="{{ __('event_types.admin.delete') }}" aria-label="{{ __('event_types.admin.delete') }}"
                        data-event-type-delete='@json($typeDeleteData($t, $count))'>
                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                </button>
            </div>
        </li>
        @endforeach
    </ul>
@endif

{{-- ── Modale de modification ─────────────────────────────────────────── --}}
<div id="event-type-edit-modal" data-modal class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="event-type-edit-title" hidden>
    <div class="absolute inset-0 bg-slate-900/50" data-modal-close></div>
    <form id="event-type-edit-form" method="POST" action="" class="card relative max-h-full w-full max-w-lg overflow-y-auto shadow-xl" data-loading-label="{{ __('event_types.admin.saving') }}">
        @csrf @method('PUT')
        <input type="hidden" name="_form" value="edit">
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
            <h2 id="event-type-edit-title" class="text-base font-semibold text-primary-600">{{ __('event_types.admin.edit_title') }}</h2>
            <button type="button" class="btn-ghost btn-sm" data-modal-close aria-label="{{ __('layout.close') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="space-y-4 px-5 py-4">
            <div>
                <label for="edit-name-fr" class="form-label">{{ __('event_types.admin.name_fr') }} *</label>
                <input id="edit-name-fr" type="text" name="name_fr" required maxlength="80" class="form-input">
            </div>
            <div>
                <label for="edit-name-en" class="form-label">{{ __('event_types.admin.name_en') }}</label>
                <input id="edit-name-en" type="text" name="name_en" maxlength="80" class="form-input">
                <p class="form-hint">{{ __('event_types.admin.name_en_hint') }}</p>
            </div>
            <p class="text-sm text-slate-500">{{ __('event_types.admin.slug') }} : <span id="edit-slug" class="font-mono text-slate-900"></span></p>
            <fieldset>
                <legend class="form-label">{{ __('event_types.admin.icon') }}</legend>
                @include('admin.event-types.partials.icon-picker', ['prefix' => 'edit', 'selected' => null])
            </fieldset>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input id="edit-active" type="checkbox" name="is_active" value="1" class="rounded">
                {{ __('event_types.admin.active') }}
            </label>
        </div>
        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <button type="button" class="btn-secondary" data-modal-close>{{ __('event_types.admin.cancel') }}</button>
            <button type="submit" class="btn-primary">{{ __('event_types.admin.save') }}</button>
        </div>
    </form>
</div>

{{-- ── Modale de suppression (avec « Remplacer par » si des événements l'utilisent) ── --}}
<div id="event-type-delete-modal" data-modal class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="event-type-delete-title" hidden>
    <div class="absolute inset-0 bg-slate-900/50" data-modal-close></div>
    <form id="event-type-delete-form" method="POST" action="" class="card relative w-full max-w-md shadow-xl" data-loading-label="{{ __('event_types.admin.deleting') }}">
        @csrf @method('DELETE')
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
            <h2 id="event-type-delete-title" class="text-base font-semibold text-primary-600">{{ __('event_types.admin.delete_title') }}</h2>
            <button type="button" class="btn-ghost btn-sm" data-modal-close aria-label="{{ __('layout.close') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="space-y-3 px-5 py-4 text-sm text-slate-700">
            <p id="event-type-delete-message"></p>
            <div id="event-type-delete-replace" hidden>
                <p id="event-type-delete-in-use" class="mb-2 font-medium text-slate-900"></p>
                <label for="replace-with" class="form-label">{{ __('event_types.admin.replace_with') }} *</label>
                <select id="replace-with" name="replace_with" class="form-input">
                    @foreach($activeTypes as $t)
                    <option value="{{ $t->slug }}">{{ $t->name_fr }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <button type="button" class="btn-secondary" data-modal-close>{{ __('event_types.admin.cancel') }}</button>
            <button type="submit" class="btn-primary"><i class="fa-regular fa-trash-can" aria-hidden="true"></i>{{ __('event_types.admin.delete') }}</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Modifier : remplit la modale.
    document.addEventListener('click', function (e) {
        const edit = e.target.closest('[data-event-type-edit]');
        if (edit) {
            const data = JSON.parse(edit.dataset.eventTypeEdit);
            document.getElementById('event-type-edit-form').action = data.action;
            document.getElementById('edit-name-fr').value = data.name_fr ?? '';
            document.getElementById('edit-name-en').value = data.name_en ?? '';
            document.getElementById('edit-slug').textContent = data.slug;
            document.getElementById('edit-active').checked = !!data.is_active;
            const radio = document.querySelector('#event-type-edit-form input[name="icon"][value="' + (data.icon || 'calendar') + '"]');
            if (radio) radio.checked = true;
            window.openModal('event-type-edit-modal');
            return;
        }
        // Supprimer : choix du remplaçant si des événements utilisent le type.
        const del = e.target.closest('[data-event-type-delete]');
        if (del) {
            const data = JSON.parse(del.dataset.eventTypeDelete);
            const form = document.getElementById('event-type-delete-form');
            form.action = data.action;
            document.getElementById('event-type-delete-message').textContent = data.message;
            const replace = document.getElementById('event-type-delete-replace');
            const select = document.getElementById('replace-with');
            replace.hidden = !data.inUse;
            select.disabled = !data.inUse;
            [...select.options].forEach(o => { o.hidden = o.disabled = o.value === data.slug; });
            const first = [...select.options].find(o => !o.disabled);
            if (first) select.value = first.value;
            document.getElementById('event-type-delete-in-use').textContent = data.inUse || '';
            window.openModal('event-type-delete-modal');
        }
    });

    // Glisser-déposer : enregistre le nouvel ordre (PUT JSON), puis recharge la page.
    const list = document.getElementById('event-types-list');
    if (!list) return;
    let dragged = null;
    list.addEventListener('dragstart', e => {
        dragged = e.target.closest('[data-type-id]');
        if (dragged) { dragged.classList.add('opacity-50'); e.dataTransfer.effectAllowed = 'move'; }
    });
    list.addEventListener('dragover', e => {
        if (!dragged) return;
        e.preventDefault();
        const over = e.target.closest('[data-type-id]');
        if (!over || over === dragged) return;
        const rect = over.getBoundingClientRect();
        list.insertBefore(dragged, (e.clientY - rect.top) > rect.height / 2 ? over.nextSibling : over);
    });
    list.addEventListener('dragend', () => {
        if (!dragged) return;
        dragged.classList.remove('opacity-50');
        dragged = null;
        const ids = [...list.querySelectorAll('[data-type-id]')].map(li => parseInt(li.dataset.typeId, 10));
        fetch(list.dataset.reorderUrl, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json', 'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ ids }),
        }).then(() => window.location.reload());
    });
})();
</script>
@endpush

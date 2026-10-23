{{-- Suppression définitive d'un témoignage de la corbeille (onglet Corbeille, super-administrateur). Attend $item, $formId.
     docs/fonctionnalites/gestion-des-temoignages.md --}}
@if($item->trashed())
@can('forceDelete', $item)
<form id="{{ $formId }}" method="POST" action="{{ route('moderation.force-delete', $item->id) }}" data-loading-label="{{ __('moderation.index.processing') }}">
    @csrf @method('DELETE')
    <button type="button" class="action-btn-delete" title="{{ __('moderation.show.force_delete') }}" aria-label="{{ __('moderation.index.force_delete_item', ['title' => $item->title]) }}"
            onclick="openConfirmModal(@js($formId), @js(__('moderation.index.force_delete_item_confirm', ['title' => $item->title])), @js(__('moderation.show.force_delete')), @js(__('moderation.show.force_delete')), 'fa-trash')">
        <i class="fa-solid fa-trash" aria-hidden="true"></i>{{ __('moderation.index.force_delete_short') }}
    </button>
</form>
@endcan
@endif

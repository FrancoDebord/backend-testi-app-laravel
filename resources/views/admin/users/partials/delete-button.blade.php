{{-- Suppression définitive d'un compte depuis la liste (super-administrateurs). Attend $user, $formId, $canDeleteUsers. --}}
@if($canDeleteUsers && auth()->id() !== $user->id && \App\Services\AccountDeletion::allowed($user))
<form id="{{ $formId }}" method="POST" action="{{ route('admin.users.destroy', $user->id) }}" data-loading-label="{{ __('admin.common.deleting') }}">
    @csrf @method('DELETE')
    <button type="button" class="action-btn-delete" title="{{ __('admin.common.delete') }}" aria-label="{{ __('admin.users.delete_aria', ['name' => $user->display_name]) }}"
            onclick="openConfirmModal(@js($formId), @js(__('admin.users.delete_confirm', ['name' => $user->display_name, 'count' => $user->testimony_count])), @js(__('admin.users.delete_title')), @js(__('admin.common.delete_permanently')), 'fa-trash-can')">
        <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
    </button>
</form>
@endif

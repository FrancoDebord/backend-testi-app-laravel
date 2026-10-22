{{-- Suppression définitive d'un compte depuis la liste (super-administrateurs). Attend $user, $formId, $canDeleteUsers. --}}
@if($canDeleteUsers && auth()->id() !== $user->id && \App\Services\AccountDeletion::allowed($user))
<form id="{{ $formId }}" method="POST" action="{{ route('admin.users.destroy', $user->id) }}" data-loading-label="Suppression…">
    @csrf @method('DELETE')
    <button type="button" class="action-btn-delete" title="Supprimer" aria-label="Supprimer le compte de {{ $user->display_name }}"
            onclick="openConfirmModal(@js($formId), @js('Le compte « ' . $user->display_name . ' » et ses ' . $user->testimony_count . ' témoignage(s) seront supprimés définitivement, avec toutes ses données. Cette action est irréversible.'), 'Supprimer ce compte', 'Supprimer définitivement', 'fa-trash-can')">
        <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
    </button>
</form>
@endif

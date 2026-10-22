{{-- Approuver directement un témoignage rejeté (listes de modération et Administration › Contenu). Attend $item, $formId.
     docs/fonctionnalites/gestion-des-temoignages.md --}}
@if($item->status === \App\Enums\TestimonyStatus::Rejected)
@can('moderate', $item)
<form id="{{ $formId }}" method="POST" action="{{ route('moderation.approve', $item->id) }}" data-loading-label="{{ __('moderation.show.publishing') }}">
    @csrf
    <input type="hidden" name="return" value="back">
    <button type="button" class="action-btn-success"
            onclick="openConfirmModal(@js($formId), @js(__('moderation.index.approve_rejected', ['title' => $item->title])), @js(__('moderation.show.approve_confirm_title')), @js(__('moderation.show.approve_publish')), 'fa-check')">
        <i class="fa-solid fa-check" aria-hidden="true"></i>{{ __('moderation.index.approve') }}
    </button>
</form>
@endcan
@endif

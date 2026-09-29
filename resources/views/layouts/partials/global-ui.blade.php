{{-- Messages éphémères (window.flash) --}}
<div id="toast-stack" class="pointer-events-none fixed inset-x-4 top-20 z-[60] ml-auto flex max-w-sm flex-col gap-2" aria-live="polite"></div>

{{-- Modale de confirmation globale : openConfirmModal(formId, message, titre, libellé, icône) --}}
<div id="confirm-modal" data-modal class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title" hidden>
    <div class="absolute inset-0 bg-slate-900/50" data-modal-close></div>
    <div class="card relative w-full max-w-md p-6 shadow-xl">
        <div class="flex items-start gap-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                <i id="confirm-modal-icon" class="fa-solid fa-circle-question"></i>
            </span>
            <div class="min-w-0">
                <h2 id="confirm-modal-title" class="text-base font-semibold text-primary-600">Confirmer</h2>
                <p id="confirm-modal-message" class="mt-1 text-sm text-slate-600"></p>
            </div>
        </div>
        <div class="mt-6 flex flex-wrap justify-end gap-2">
            <button type="button" class="btn-secondary" data-modal-close>Annuler</button>
            <button type="button" id="confirm-modal-submit" class="btn-primary">
                <span id="confirm-modal-label">Confirmer</span>
            </button>
        </div>
    </div>
</div>

{{-- Indicateur de chargement des formulaires POST --}}
<div id="loading-overlay" class="fixed inset-0 z-[70] flex items-center justify-center bg-white/70 p-4" role="status" aria-live="assertive" hidden>
    <div class="card flex items-center gap-3 px-5 py-4 text-sm font-medium text-slate-700">
        <span class="spinner text-primary-600"></span>
        <span id="loading-overlay-label">Traitement en cours…</span>
    </div>
</div>

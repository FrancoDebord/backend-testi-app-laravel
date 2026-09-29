{{-- Qui regarde le direct (liste visible de tous), remplie par live.js depuis lives.viewers. --}}
<div id="live-viewers-modal" data-modal class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="live-viewers-title" hidden>
    <div class="absolute inset-0 bg-slate-900/50" data-modal-close></div>
    <div class="card relative flex max-h-[80vh] w-full max-w-md flex-col shadow-xl">
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
            <h2 id="live-viewers-title" class="text-base font-semibold text-primary-600">
                Qui regarde <span class="font-normal text-slate-500" data-live-viewers-total></span>
            </h2>
            <button type="button" class="btn-ghost btn-sm" data-modal-close aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <ul class="min-h-24 flex-1 divide-y divide-slate-100 overflow-y-auto" data-live-viewers-list aria-live="polite">
            <li class="px-5 py-6 text-center text-sm text-slate-500">Chargement…</li>
        </ul>
        <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500" data-live-viewers-anonymous hidden></p>
    </div>
</div>

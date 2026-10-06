{{--
    Modales « Reporter » et « Annuler l'événement » (gestionnaires) de la page d'un événement.
    @include('events.partials.status-modals', ['event' => $event]) — ouvertes par [data-modal-open].
--}}
@php
    $postponeErrors = $errors->hasAny(['starts_at', 'ends_at', 'date_tbd']);
    $tbdChecked     = old('date_tbd') ? true : false;
@endphp
<div id="postpone-modal" data-modal class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="postpone-modal-title" @unless($postponeErrors) hidden @endunless>
    <div class="absolute inset-0 bg-slate-900/50" data-modal-close></div>
    <form method="POST" action="{{ route('events.postpone', $event->id) }}" class="card relative max-h-full w-full max-w-lg overflow-y-auto shadow-xl" data-loading-label="Enregistrement…">
        @csrf
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
            <h2 id="postpone-modal-title" class="text-base font-semibold text-primary-600">Reporter l'événement</h2>
            <button type="button" class="btn-ghost btn-sm" data-modal-close aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="space-y-4 px-5 py-4">
            <p class="text-sm text-slate-600">Les personnes qui participent sont prévenues. La date initiale reste affichée, barrée.</p>
            <label class="flex items-start gap-2 text-sm text-slate-700">
                <input type="checkbox" name="date_tbd" value="1" class="mt-1" @checked($tbdChecked)
                       onchange="document.getElementById('postpone-dates').hidden = this.checked; document.querySelectorAll('#postpone-dates input').forEach(i => i.required = !this.checked);">
                <span><strong>Date à confirmer</strong> — la nouvelle date sera annoncée plus tard ; les réponses « Je participe » sont conservées.</span>
            </label>
            <div id="postpone-dates" class="grid grid-cols-1 gap-3 sm:grid-cols-2" @if($tbdChecked) hidden @endif>
                <div>
                    <label for="postpone-start" class="form-label">Nouveau début</label>
                    <input id="postpone-start" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="form-input" @unless($tbdChecked) required @endunless @error('starts_at') aria-invalid="true" @enderror>
                    @error('starts_at')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="postpone-end" class="form-label">Nouvelle fin</label>
                    <input id="postpone-end" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="form-input" @unless($tbdChecked) required @endunless @error('ends_at') aria-invalid="true" @enderror>
                    @error('ends_at')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label for="postpone-reason" class="form-label">Motif (facultatif)</label>
                <input id="postpone-reason" type="text" name="reason" maxlength="500" value="{{ old('reason') }}" class="form-input" placeholder="Ex. : travaux au stade, intempéries…">
            </div>
        </div>
        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <button type="button" class="btn-secondary" data-modal-close>Fermer</button>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i>Reporter</button>
        </div>
    </form>
</div>

<div id="cancel-modal" data-modal class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="cancel-modal-title" hidden>
    <div class="absolute inset-0 bg-slate-900/50" data-modal-close></div>
    <form method="POST" action="{{ route('events.cancel', $event->id) }}" class="card relative w-full max-w-lg shadow-xl" data-loading-label="Annulation…">
        @csrf
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
            <h2 id="cancel-modal-title" class="text-base font-semibold text-error-700">Annuler l'événement</h2>
            <button type="button" class="btn-ghost btn-sm" data-modal-close aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="space-y-4 px-5 py-4">
            <p class="text-sm text-slate-600">La page reste visible avec la mention « Annulé » ; plus de réponse possible. Les personnes qui participent sont prévenues.</p>
            <div>
                <label for="cancel-reason" class="form-label">Motif (facultatif)</label>
                <input id="cancel-reason" type="text" name="reason" maxlength="500" class="form-input" placeholder="Ex. : raisons de sécurité">
            </div>
        </div>
        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <button type="button" class="btn-secondary" data-modal-close>Fermer</button>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-ban" aria-hidden="true"></i>Annuler l'événement</button>
        </div>
    </form>
</div>

{{--
    Écran « Temps de prière » (resources/js/prayer-focus.js), une seule fois par page (layouts/partials/global-ui).
    « Je prie » (requête de prière, témoignage, verset du jour, parole prophétique…) : décompte de 5 min, puis « J'ai prié… » active « Valider ma prière ».
    Le serveur compte aussi le temps (App\Services\PrayerFocus). Voir docs/fonctionnalites/temps-de-priere.md
--}}
<div id="prayer-focus" class="fixed inset-0 z-[65] overflow-y-auto bg-gradient-to-b from-[#0b1f4d] via-[#12306f] to-[#1d4fa3] text-white"
     role="dialog" aria-modal="true" aria-labelledby="prayer-focus-title" hidden
     data-label-request="{{ __('prayer_focus.modal.for_request') }}"
     data-label-testimony="{{ __('prayer_focus.modal.for_testimony') }}"
     data-confirm-request="{{ __('prayer_focus.modal.confirm_request') }}"
     data-confirm-testimony="{{ __('prayer_focus.modal.confirm_testimony') }}"
     data-label-verse="{{ __('prayer_focus.modal.for_verse') }}"
     data-confirm-verse="{{ __('prayer_focus.modal.confirm_verse') }}"
     data-label-prophecy="{{ __('prayer_focus.modal.for_prophecy') }}"
     data-confirm-prophecy="{{ __('prayer_focus.modal.confirm_prophecy') }}"
     data-label-event="{{ __('prayer_focus.modal.for_event') }}"
     data-confirm-event="{{ __('prayer_focus.modal.confirm_event') }}"
     data-label-session="{{ __('prayer_focus.modal.for_session') }}"
     data-confirm-session="{{ __('prayer_focus.modal.confirm_session') }}"
     data-validating="{{ __('prayer_focus.modal.validating') }}"
     data-validate="{{ __('prayer_focus.modal.validate') }}">
    <div class="mx-auto flex min-h-full w-full max-w-xl flex-col px-5 py-6 sm:py-10">
        <div class="flex items-center justify-between gap-3">
            <h2 id="prayer-focus-title" class="flex items-center gap-2 text-lg font-semibold">
                <i class="fa-solid fa-hands-praying text-sky-200" aria-hidden="true"></i>{{ __('prayer_focus.modal.title') }}
            </h2>
            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                    data-prayer-focus-close aria-label="{{ __('prayer_focus.modal.close') }}" title="{{ __('prayer_focus.modal.close') }}">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <p class="mt-1 text-sm text-sky-100/80">{{ __('prayer_focus.modal.subtitle') }}</p>

        <section class="mt-6 rounded-2xl bg-white/10 p-4 ring-1 ring-white/15 sm:p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-200" data-prayer-focus-label></p>
            <p class="mt-2 text-base font-semibold leading-snug break-words" data-prayer-focus-subject></p>
            <p class="mt-1 text-sm leading-relaxed whitespace-pre-line break-words text-sky-50/90" data-prayer-focus-excerpt></p>
        </section>

        <div class="mt-8 flex flex-col items-center">
            <div class="relative h-56 w-56 sm:h-64 sm:w-64">
                <svg class="h-full w-full -rotate-90" viewBox="0 0 120 120" aria-hidden="true">
                    <circle cx="60" cy="60" r="54" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="6"></circle>
                    <circle cx="60" cy="60" r="54" fill="none" stroke="#bae6fd" stroke-width="6" stroke-linecap="round"
                            stroke-dasharray="339.292" stroke-dashoffset="0" data-prayer-focus-ring style="transition: stroke-dashoffset 1s linear"></circle>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-5xl font-semibold tabular-nums sm:text-6xl" data-prayer-focus-time role="timer" aria-live="off">05:00</span>
                    <span class="mt-1 text-xs uppercase tracking-wide text-sky-100/80" data-prayer-focus-remaining>{{ __('prayer_focus.modal.remaining') }}</span>
                </div>
            </div>
            <p class="mt-3 min-h-5 text-center text-sm text-amber-200" data-prayer-focus-paused hidden>{{ __('prayer_focus.modal.paused') }}</p>
            <p class="mt-3 text-center text-sm font-medium text-emerald-200" data-prayer-focus-done hidden>{{ __('prayer_focus.modal.done') }}</p>
        </div>

        <blockquote class="mt-6 text-center text-sm italic leading-relaxed text-sky-50/90">
            {{ __('prayer_focus.modal.verse_text') }}
            <footer class="mt-1 text-xs font-semibold not-italic text-sky-200">{{ __('prayer_focus.modal.verse_ref') }}</footer>
        </blockquote>

        <div class="mt-auto pt-8">
            <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-white/10 p-3 ring-1 ring-white/15" data-prayer-focus-check-row hidden>
                <input type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-white/40 text-sky-500 focus:ring-sky-300" data-prayer-focus-check>
                <span class="text-sm font-medium" data-prayer-focus-check-label></span>
            </label>
            <button type="button" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-[#12306f] shadow-lg transition disabled:cursor-not-allowed disabled:opacity-50"
                    data-prayer-focus-submit disabled>
                <i class="fa-solid fa-check" aria-hidden="true"></i><span data-prayer-focus-submit-label>{{ __('prayer_focus.modal.validate') }}</span>
            </button>
            <p class="mt-2 text-center text-xs text-sky-100/70" data-prayer-focus-hint>{{ __('prayer_focus.modal.wait_hint') }}</p>
            <button type="button" class="mt-3 w-full text-center text-sm text-sky-100/80 underline-offset-2 hover:underline" data-prayer-focus-close>
                {{ __('prayer_focus.modal.close') }}
            </button>
        </div>
    </div>
</div>

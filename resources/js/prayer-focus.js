/*
|--------------------------------------------------------------------------
| Temps de prière (docs/fonctionnalites/temps-de-priere.md)
|--------------------------------------------------------------------------
| « Je prie » qui AJOUTE une prière (requête de prière ou témoignage) :
|   1. POST de la route « commencer » (le serveur note l'heure de début) ;
|   2. écran plein « Temps de prière » (components/prayer-focus-modal) : décompte de requiredSeconds
|      (5 min), en pause quand l'onglet est caché ; ensuite « J'ai prié… » active « Valider ma prière » ;
|   3. « Valider » : la requête d'origine part (formulaire ou fetch), le serveur vérifie le temps écoulé.
| Fermer l'écran = partir sans enregistrer. Retirer sa prière reste immédiat (pas d'écran).
|
| Formulaires : <form data-prayer-focus="URL de début" data-prayed="0|1" data-prayer-subject="…"
|   data-prayer-focus-kind="prayer_request|daily_verse|prophecy"> (requêtes de prière, « Je prie » du verset
|   du jour, « J'ai prié » d'une parole prophétique ; prayer_request par défaut).
| Témoignages : resources/js/videos.js appelle prayerFocus({ startUrl, kind: 'testimony', subject, excerpt }).
*/
import { t } from './i18n';

const RING = 339.292; // 2πr (r = 54)
let active = null;    // une seule séance à la fois

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function startOnServer(url) {
    let response;
    try {
        response = await fetch(url, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
            credentials: 'same-origin',
        });
    } catch {
        throw new Error(navigator.onLine ? t('videos.errors.no_response') : t('videos.errors.offline'));
    }
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload?.success) {
        if ([401, 419, 429].includes(response.status)) throw new Error(t(`videos.errors.${response.status}`));
        throw new Error(payload?.message || t('videos.errors.failed'));
    }
    return payload.data;
}

function format(seconds) {
    const s = Math.max(0, Math.ceil(seconds));
    return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
}

/**
 * Ouvre le temps de prière. Résout true quand la personne valide (temps écoulé + case cochée),
 * false si elle ferme l'écran ou si le début est refusé (message affiché).
 */
export async function prayerFocus({ startUrl, kind = 'prayer_request', subject = '', excerpt = '' }) {
    const modal = document.getElementById('prayer-focus');
    if (!modal || !startUrl) return true; // page sans écran (invité) : comportement d'avant
    if (active) return false;

    let data;
    try {
        data = await startOnServer(startUrl);
    } catch (error) {
        window.flash?.(error.message, 'error');
        return false;
    }

    const required = Number(data?.requiredSeconds ?? 0);
    if (required <= 0) return true; // règle désactivée (PRAYER_FOCUS_SECONDS=0)

    return new Promise((resolve) => {
        const q = (sel) => modal.querySelector(sel);
        const timeEl = q('[data-prayer-focus-time]');
        const ring = q('[data-prayer-focus-ring]');
        const paused = q('[data-prayer-focus-paused]');
        const done = q('[data-prayer-focus-done]');
        const checkRow = q('[data-prayer-focus-check-row]');
        const check = q('[data-prayer-focus-check]');
        const submit = q('[data-prayer-focus-submit]');
        const submitLabel = q('[data-prayer-focus-submit-label]');
        const hint = q('[data-prayer-focus-hint]');
        // Textes selon le sujet : data-label-{request|testimony|verse|prophecy|event|session} et data-confirm-… du composant.
        const suffix = { testimony: 'Testimony', daily_verse: 'Verse', prophecy: 'Prophecy', event: 'Event', prayer_session: 'Session' }[kind] || 'Request';

        q('[data-prayer-focus-label]').textContent = modal.dataset[`label${suffix}`] || modal.dataset.labelRequest;
        q('[data-prayer-focus-subject]').textContent = subject;
        q('[data-prayer-focus-subject]').hidden = !subject;
        q('[data-prayer-focus-excerpt]').textContent = excerpt;
        q('[data-prayer-focus-excerpt]').hidden = !excerpt;
        q('[data-prayer-focus-check-label]').textContent = modal.dataset[`confirm${suffix}`] || modal.dataset.confirmRequest;

        // Temps de prière effectif : n'avance que quand l'onglet est visible (le serveur, lui, compte le temps réel).
        let elapsed = 0;
        let lastTick = performance.now();
        let finished = false;
        let timer = null;
        const lastFocused = document.activeElement;

        check.checked = false;
        checkRow.hidden = true;
        done.hidden = true;
        paused.hidden = true;
        hint.hidden = false;
        submit.disabled = true;
        submitLabel.textContent = modal.dataset.validate;

        const render = () => {
            const remaining = Math.max(0, required - elapsed);
            timeEl.textContent = format(remaining);
            ring.style.strokeDashoffset = String(RING * (1 - remaining / required));
        };

        const tick = () => {
            const now = performance.now();
            if (!document.hidden) elapsed += (now - lastTick) / 1000;
            lastTick = now;
            render();
            if (!finished && elapsed >= required) {
                finished = true;
                clearInterval(timer);
                done.hidden = false;
                checkRow.hidden = false;
                hint.hidden = true;
                check.focus();
            }
        };

        const onVisibility = () => {
            // Onglet caché : le temps écoulé pendant l'absence n'est pas compté.
            if (document.hidden) {
                tick();
                paused.hidden = finished;
            } else {
                lastTick = performance.now();
                paused.hidden = true;
            }
        };

        const onCheck = () => {
            submit.disabled = !(finished && check.checked);
        };

        const close = (confirmed) => {
            clearInterval(timer);
            document.removeEventListener('visibilitychange', onVisibility);
            document.removeEventListener('keydown', onKey);
            check.removeEventListener('change', onCheck);
            submit.removeEventListener('click', onSubmit);
            modal.querySelectorAll('[data-prayer-focus-close]').forEach((b) => b.removeEventListener('click', onClose));
            modal.hidden = true;
            document.body.classList.remove('overflow-hidden');
            active = null;
            lastFocused?.focus?.();
            resolve(confirmed);
        };

        const onSubmit = () => {
            if (!finished || !check.checked) return;
            submit.disabled = true;
            submitLabel.textContent = modal.dataset.validating;
            close(true);
        };
        const onClose = () => close(false);
        const onKey = (e) => {
            if (e.key === 'Escape') close(false);
        };

        check.addEventListener('change', onCheck);
        submit.addEventListener('click', onSubmit);
        modal.querySelectorAll('[data-prayer-focus-close]').forEach((b) => b.addEventListener('click', onClose));
        document.addEventListener('visibilitychange', onVisibility);
        document.addEventListener('keydown', onKey);

        active = { close };
        render();
        modal.hidden = false;
        document.body.classList.add('overflow-hidden');
        modal.querySelector('[data-prayer-focus-close]')?.focus();
        timer = setInterval(tick, 250);
    });
}

// ── Formulaires « Je prie » (requêtes, verset du jour, paroles prophétiques) ──
// Capture : passe avant l'indicateur de chargement des formulaires (app.js), qui ignore un envoi annulé.
document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.prayerFocus) return;
    if (form.dataset.prayed === '1') return; // retirer sa prière : immédiat
    if (form.dataset.prayerFocusDone === '1') {
        delete form.dataset.prayerFocusDone;
        return; // temps de prière validé : envoi normal
    }

    e.preventDefault();
    e.stopImmediatePropagation();
    const ok = await prayerFocus({
        startUrl: form.dataset.prayerFocus,
        kind: form.dataset.prayerFocusKind || 'prayer_request',
        subject: form.dataset.prayerSubject || '',
        excerpt: form.dataset.prayerExcerpt || '',
    });
    if (!ok) return;
    form.dataset.prayerFocusDone = '1';
    if (typeof form.requestSubmit === 'function') form.requestSubmit();
    else form.submit();
}, true);

window.prayerFocus = prayerFocus;

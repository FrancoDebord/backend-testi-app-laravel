/*
|--------------------------------------------------------------------------
| Publication d'un témoignage vidéo depuis le site : envoi DIRECT à Bunny Stream
|--------------------------------------------------------------------------
| docs/fonctionnalites/videos-bunny-stream.md
| 1. le formulaire (sans le fichier) est envoyé avec bunny_upload=1 : le serveur crée le témoignage et
|    la vidéo Bunny, et renvoie une autorisation d'envoi signée (jamais la clé API) ;
| 2. le navigateur envoie le fichier à https://video.bunnycdn.com/tusupload (tus-js-client, reprise
|    automatique ; autorisation renouvelée par signature_url avant son expiration) ;
| 3. upload-complete : la vidéo passe « en traitement » ; redirection vers le témoignage.
| Sans JavaScript, ou pour un audio, le formulaire est envoyé normalement.
*/

import { t } from './i18n';

const MAX_BYTES = 2 * 1024 * 1024 * 1024; // 2 Go

export function initBunnyUpload() {
    const form = document.querySelector('form[data-bunny-upload]');
    if (!form) return;
    const fileInput = form.querySelector('input[name="media_file"]');
    const panel = form.querySelector('[data-bunny-progress]');
    const bar = panel?.querySelector('[data-bunny-bar]');
    const percent = panel?.querySelector('[data-bunny-percent]');
    const stateText = panel?.querySelector('[data-bunny-state]');
    const errorBox = panel?.querySelector('[data-bunny-error]');
    const submit = form.querySelector('[type="submit"]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const isVideoUpload = () =>
        form.querySelector('input[name="type"]:checked')?.value === 'video' && fileInput?.files?.length === 1;

    const setState = (text, pct = null) => {
        panel.hidden = false;
        stateText.textContent = text;
        if (pct !== null) {
            bar.style.width = `${pct}%`;
            percent.textContent = t('bunny.percent', { pct });
            bar.parentElement.setAttribute('aria-valuenow', String(pct));
        }
    };
    const fail = (message) => {
        errorBox.textContent = message;
        errorBox.hidden = false;
        stateText.textContent = t('bunny.interrupted');
        if (submit) {
            submit.disabled = false;
            submit.innerHTML = submit.dataset.bunnyLabel || submit.innerHTML;
        }
    };
    const post = async (url, body) => {
        const res = await fetch(url, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body,
            credentials: 'same-origin',
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const errors = data.errors ? Object.values(data.errors).flat() : [];
            throw new Error(errors[0] || data.message || t('bunny.prepare_failed'));
        }
        return data;
    };

    // Phase de capture : avant l'indicateur de chargement générique. Le travail est différé d'un tour
    // pour laisser l'éditeur de texte recopier son contenu dans le formulaire (et le vérifier).
    form.addEventListener(
        'submit',
        (e) => {
            if (!isVideoUpload()) return;
            e.preventDefault();
            setTimeout(() => start(), 0);
        },
        { capture: true },
    );

    async function start() {
        const required = form.querySelector('[data-rt-required]');
        if (required && !required.value.trim()) return; // l'éditeur affiche déjà l'erreur

        const file = fileInput.files[0];
        errorBox.hidden = true;
        if (file.size > MAX_BYTES) {
            panel.hidden = false;
            fail(t('bunny.too_large'));
            return;
        }
        if (submit) {
            submit.dataset.bunnyLabel = submit.innerHTML;
            submit.disabled = true;
            submit.textContent = t('bunny.sending');
        }
        setState(t('bunny.preparing'), 0);

        let init;
        try {
            const body = new FormData(form);
            body.delete('media_file');
            body.set('bunny_upload', '1');
            init = await post(form.action, body);
        } catch (err) {
            fail(err.message);
            return;
        }

        let auth = init.upload;
        let tus;
        try {
            tus = await import('tus-js-client');
        } catch {
            fail(t('bunny.module_failed'));
            return;
        }

        const upload = new tus.Upload(file, {
            endpoint: auth.tus_endpoint,
            retryDelays: [0, 3000, 5000, 10000, 20000, 60000],
            chunkSize: 50 * 1024 * 1024,
            metadata: { filetype: file.type || 'video/mp4', title: form.querySelector('[name="title"]')?.value || file.name },
            headers: {
                AuthorizationSignature: auth.signature,
                AuthorizationExpire: String(auth.expiration),
                VideoId: auth.bunny_video_id,
                LibraryId: String(auth.library_id),
            },
            // Autorisation renouvelée 5 minutes avant son expiration (gros fichiers, reprises).
            onBeforeRequest: async (req) => {
                if (Date.now() / 1000 < auth.expiration - 300) return;
                const fresh = await post(init.signature_url, new FormData());
                auth = { ...auth, ...(fresh.data || {}) };
                req.setHeader('AuthorizationSignature', auth.signature);
                req.setHeader('AuthorizationExpire', String(auth.expiration));
            },
            onProgress: (sent, total) => setState(t('bunny.uploading'), Math.min(99, Math.floor((sent / total) * 100))),
            onError: () => fail(t('bunny.upload_failed')),
            onSuccess: async () => {
                setState(t('bunny.processing'), 100);
                try {
                    await post(init.complete_url, new FormData());
                } catch {
                    /* le webhook et la synchronisation planifiée prendront le relais */
                }
                try {
                    localStorage.removeItem(`testiapp:draft:${form.dataset.autosave}`);
                } catch {
                    /* stockage indisponible */
                }
                window.location.href = init.redirect;
            },
        });
        upload.start();
    }
}

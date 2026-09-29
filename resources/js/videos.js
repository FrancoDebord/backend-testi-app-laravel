/*
|--------------------------------------------------------------------------
| Page Vidéos (/videos, /videos/{id}) — docs/fonctionnalites/videos.md
|--------------------------------------------------------------------------
| Tout s'active par attributs HTML ; sans JavaScript, liens et formulaires
| fonctionnent normalement (envoi classique, page suivante).
*/

const flash = (message, type = 'success') => window.flash?.(message, type);

// ── Requêtes JSON ───────────────────────────────────────────────────────
const ERROR_MESSAGES = {
    401: 'Connectez-vous pour continuer.',
    403: "Vous n'avez pas le droit d'effectuer cette action.",
    404: "Ce contenu n'existe plus.",
    419: 'Votre session a expiré. Rechargez la page.',
    429: "Trop d'actions en peu de temps. Patientez un instant.",
};

async function request(url, { method = 'GET', data } = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
    };
    if (data) headers['Content-Type'] = 'application/json';

    let response;
    try {
        response = await fetch(url, { method, headers, credentials: 'same-origin', body: data ? JSON.stringify(data) : undefined });
    } catch {
        throw new Error(navigator.onLine ? 'Le serveur ne répond pas. Réessayez.' : 'Vous êtes hors connexion.');
    }

    const payload = await response.json().catch(() => null);
    if (!response.ok) {
        const validation = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;
        throw new Error(validation || ERROR_MESSAGES[response.status] || "L'opération n'a pas pu aboutir. Réessayez.");
    }
    return payload;
}

/** Fragment HTML rendu par le serveur (texte déjà échappé par Blade). */
function fragment(html) {
    const template = document.createElement('template');
    template.innerHTML = html.trim();
    return template.content;
}

function withSpinner(button, label) {
    const original = button.innerHTML;
    button.setAttribute('aria-busy', 'true');
    button.classList.add('pointer-events-none', 'opacity-60');
    button.innerHTML = '';
    const spinner = document.createElement('span');
    spinner.className = 'spinner';
    const text = document.createElement('span');
    text.textContent = label;
    button.append(spinner, text);
    return () => {
        button.innerHTML = original;
        button.removeAttribute('aria-busy');
        button.classList.remove('pointer-events-none', 'opacity-60');
    };
}

const formatCount = (n) => Number(n).toLocaleString('fr-FR');

// ── Miniatures et prévisualisation ──────────────────────────────────────
const lazyFrames =
    'IntersectionObserver' in window
        ? new IntersectionObserver(
              (entries) => {
                  entries.forEach((entry) => {
                      if (!entry.isIntersecting) return;
                      const video = entry.target;
                      lazyFrames.unobserve(video);
                      video.addEventListener('loadeddata', () => video.classList.remove('opacity-0'), { once: true });
                      video.preload = 'metadata';
                      video.src = video.dataset.src;
                  });
              },
              { rootMargin: '200px' },
          )
        : null;

const canPreview =
    window.matchMedia('(hover: hover) and (pointer: fine)').matches &&
    !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function initPreview(card) {
    if (card.dataset.previewReady) return;
    card.dataset.previewReady = '1';
    let timer = null;
    let clip = null;

    card.addEventListener('mouseenter', () => {
        timer = setTimeout(() => {
            clip = document.createElement('video');
            Object.assign(clip, { muted: true, loop: true, playsInline: true, preload: 'auto', src: card.dataset.preview });
            clip.setAttribute('aria-hidden', 'true');
            clip.className = 'absolute inset-0 h-full w-full object-cover opacity-0 transition-opacity duration-300';
            clip.addEventListener('playing', () => clip?.classList.remove('opacity-0'), { once: true });
            card.querySelector('[data-preview-frame]')?.append(clip);
            clip.play().catch(() => {});
        }, 600);
    });
    card.addEventListener('mouseleave', () => {
        clearTimeout(timer);
        clip?.pause();
        clip?.remove();
        clip = null;
    });
}

function initMedia(root = document) {
    root.querySelectorAll('video[data-lazy-frame]').forEach((video) => {
        if (lazyFrames) lazyFrames.observe(video);
        else video.src = video.dataset.src;
    });
    if (canPreview) root.querySelectorAll('[data-preview]').forEach(initPreview);
}

// ── « Afficher plus » (cartes et commentaires) ──────────────────────────
document.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-load-more]');
    if (!button) return;
    const target = document.getElementById(button.dataset.loadMore);
    if (!target) return;
    e.preventDefault();
    if (button.getAttribute('aria-busy')) return;

    const done = withSpinner(button, 'Chargement…');
    try {
        const data = await request(button.href);
        const nodes = fragment(data.html);
        const first = nodes.firstElementChild;
        target.append(nodes);
        initMedia(target);
        done();
        if (data.next) button.href = data.next;
        else button.closest('div')?.remove();
        first?.querySelector('a')?.focus({ preventScroll: true });
    } catch (error) {
        done();
        flash(error.message, 'error');
    }
});

// ── Lecteur ─────────────────────────────────────────────────────────────
// Qualités, en boucle et lecture automatique : docs/fonctionnalites/affichage-et-lecture.md

/** Préférences de lecture propres à ce navigateur (qualité vidéo, qualité audio, boucle, lecture auto). */
const PREFS_KEY = 'testiapp.playback';
const PLAYED_KEY = 'testiapp.played';
const AUTOPLAY_KEY = 'testiapp.autoplay';
const UP_NEXT_SECONDS = 5;

function storage(kind) {
    try {
        return window[kind];
    } catch {
        return null;
    }
}

function readJson(kind, key, fallback) {
    try {
        return JSON.parse(storage(kind)?.getItem(key)) ?? fallback;
    } catch {
        return fallback;
    }
}

function writeJson(kind, key, value) {
    try {
        storage(kind)?.setItem(key, JSON.stringify(value));
    } catch {
        // Stockage indisponible (navigation privée…) : le choix vaut pour cette page seulement.
    }
}

const readPrefs = () => readJson('localStorage', PREFS_KEY, {});
const savePrefs = (patch) => writeJson('localStorage', PREFS_KEY, { ...readPrefs(), ...patch });

const rankOf = (r) => r.height || r.bitrate || 0;

/**
 * Plafond du mode Auto, comme dans l'application mobile : économiseur de données ou réseau très lent →
 * version la plus légère ; données mobiles ou 3G → 360p / 64 kbps ; sinon 720p / 128 kbps.
 */
function autoCap(kind) {
    const c = navigator.connection;
    if (c && (c.saveData || ['slow-2g', '2g'].includes(c.effectiveType))) return 0;
    const metered = c && (c.type === 'cellular' || c.effectiveType === '3g');
    if (kind === 'video') return metered ? 360 : 720;
    return metered ? 64 : 128;
}

/** Version la plus haute qui ne dépasse pas le plafond ; sinon la plus légère. */
function pickRendition(renditions, cap) {
    if (!renditions.length) return null;
    if (cap <= 0) return renditions[0];
    let best = null;
    renditions.forEach((r) => {
        if (rankOf(r) <= cap) best = r;
    });
    return best ?? renditions[0];
}

/** Préférence (« auto », « 480p », « 64k », « original ») → { url, value, rendition } à lire. */
function resolveQuality(player, renditions, pref) {
    const original = { url: player.dataset.original, value: 'original', rendition: null };
    if (pref === 'original' || !renditions.length) return original;
    const auto = !pref || pref === 'auto';
    const r = pickRendition(renditions, auto ? autoCap(player.dataset.kind) : parseInt(pref, 10) || 0);
    return r ? { url: r.url, value: auto ? 'auto' : r.quality, rendition: r } : original;
}

const sameUrl = (a, b) => {
    try {
        return new URL(a, location.href).href === new URL(b, location.href).href;
    } catch {
        return a === b;
    }
};

/** Change de fichier sans perdre la position, la vitesse ni l'état lecture / pause. */
function switchSource(player, url) {
    if (player.currentSrc && sameUrl(player.currentSrc, url)) return;
    const time = player.currentTime;
    const wasPlaying = !player.paused && !player.ended;
    const rate = player.playbackRate;
    player.src = url;
    player.addEventListener(
        'loadedmetadata',
        () => {
            if (time) player.currentTime = time;
            player.playbackRate = rate;
            if (wasPlaying) player.play().catch(() => {});
        },
        { once: true },
    );
}

/** Menu « Qualité ». Renvoie la fonction de secours appelée si la version choisie est illisible. */
function initQuality(player) {
    const select = document.querySelector('[data-player-quality]');
    let renditions = [];
    try {
        renditions = JSON.parse(player.dataset.renditions || '[]').sort((a, b) => rankOf(a) - rankOf(b));
    } catch {
        renditions = [];
    }
    if (!select || !renditions.length) return () => false;

    const isVideo = player.dataset.kind === 'video';
    const prefKey = isVideo ? 'videoQuality' : 'audioQuality';
    const autoOption = select.querySelector('option[value="auto"]');

    const apply = (pref) => {
        const choice = resolveQuality(player, renditions, pref);
        select.value = choice.value;
        const detail = choice.value === 'auto' && choice.rendition
            ? ` (${isVideo ? choice.rendition.quality : `${choice.rendition.bitrate} kbps`})`
            : '';
        autoOption.textContent = autoOption.dataset.autoLabel + detail;
        switchSource(player, choice.url);
    };

    select.hidden = false;
    apply(readPrefs()[prefKey] ?? 'auto');
    select.addEventListener('change', () => {
        savePrefs({ [prefKey]: select.value });
        apply(select.value);
    });

    // Version allégée illisible (fichier absent, conversion incomplète) : retour unique à l'original.
    let fellBack = false;
    return () => {
        if (fellBack || sameUrl(player.currentSrc || '', player.dataset.original)) return false;
        fellBack = true;
        select.value = 'original';
        switchSource(player, player.dataset.original);
        flash("Cette qualité n'est pas disponible : lecture en qualité d'origine.", 'error');
        return true;
    };
}

/** « En boucle » et « Lecture auto » (bandeau « À suivre » avec compte à rebours). */
function initRepeatAndNext(player) {
    const prefs = readPrefs();
    const loopButton = document.querySelector('[data-player-loop]');
    const autoButton = document.querySelector('[data-player-autoplay]');
    const banner = document.querySelector('[data-up-next-banner]');

    player.loop = prefs.loop === true;
    if (loopButton) {
        loopButton.hidden = false;
        setPressed(loopButton, player.loop);
        loopButton.addEventListener('click', () => {
            player.loop = !player.loop;
            setPressed(loopButton, player.loop);
            savePrefs({ loop: player.loop });
            flash(player.loop ? 'Lecture en boucle activée.' : 'Lecture en boucle désactivée.');
        });
    }

    // Témoignages déjà enchaînés dans cet onglet : on évite de revenir sans cesse aux deux mêmes.
    const id = player.dataset.testimony;
    const played = readJson('sessionStorage', PLAYED_KEY, []).filter((x) => x !== id);
    played.push(id);
    writeJson('sessionStorage', PLAYED_KEY, played.slice(-50));

    // Arrivée par la lecture automatique : on lance la lecture (le navigateur peut la refuser, sans gravité).
    if (readJson('sessionStorage', AUTOPLAY_KEY, null) === id) {
        writeJson('sessionStorage', AUTOPLAY_KEY, null);
        player.play().catch(() => {});
    }

    let queue = [];
    try {
        queue = JSON.parse(player.dataset.upNext || '[]');
    } catch {
        queue = [];
    }
    if (!autoButton || !banner || !queue.length) return;

    const next = queue.find((item) => !played.includes(item.id)) ?? queue[0];
    const playLink = banner.querySelector('[data-up-next-play]');
    const count = banner.querySelector('[data-up-next-count]');
    banner.querySelector('[data-up-next-title]').textContent = next.title;
    playLink.href = next.url;

    let timer = null;
    const cancel = () => {
        clearInterval(timer);
        timer = null;
        banner.hidden = true;
    };
    const markAutoplay = () => writeJson('sessionStorage', AUTOPLAY_KEY, next.id);

    let autoplay = prefs.autoplay !== false;
    autoButton.hidden = false;
    setPressed(autoButton, autoplay);
    autoButton.addEventListener('click', () => {
        autoplay = !autoplay;
        setPressed(autoButton, autoplay);
        savePrefs({ autoplay });
        if (!autoplay) cancel();
        flash(autoplay ? 'Lecture automatique activée.' : 'Lecture automatique désactivée.');
    });

    playLink.addEventListener('click', markAutoplay);
    banner.querySelector('[data-up-next-cancel]').addEventListener('click', cancel);
    player.addEventListener('play', cancel);
    player.addEventListener('ended', () => {
        if (!autoplay || player.loop) return;
        let left = UP_NEXT_SECONDS;
        count.textContent = left;
        banner.hidden = false;
        clearInterval(timer);
        timer = setInterval(() => {
            left -= 1;
            count.textContent = left;
            if (left > 0) return;
            cancel();
            markAutoplay();
            window.location.href = next.url;
        }, 1000);
    });
}

function initPlayer() {
    const player = document.querySelector('[data-player]');
    if (!player) return;
    const error = player.parentElement.querySelector('[data-player-error]');

    const fallBack = initQuality(player);
    const showError = () => {
        if (fallBack()) return;
        if (error) error.hidden = false;
    };
    player.addEventListener('error', showError);
    player.querySelector('source')?.addEventListener('error', showError);

    // La vue est comptée au premier lancement de la lecture (une fois par personne et par période côté serveur).
    player.addEventListener(
        'play',
        async () => {
            try {
                const duration = Number.isFinite(player.duration) ? Math.round(player.duration) : null;
                const data = await request(player.dataset.viewUrl, { method: 'POST', data: { duration } });
                document.querySelectorAll('[data-views-label]').forEach((el) => (el.textContent = data.label));
            } catch {
                // Sans incidence pour la personne qui regarde.
            }
        },
        { once: true },
    );

    const speed = document.querySelector('[data-player-speed]');
    speed?.addEventListener('change', () => {
        const rate = Number(speed.value) || 1;
        player.defaultPlaybackRate = rate;
        player.playbackRate = rate;
    });

    initRepeatAndNext(player);
}

// ── Liste compacte : déplier les détails ────────────────────────────────
document.addEventListener('click', (e) => {
    const button = e.target.closest('[data-row-toggle]');
    if (!button) return;
    const panel = document.getElementById(button.getAttribute('aria-controls'));
    if (!panel) return;
    const open = panel.hidden;
    panel.hidden = !open;
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    button.title = open ? 'Masquer les détails' : 'Afficher les détails';
    button.querySelector('[data-row-icon]')?.classList.toggle('rotate-180', open);
});

// ── Réactions, sauvegarde, partage, description ─────────────────────────
function setPressed(button, on) {
    button.classList.toggle('chip-active', on);
    button.classList.toggle('chip', !on);
    button.setAttribute('aria-pressed', on ? 'true' : 'false');
}

document.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-reaction]');
    if (!button) return;
    const group = button.closest('[data-reactions]');
    if (group.dataset.authenticated !== '1') {
        window.location = group.dataset.loginUrl;
        return;
    }
    if (button.disabled) return;
    button.disabled = true;
    try {
        const data = await request(group.dataset.reactions, { method: 'POST', data: { type: button.dataset.reaction } });
        setPressed(button, data.reacted);
        group.querySelectorAll('[data-count]').forEach((el) => (el.textContent = formatCount(data[el.dataset.count] ?? 0)));
    } catch (error) {
        flash(error.message, 'error');
    } finally {
        button.disabled = false;
    }
});

document.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-save]');
    if (!button || button.disabled) return;
    button.disabled = true;
    try {
        const data = await request(button.dataset.save, { method: 'PUT' });
        setPressed(button, data.saved);
        button.querySelector('[data-save-icon]').className = `${data.saved ? 'fa-solid' : 'fa-regular'} fa-bookmark`;
        button.querySelector('[data-save-label]').textContent = data.saved ? 'Sauvegardé' : 'Sauvegarder';
        flash(data.saved ? 'Ajouté à vos sauvegardes.' : 'Retiré de vos sauvegardes.');
    } catch (error) {
        flash(error.message, 'error');
    } finally {
        button.disabled = false;
    }
});

document.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-share]');
    if (!button) return;
    const url = button.dataset.share;
    try {
        if (navigator.share) {
            await navigator.share({ title: button.dataset.shareTitle, url });
            return;
        }
        await navigator.clipboard.writeText(url);
        flash('Lien copié.');
    } catch (error) {
        if (error?.name !== 'AbortError') flash("Le lien n'a pas pu être copié.", 'error');
    }
});

document.addEventListener('click', (e) => {
    const button = e.target.closest('[data-expand]');
    if (!button) return;
    const target = document.getElementById(button.dataset.expand);
    const expanded = target.classList.toggle('line-clamp-4') === false;
    button.textContent = expanded ? 'Afficher moins' : 'Afficher plus';
    button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
});

// ── Commentaires ────────────────────────────────────────────────────────
function updateCommentCount(count) {
    if (count === undefined || count === null) return;
    document.querySelectorAll('[data-comment-count]').forEach((el) => (el.textContent = formatCount(count)));
}

function updateRepliesButton(root, count) {
    const button = root?.querySelector('[data-replies-load]');
    if (!button || count === undefined || count === null) return;
    button.dataset.repliesCount = count;
    const loaded = root.querySelectorAll('[data-replies-list] > [data-comment]').length;
    button.hidden = count < 1 || loaded >= count;
    button.querySelector('[data-replies-label]').textContent = count > 1 ? `Voir les ${count} réponses` : 'Voir la réponse';
}

function syncEmptyState() {
    const empty = document.querySelector('[data-comments-empty]');
    if (empty) empty.hidden = document.querySelector('#comments-list > [data-comment]') !== null;
}

const findRoot = (id) => document.querySelector(`[data-comment="${CSS.escape(id)}"][data-comment-root]`);

// Répondre / annuler
document.addEventListener('click', (e) => {
    const toggle = e.target.closest('[data-reply-toggle]');
    if (toggle) {
        const form = findRoot(toggle.dataset.replyToggle)?.querySelector('[data-reply-form]');
        if (!form) return;
        form.hidden = false;
        const textarea = form.querySelector('textarea');
        if (toggle.dataset.mention && !textarea.value) textarea.value = `@${toggle.dataset.mention} `;
        textarea.focus();
        textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        return;
    }
    const cancel = e.target.closest('[data-reply-cancel]');
    if (cancel) {
        const form = cancel.closest('form');
        form.reset();
        form.hidden = true;
    }
});

// Modifier / annuler
document.addEventListener('click', (e) => {
    const toggle = e.target.closest('[data-edit-toggle], [data-edit-cancel]');
    if (!toggle) return;
    const comment = toggle.closest('[data-comment]');
    const form = comment.querySelector(':scope > div > [data-comment-edit]');
    const body = comment.querySelector(':scope > div > [data-comment-body]');
    const editing = toggle.hasAttribute('data-edit-toggle');
    form.hidden = !editing;
    body.hidden = editing;
    comment.querySelector(':scope > div > [data-comment-actions]').hidden = editing;
    if (editing) {
        const textarea = form.querySelector('textarea');
        textarea.value = body.textContent;
        textarea.focus();
    }
});

// Voir les réponses
document.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-replies-load]');
    if (!button || button.getAttribute('aria-busy')) return;
    const root = button.closest('[data-comment-root]');
    const list = root.querySelector('[data-replies-list]');

    const done = withSpinner(button, 'Chargement…');
    try {
        const data = await request(button.dataset.repliesLoad);
        // Retire les réponses déjà affichées (publiées à l'instant) pour éviter les doublons.
        const nodes = fragment(data.html);
        nodes.querySelectorAll('[data-comment]').forEach((node) => {
            list.querySelector(`[data-comment="${CSS.escape(node.dataset.comment)}"]`)?.remove();
        });
        list.append(nodes);
        done();
        if (data.next) button.dataset.repliesLoad = data.next;
        else button.hidden = true;
    } catch (error) {
        done();
        flash(error.message, 'error');
    }
});

// Envois : publier, modifier, supprimer
document.addEventListener('submit', async (e) => {
    const form = e.target;
    const kind = form.matches('[data-comment-form]')
        ? 'create'
        : form.matches('[data-comment-edit]')
          ? 'edit'
          : form.matches('[data-comment-delete]')
            ? 'delete'
            : null;
    if (!kind) return;
    e.preventDefault();
    if (form.getAttribute('aria-busy')) return;

    const submit = form.querySelector('[type="submit"]');
    const textarea = form.querySelector('textarea');
    const body = textarea?.value.trim() ?? '';
    if (kind !== 'delete' && !body) {
        textarea.focus();
        return;
    }

    form.setAttribute('aria-busy', 'true');
    const done = submit ? withSpinner(submit, kind === 'edit' ? 'Enregistrement…' : 'Envoi…') : () => {};
    try {
        if (kind === 'create') {
            const parentId = form.querySelector('[name="parent_id"]')?.value || null;
            const data = await request(form.action, { method: 'POST', data: { body, parent_id: parentId } });
            const nodes = fragment(data.html);
            const created = nodes.firstElementChild;

            if (data.parentId) {
                const root = findRoot(data.parentId);
                root.querySelector('[data-replies-list]').append(nodes);
                form.hidden = true;
                updateRepliesButton(root, data.replies);
            } else {
                document.getElementById('comments-list').prepend(nodes);
            }
            form.reset();
            updateCommentCount(data.count);
            syncEmptyState();
            created?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            flash(data.parentId ? 'Réponse publiée.' : 'Commentaire publié.');
        }

        if (kind === 'edit') {
            const data = await request(form.action, { method: 'PUT', data: { body } });
            const comment = form.closest('[data-comment]');
            const text = comment.querySelector(':scope > div > [data-comment-body]');
            text.textContent = data.body;
            form.hidden = true;
            text.hidden = false;
            comment.querySelector(':scope > div > [data-comment-actions]').hidden = false;
            flash('Commentaire modifié.');
        }

        if (kind === 'delete') {
            const data = await request(form.action, { method: 'DELETE' });
            const comment = form.closest('[data-comment]');
            const root = data.parentId ? findRoot(data.parentId) : null;
            comment.remove();
            updateCommentCount(data.count);
            if (root) updateRepliesButton(root, data.replies);
            syncEmptyState();
            flash('Commentaire supprimé.');
        }
    } catch (error) {
        flash(error.message, 'error');
    } finally {
        done();
        form.removeAttribute('aria-busy');
    }
});

// ── Initialisation ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initMedia();
    initPlayer();
});

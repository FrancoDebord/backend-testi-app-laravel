/*
|--------------------------------------------------------------------------
| Témoignages en direct — vérifications, studio (diffuseur), lecteur (spectateurs)
|--------------------------------------------------------------------------
| Chargé uniquement sur les pages lives/*. Vidéo : LiveKit (WebRTC).
| Commentaires et réactions : envoyés à Laravel (contrôles, enregistrement),
| qui les rediffuse en temps réel dans la salle. Voir docs/fonctionnalites/lives.md
*/
import {
    Room,
    RoomEvent,
    Track,
    ConnectionState,
    ConnectionQuality,
    VideoPresets,
    createLocalTracks,
    createLocalAudioTrack,
    createLocalVideoTrack,
} from 'livekit-client';

const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
const config = JSON.parse(document.getElementById('live-config')?.textContent || 'null');

const flash = (msg, type = 'success') => window.flash?.(msg, type);

async function api(url, { method = 'GET', body } = {}) {
    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(CSRF ? { 'X-CSRF-TOKEN': CSRF } : {}),
            ...(body ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    let json = null;
    try {
        json = await res.json();
    } catch {
        /* réponse vide */
    }
    if (!res.ok || json?.success === false) {
        const error = new Error(json?.message || firstError(json) || 'Une erreur est survenue. Réessayez.');
        error.status = res.status;
        throw error;
    }
    return json?.data;
}

const firstError = (json) => (json?.errors ? Object.values(json.errors).flat()[0] : null);

function setDot(dot, tone) {
    const tones = { ok: 'bg-emerald-500', warn: 'bg-amber-500', bad: 'bg-red-500', idle: 'bg-slate-400' };
    dot.classList.remove(...Object.values(tones));
    dot.classList.add(tones[tone] || tones.idle);
}

function formatDuration(ms) {
    const s = Math.max(0, Math.floor(ms / 1000));
    const h = Math.floor(s / 3600);
    const m = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
    const sec = String(s % 60).padStart(2, '0');
    return h ? `${h}:${m}:${sec}` : `${m}:${sec}`;
}

const STATUS = {
    preparing: ['En préparation', 'badge-pending'],
    live: ['En direct', 'badge-live'],
    ended: ['Terminé', 'badge-draft'],
};

function renderStatus(status) {
    const el = $('[data-live-status]');
    if (!el || !STATUS[status]) return;
    el.className = STATUS[status][1];
    el.textContent = STATUS[status][0];
    if (status === 'ended') {
        const rec = $('[data-live-rec]');
        if (rec) rec.hidden = true;
    }
}

// ═══════════════════════════════════════════════════════════════════════
// Vérifications avant de lancer un direct
// ═══════════════════════════════════════════════════════════════════════
function initPreflight(root) {
    const video = $('[data-preflight-video]', root);
    const placeholder = $('[data-preflight-placeholder]', root);
    const level = $('[data-preflight-level]', root);
    const okBox = $('[data-preflight-ok]', root);
    const switchBtn = $('[data-preflight-switch]', root);
    let stream = null;
    let audioCtx = null;
    let facing = 'user';

    const setCheck = (key, tone, detail) => {
        const li = $(`[data-check="${key}"]`, root);
        if (!li) return;
        setDot($('[data-check-dot]', li), tone);
        $('[data-check-detail]', li).textContent = detail;
        li.dataset.state = tone;
    };

    const stop = () => {
        stream?.getTracks().forEach((t) => t.stop());
        stream = null;
        audioCtx?.close();
        audioCtx = null;
    };

    const mediaError = (err, what) => {
        switch (err?.name) {
            case 'NotAllowedError':
            case 'SecurityError':
                return `Accès refusé. Autorisez ${what} dans les réglages du navigateur (icône à gauche de l'adresse), puis relancez.`;
            case 'NotFoundError':
            case 'OverconstrainedError':
                return `Aucun${what === 'la caméra' ? 'e caméra' : ' micro'} détecté${what === 'la caméra' ? 'e' : ''} sur cet appareil.`;
            case 'NotReadableError':
            case 'AbortError':
                return `${what === 'la caméra' ? 'La caméra' : 'Le micro'} est déjà utilisé${what === 'la caméra' ? 'e' : ''} par une autre application. Fermez-la puis relancez.`;
            default:
                return `Impossible d'accéder à ${what}${err?.message ? ` (${err.message})` : ''}.`;
        }
    };

    const meter = (audioTrack) => {
        try {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const analyser = audioCtx.createAnalyser();
            analyser.fftSize = 512;
            audioCtx.createMediaStreamSource(new MediaStream([audioTrack])).connect(analyser);
            const data = new Uint8Array(analyser.frequencyBinCount);
            const tick = () => {
                if (!audioCtx) return;
                analyser.getByteTimeDomainData(data);
                let peak = 0;
                for (const v of data) peak = Math.max(peak, Math.abs(v - 128));
                level.style.width = `${Math.min(100, (peak / 64) * 100)}%`;
                requestAnimationFrame(tick);
            };
            tick();
        } catch {
            /* indicateur facultatif */
        }
    };

    async function run() {
        stop();
        okBox.checked = false;
        okBox.dispatchEvent(new Event('change', { bubbles: true }));
        ['secure', 'browser', 'network', 'camera', 'micro'].forEach((k) => setCheck(k, 'idle', 'Vérification…'));

        const secure = window.isSecureContext;
        setCheck('secure', secure ? 'ok' : 'bad', secure ? 'Connexion chiffrée.' : 'La caméra ne fonctionne qu\'en HTTPS. Ouvrez le site avec une adresse https://.');

        const browser = 'RTCPeerConnection' in window && !!navigator.mediaDevices?.getUserMedia;
        setCheck('browser', browser ? 'ok' : 'bad', browser ? 'Navigateur compatible.' : 'Utilisez une version récente de Chrome, Safari, Edge ou Firefox.');

        const online = navigator.onLine;
        const type = navigator.connection?.effectiveType;
        setCheck('network', online ? (type && ['slow-2g', '2g'].includes(type) ? 'warn' : 'ok') : 'bad',
            online ? (type ? `Connecté (${type.toUpperCase()}).${['slow-2g', '2g', '3g'].includes(type) ? ' Connexion lente : la vidéo pourra être de qualité réduite.' : ''}` : 'Connecté.') : 'Aucune connexion Internet.');

        if (!secure || !browser) {
            setCheck('camera', 'bad', 'Non vérifiable (voir ci-dessus).');
            setCheck('micro', 'bad', 'Non vérifiable (voir ci-dessus).');
            return finish();
        }

        let videoTrack = null;
        let audioTrack = null;
        try {
            const v = await navigator.mediaDevices.getUserMedia({ video: { facingMode: facing, width: { ideal: 1280 }, height: { ideal: 720 } } });
            videoTrack = v.getVideoTracks()[0];
            const s = videoTrack.getSettings();
            setCheck('camera', 'ok', `${videoTrack.label || 'Caméra'}${s.width ? ` · ${s.width}×${s.height}` : ''}`);
        } catch (err) {
            setCheck('camera', 'bad', mediaError(err, 'la caméra'));
        }
        try {
            const a = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } });
            audioTrack = a.getAudioTracks()[0];
            setCheck('micro', 'ok', audioTrack.label || 'Micro disponible.');
            meter(audioTrack);
        } catch (err) {
            setCheck('micro', 'bad', mediaError(err, 'le micro'));
        }

        stream = new MediaStream([videoTrack, audioTrack].filter(Boolean));
        if (videoTrack) {
            video.srcObject = new MediaStream([videoTrack]);
            video.classList.toggle('-scale-x-100', facing === 'user');
            placeholder.hidden = true;
        }

        try {
            const cams = (await navigator.mediaDevices.enumerateDevices()).filter((d) => d.kind === 'videoinput');
            switchBtn.hidden = cams.length < 2;
        } catch {
            switchBtn.hidden = true;
        }

        return finish();
    }

    async function finish() {
        try {
            const battery = await navigator.getBattery?.();
            if (battery) {
                const pct = Math.round(battery.level * 100);
                const low = pct < 20 && !battery.charging;
                setCheck('battery', low ? 'warn' : 'ok', `${pct} %${battery.charging ? ', en charge' : ''}.${low ? ' Branchez le téléphone avant de commencer.' : ''}`);
            }
        } catch {
            /* information facultative */
        }
        const required = $$('[data-check]:not([data-optional])', root);
        const ok = required.every((li) => li.dataset.state === 'ok' || li.dataset.state === 'warn');
        okBox.checked = ok;
        okBox.dispatchEvent(new Event('change', { bubbles: true }));
        return ok;
    }

    $('[data-preflight-run]', root).addEventListener('click', run);
    switchBtn.addEventListener('click', () => {
        facing = facing === 'user' ? 'environment' : 'user';
        run();
    });
    window.addEventListener('online', run);
    window.addEventListener('offline', run);
    // Libère la caméra avant d'ouvrir le studio.
    $('#live-create-form')?.addEventListener('submit', stop);
    window.addEventListener('pagehide', stop);

    run();
}

// ═══════════════════════════════════════════════════════════════════════
// Commentaires, réactions, audience (studio et spectateurs)
// ═══════════════════════════════════════════════════════════════════════
function initInteractions() {
    const list = $('[data-live-comment-list]');
    const empty = $('[data-live-comment-empty]');
    const form = $('[data-live-comment-form]');
    const countEl = $('[data-live-comment-count]');
    const seen = new Map();
    let pendingModeration = null;
    let pinned = null; // commentaire épinglé (payload) ou null

    const nearBottom = () => list.scrollHeight - list.scrollTop - list.clientHeight < 80;

    function renderComment(c) {
        if (!c || seen.has(c.id)) return;
        const stick = nearBottom();
        const byHost = c.user?.id === config.hostId;
        const li = document.createElement('li');
        // Message du diffuseur mis en évidence (fond léger, pastille « Diffuseur »).
        li.className = byHost
            ? 'flex gap-2.5 rounded-xl bg-slate-50 px-2.5 py-2 ring-1 ring-slate-200'
            : 'flex gap-2.5 rounded-xl px-2.5 py-1.5 hover:bg-slate-50';
        li.dataset.commentId = c.id;
        li.dataset.userId = c.user?.id || '';
        li.dataset.base = li.className;

        const avatar = document.createElement('span');
        avatar.className = 'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-[11px] font-semibold text-slate-700';
        avatar.textContent = c.user?.initials || '?';
        avatar.setAttribute('aria-hidden', 'true');

        const body = document.createElement('div');
        body.className = 'min-w-0 flex-1';
        const meta = document.createElement('p');
        meta.className = 'flex flex-wrap items-center gap-x-1.5 text-xs';
        const name = document.createElement('span');
        name.className = 'font-semibold text-slate-900';
        name.textContent = c.user?.displayName || 'Utilisateur';
        meta.append(name);
        if (byHost) {
            const badge = document.createElement('span');
            badge.className = 'badge-neutral py-0 text-[10px]';
            badge.textContent = 'Diffuseur';
            meta.append(badge);
        }
        const time = document.createElement('span');
        time.className = 'text-slate-400';
        time.textContent = c.createdAt ? new Date(c.createdAt).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '';
        meta.append(time);
        const text = document.createElement('p');
        text.className = 'mt-0.5 text-sm leading-snug break-words whitespace-pre-line text-slate-700';
        text.textContent = c.body;
        body.append(meta, text);
        li.append(avatar, body);

        const isStaff = c.user?.id === config.hostId;
        const actions = document.createElement('div');
        actions.className = 'flex shrink-0 items-start gap-1';
        // Épingler : tout commentaire, y compris ceux du diffuseur.
        if (config.canModerate) actions.append(pinButton(c));
        if (config.canModerate && c.user?.id !== config.currentUserId && !isStaff) {
            actions.append(
                modButton('fa-eye-slash', 'Masquer ce commentaire', () => ({
                    message: 'Ce commentaire ne sera plus visible par personne.',
                    title: 'Masquer le commentaire',
                    label: 'Masquer',
                    icon: 'fa-eye-slash',
                    run: () => api(config.urls.commentHide.replace('__COMMENT__', c.id), { method: 'DELETE' }).then(() => removeComment(c.id)),
                })),
                modButton('fa-user-slash', 'Exclure cette personne du direct', () => ({
                    message: `${c.user?.displayName || 'Cette personne'} ne pourra plus commenter ni réagir pendant ce direct, et ses commentaires seront masqués.`,
                    title: 'Exclure du direct',
                    label: 'Exclure',
                    icon: 'fa-user-slash',
                    run: () => api(config.urls.bans, { method: 'POST', body: { user_id: c.user.id } }).then(() => removeUserComments(c.user.id)),
                })),
            );
        }
        if (actions.childElementCount) li.append(actions);

        seen.set(c.id, li);
        markPinned(li, pinned?.id === c.id);
        list.append(li);
        empty.hidden = true;
        if (stick) list.scrollTop = list.scrollHeight;
    }

    function modButton(icon, label, action) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'action-btn-delete h-7 min-w-7';
        btn.title = label;
        btn.setAttribute('aria-label', label);
        btn.innerHTML = `<i class="fa-solid ${icon}"></i>`;
        btn.addEventListener('click', () => {
            pendingModeration = action();
            window.openConfirmModal('live-moderation-form', pendingModeration.message, pendingModeration.title, pendingModeration.label, pendingModeration.icon);
        });
        return btn;
    }

    // ── Commentaire épinglé ─────────────────────────────────────────────────
    const pinnedBox = $('[data-live-pinned]');

    // Message épinglé écrit par le diffuseur : seul le diffuseur peut le retirer ou le remplacer
    // (un modérateur qui regarde est un spectateur). Même règle côté serveur.
    const canTouchPin = () => config.isHost || !pinned || pinned.user?.id !== config.hostId;

    function syncPinPermissions() {
        const allowed = canTouchPin();
        $$('[data-live-pin-btn]').forEach((b) => (b.hidden = !allowed));
        const unpin = $('[data-live-unpin]');
        if (unpin) unpin.hidden = !allowed;
        const option = $('[data-live-comment-pin]');
        if (option) {
            option.disabled = !allowed;
            if (!allowed) option.checked = false;
            const label = option.closest('label');
            label.classList.toggle('opacity-50', !allowed);
            label.classList.toggle('pointer-events-none', !allowed);
            label.title = allowed ? 'Épingler ce message en haut du direct' : 'Le diffuseur a épinglé un message : lui seul peut le remplacer.';
        }
    }

    function pinButton(c) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'action-btn-view h-7 min-w-7';
        btn.dataset.livePinBtn = '';
        btn.hidden = !canTouchPin();
        btn.innerHTML = '<i class="fa-solid fa-thumbtack"></i>';
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            try {
                if (pinned?.id === c.id) {
                    await api(config.urls.unpin, { method: 'DELETE' });
                    renderPinned(null);
                    flash('Commentaire désépinglé.');
                } else {
                    renderPinned(await api(config.urls.commentPin.replace('__COMMENT__', c.id), { method: 'POST' }));
                    flash('Commentaire épinglé.');
                }
            } catch (err) {
                flash(err.message, 'error');
            } finally {
                btn.disabled = false;
            }
        });
        return btn;
    }

    function markPinned(li, isPinned) {
        li.classList.toggle('bg-amber-50', isPinned);
        li.classList.toggle('ring-amber-200', isPinned);
        const btn = li.querySelector('[data-live-pin-btn]');
        if (btn) {
            const label = isPinned ? 'Désépingler ce commentaire' : 'Épingler ce commentaire en haut du direct';
            btn.title = label;
            btn.setAttribute('aria-label', label);
            btn.classList.toggle('text-amber-600', isPinned);
        }
    }

    /** Affiche (ou retire, si null) le commentaire épinglé. */
    function renderPinned(c) {
        pinned = c && c.id ? c : null;
        seen.forEach((li, id) => markPinned(li, pinned?.id === id));
        syncPinPermissions();
        if (!pinnedBox) return;
        pinnedBox.hidden = !pinned;
        if (!pinned) return;
        const byHost = pinned.user?.id === config.hostId;
        $('[data-live-pinned-meta]', pinnedBox).textContent =
            `Épinglé · ${pinned.user?.displayName || 'Utilisateur'}${byHost ? ' (diffuseur)' : ''}`;
        $('[data-live-pinned-body]', pinnedBox).textContent = pinned.body;
    }

    $('[data-live-unpin]')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        btn.disabled = true;
        try {
            await api(config.urls.unpin, { method: 'DELETE' });
            renderPinned(null);
            flash('Commentaire désépinglé.');
        } catch (err) {
            flash(err.message, 'error');
        } finally {
            btn.disabled = false;
        }
    });

    renderPinned(config.pinnedComment);

    $('#live-moderation-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const action = pendingModeration;
        pendingModeration = null;
        try {
            await action?.run();
            flash('Action de modération effectuée.');
        } catch (err) {
            flash(err.message, 'error');
        }
    });

    function removeComment(id) {
        seen.get(id)?.remove();
        seen.delete(id);
        empty.hidden = seen.size > 0;
        if (pinned?.id === id) renderPinned(null);
    }

    function removeUserComments(userId) {
        $$(`[data-user-id="${CSS.escape(userId)}"]`, list).forEach((li) => removeComment(li.dataset.commentId));
    }

    async function loadComments() {
        try {
            const comments = await api(config.urls.comments);
            const ids = new Set(comments.map((c) => c.id));
            [...seen.keys()].forEach((id) => !ids.has(id) && removeComment(id)); // masqués entre-temps
            comments.forEach(renderComment);
        } catch {
            /* nouvel essai au prochain rafraîchissement */
        }
    }

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const textarea = form.querySelector('textarea');
        const body = textarea.value.trim();
        if (!body) return;
        if (!navigator.onLine) {
            flash('Vous êtes hors connexion. Votre commentaire est conservé.', 'warning');
            return;
        }
        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        try {
            const comment = await api(config.urls.comments, { method: 'POST', body: { body } });
            textarea.value = '';
            autosize();
            renderComment(comment);
            list.scrollTop = list.scrollHeight;
            const pinBox = form.querySelector('[data-live-comment-pin]');
            if (pinBox?.checked) {
                pinBox.checked = false; // une seule fois
                renderPinned(await api(config.urls.commentPin.replace('__COMMENT__', comment.id), { method: 'POST' }));
            }
        } catch (err) {
            flash(err.message, 'error');
        } finally {
            btn.disabled = false;
            textarea.focus();
        }
    });
    const input = form?.querySelector('textarea');
    const counter = form?.querySelector('[data-live-comment-counter]');
    const autosize = () => {
        if (!input) return;
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 128)}px`;
        if (counter) counter.textContent = `${input.value.length} / ${input.maxLength}`;
    };
    input?.addEventListener('input', autosize);
    form?.addEventListener('reset', () => setTimeout(autosize));

    form?.querySelector('textarea')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    const renderReactions = (counts) => {
        if (!counts) return;
        Object.entries(counts).forEach(([type, n]) => {
            const el = $(`[data-live-react-count="${type}"]`);
            if (el) el.textContent = n;
        });
        const summary = $('[data-live-reaction-summary]');
        if (summary) {
            const labels = { like: "J'aime", pray: 'Prière', amen: 'Amen', worship: 'Adorer', fire: 'Feu' };
            summary.textContent = Object.entries(counts).map(([t, n]) => `${labels[t]} ${n}`).join(' · ');
        }
    };
    renderReactions(config.reactions);

    $$('[data-live-react]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!config.currentUserId) {
                window.location = config.urls.login;
                return;
            }
            btn.disabled = true;
            try {
                const data = await api(config.urls.reactions, { method: 'POST', body: { type: btn.dataset.liveReact } });
                renderReactions(data.reactions);
            } catch (err) {
                flash(err.message, 'error');
            } finally {
                setTimeout(() => (btn.disabled = false), 600);
            }
        });
    });

    return {
        loadComments,
        onData(msg) {
            switch (msg.type) {
                case 'comment':
                    renderComment(msg.comment);
                    break;
                case 'comment_hidden':
                    removeComment(msg.id);
                    break;
                case 'user_banned':
                    removeUserComments(msg.userId);
                    if (msg.userId === config.currentUserId) {
                        form?.remove();
                        flash('Un modérateur vous a exclu des commentaires de ce direct.', 'warning');
                    }
                    break;
                case 'reaction':
                    renderReactions(msg.counts);
                    break;
                case 'comment_pinned':
                    renderPinned(msg.comment);
                    break;
                case 'comment_unpinned':
                    renderPinned(null);
                    break;
                case 'recording': {
                    // Pastille « REC » : visible seulement pendant l'enregistrement.
                    const rec = $('[data-live-rec]');
                    if (rec) rec.hidden = msg.status !== 'recording';
                    break;
                }
            }
        },
        renderStats(stats) {
            const v = $('[data-live-viewers]');
            if (v) v.textContent = stats.viewers;
            if (countEl) countEl.textContent = stats.commentCount;
            renderReactions(stats.reactions);
            if ('pinnedComment' in stats) renderPinned(stats.pinnedComment);
        },
    };
}

function startTimer(startedAt) {
    const el = $('[data-live-timer]');
    if (!el || !startedAt) return;
    const start = new Date(startedAt).getTime();
    el.hidden = false;
    const tick = () => (el.textContent = formatDuration(Date.now() - start));
    tick();
    return setInterval(tick, 1000);
}

function decode(payload) {
    try {
        return JSON.parse(new TextDecoder().decode(payload));
    } catch {
        return null;
    }
}

// ═══════════════════════════════════════════════════════════════════════
// Intervenants : file d'attente, invitation, antenne (une personne à la fois)
// docs/fonctionnalites/lives-intervenants.md
// ═══════════════════════════════════════════════════════════════════════
const isHostIdentity = (identity) => String(identity || '').startsWith('host-');

const initialsOf = (name) =>
    String(name || '?')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w.charAt(0).toUpperCase())
        .join('') || '?';

const ENDED_MESSAGES = {
    removed: 'Le diffuseur a terminé votre intervention. Merci pour votre témoignage !',
    banned: 'Un modérateur vous a retiré de l\'antenne.',
    disconnected: 'Votre connexion a été perdue : vous n\'êtes plus à l\'antenne.',
    left: 'Votre intervention est terminée. Merci pour votre témoignage !',
};

/**
 * @param {Room} room salle LiveKit déjà créée (studio ou spectateur)
 */
function initStage(room) {
    const panel = $('[data-stage-panel]');
    const pip = $('[data-stage-pip]');
    const pipVideo = $('[data-stage-pip-video]');
    const modal = $('#stage-invite-modal');
    const selfBar = $('[data-stage-self]');
    const staff = !!config.canModerate;
    const url = (key, speakerId) => config.urls[key].replace('__SPEAKER__', speakerId || '');

    let state = null;
    let onStage = false; // la personne connectée est elle-même à l'antenne
    let leaving = false; // fin volontaire en cours (le retrait des droits arrive avant la réponse)
    let accepting = false; // acceptation en cours : la caméra d'aperçu doit rester ouverte
    const local = { audio: null, video: null };
    let pipTrack = null;
    let invitedFor = null;
    let countdown = null;
    let pendingRemove = null;

    // ── Médaillon ───────────────────────────────────────────────────────
    // Connexion de l'intervenant annoncé par le serveur (identité « user-{id}-… ») : c'est l'état du serveur
    // qui décide de l'affichage, pas la seule présence d'un participant dans la salle.
    const guestParticipant = (userId) =>
        userId ? [...room.remoteParticipants.values()].find((p) => p.identity.startsWith(`user-${userId}-`)) : null;

    function refreshPip() {
        if (!pip) return;
        const current = state?.current?.status === 'on_stage' ? state.current : null;
        const remote = onStage ? null : guestParticipant(current?.user?.id);
        if (!onStage && !current) {
            pipTrack?.detach(pipVideo);
            pipTrack = null;
            pip.hidden = true;
            return;
        }

        const name = onStage ? 'Vous' : current?.user?.displayName || remote?.name || 'Intervenant';
        $('[data-stage-pip-name]', pip).textContent = name;
        $('[data-stage-pip-initials]', pip).textContent = onStage ? current?.user?.initials || initialsOf(config.currentUserName) : current?.user?.initials || initialsOf(name);

        let track = null;
        let micMuted = false;
        if (onStage) {
            track = local.video && !local.video.isMuted ? local.video : null;
            micMuted = !!local.audio?.isMuted;
        } else if (remote) {
            const cam = remote.getTrackPublication(Track.Source.Camera);
            track = cam?.isSubscribed && !cam.isMuted ? cam.track : null;
            micMuted = !!remote.getTrackPublication(Track.Source.Microphone)?.isMuted;
        }
        if (track !== pipTrack) {
            pipTrack?.detach(pipVideo);
            pipTrack = track;
            track?.attach(pipVideo);
        }
        pipVideo.hidden = !track;
        pipVideo.classList.toggle('-scale-x-100', onStage);
        $('[data-stage-pip-avatar]', pip).hidden = !!track;
        $('[data-stage-pip-mic]', pip).className = `fa-solid ${micMuted ? 'fa-microphone-slash' : 'fa-microphone'} shrink-0 text-[10px]`;
        pip.hidden = false;
    }

    ['TrackSubscribed', 'TrackUnsubscribed', 'TrackMuted', 'TrackUnmuted', 'ParticipantDisconnected', 'LocalTrackPublished', 'LocalTrackUnpublished'].forEach(
        (event) => room.on(RoomEvent[event], () => refreshPip()),
    );

    // ── État ────────────────────────────────────────────────────────────
    async function load() {
        try {
            state = await api(config.urls.stage);
            render();
        } catch {
            /* nouvel essai au prochain rafraîchissement */
        }
    }

    const setState = (next) => {
        if (next) state = next;
        render();
    };

    function render() {
        if (!state) return;
        renderCurrent();
        if (staff) renderQueue();
        else renderMine();
        refreshPip();
    }

    function renderCurrent() {
        const box = $('[data-stage-current]', panel || document);
        if (!box) return;
        const cur = state.current;
        box.hidden = !cur;
        if (!cur) return;

        const isMe = cur.user?.id === config.currentUserId;
        $('[data-stage-current-initials]', box).textContent = cur.user?.initials || '?';
        $('[data-stage-current-name]', box).textContent = (cur.user?.displayName || 'Intervenant') + (isMe ? ' (vous)' : '');
        $('[data-stage-current-status]', box).textContent = cur.status === 'on_stage'
            ? `À l'antenne${cur.camera ? ' · caméra' : ' · micro seulement'}`
            : 'Invité · en attente de sa réponse';
        setDot($('[data-stage-current-dot]', box), cur.status === 'on_stage' ? 'bad' : 'warn');

        const message = $('[data-stage-current-message]', box);
        if (message) {
            message.textContent = cur.message ? `« ${cur.message} »` : '';
            message.hidden = !cur.message;
        }
        const action = $('[data-stage-current-action]', box);
        if (action) {
            action.hidden = false;
            action.dataset.speakerId = cur.id;
            action.dataset.kind = cur.status === 'on_stage' ? 'remove' : 'cancel';
            action.innerHTML = cur.status === 'on_stage'
                ? '<i class="fa-solid fa-phone-slash" aria-hidden="true"></i>Terminer'
                : '<i class="fa-solid fa-xmark" aria-hidden="true"></i>Annuler l\'invitation';
            action.dataset.name = cur.user?.displayName || 'Cette personne';
        }
    }

    function renderQueue() {
        const list = $('[data-stage-queue]', panel);
        const empty = $('[data-stage-queue-empty]', panel);
        const toggle = $('[data-stage-enabled]', panel);
        if (toggle) toggle.checked = !!state.enabled;
        if (!list) return;

        const queue = state.queue || [];
        list.replaceChildren(
            ...queue.map((s) => {
                const li = document.createElement('li');
                li.className = 'flex items-start gap-3 px-4 py-3';

                const avatar = document.createElement('span');
                avatar.className = 'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-[11px] font-semibold text-slate-700';
                avatar.textContent = s.user?.initials || '?';

                const body = document.createElement('div');
                body.className = 'min-w-0 flex-1';
                const name = document.createElement('p');
                name.className = 'truncate text-sm font-semibold text-slate-900';
                name.textContent = `${s.position}. ${s.user?.displayName || 'Utilisateur'}`;
                const meta = document.createElement('p');
                meta.className = 'text-xs text-slate-500';
                meta.textContent = s.createdAt ? `Demande à ${new Date(s.createdAt).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}` : '';
                body.append(name, meta);
                if (s.message) {
                    const msg = document.createElement('p');
                    msg.className = 'mt-1 text-xs break-words text-slate-600';
                    msg.textContent = `« ${s.message} »`;
                    body.append(msg);
                }

                const actions = document.createElement('div');
                actions.className = 'flex shrink-0 items-center gap-1';
                const invite = document.createElement('button');
                invite.type = 'button';
                invite.className = 'btn-secondary btn-sm';
                invite.dataset.stageInvite = s.id;
                invite.disabled = !!state.current;
                invite.title = state.current ? 'Une personne est déjà à l\'antenne' : `Inviter ${s.user?.displayName || ''} à l'antenne`;
                invite.innerHTML = '<i class="fa-solid fa-microphone" aria-hidden="true"></i><span>Inviter</span>';
                const decline = document.createElement('button');
                decline.type = 'button';
                decline.className = 'action-btn-delete h-8 min-w-8';
                decline.dataset.stageDecline = s.id;
                decline.title = 'Retirer de la file';
                decline.setAttribute('aria-label', `Retirer ${s.user?.displayName || 'cette personne'} de la file`);
                decline.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
                actions.append(invite, decline);

                li.append(avatar, body, actions);
                return li;
            }),
        );
        if (empty) empty.hidden = queue.length > 0;
    }

    function renderMine() {
        if (!panel) return;
        const count = $('[data-stage-queue-count]', panel);
        if (count) {
            count.hidden = !state.queueCount;
            count.textContent = state.queueCount > 1 ? `${state.queueCount} personnes attendent` : '1 personne attend';
        }

        const form = $('[data-stage-request-form]', panel);
        const mineBox = $('[data-stage-mine]', panel);
        const refusal = $('[data-stage-refusal]', panel);
        const mine = state.mine;

        if (form) form.hidden = !state.canRequest;
        if (refusal) {
            refusal.hidden = !!mine || state.canRequest || !state.refusal;
            refusal.textContent = state.refusal || '';
        }
        if (mineBox) {
            mineBox.hidden = !mine;
            if (mine) {
                const text = $('[data-stage-mine-text]', mineBox);
                const openBtn = $('[data-stage-open-invite]', mineBox);
                const withdraw = $('[data-stage-withdraw]', mineBox);
                openBtn.hidden = mine.status !== 'invited';
                withdraw.hidden = mine.status === 'on_stage';
                withdraw.textContent = mine.status === 'invited' ? 'Refuser' : 'Annuler ma demande';
                text.textContent = {
                    waiting: `Votre demande est enregistrée : vous êtes n°${mine.position} dans la file. Gardez cette page ouverte, vous serez prévenu à votre tour.`,
                    invited: 'C\'est votre tour ! Le diffuseur vous invite à l\'antenne.',
                    on_stage: 'Vous êtes à l\'antenne.',
                }[mine.status] || '';
            }
        }

        // Invitation reçue : fenêtre ouverte une fois ; fermée si l'invitation est annulée ou expirée.
        if (mine?.status === 'invited' && !onStage) {
            if (invitedFor !== mine.id) openInvite(mine);
        } else if (invitedFor && !accepting) {
            closeInvite(mine ? null : 'L\'invitation n\'est plus valable.');
        }
        if (onStage && mine?.status !== 'on_stage') stopGuest(leaving ? 'left' : undefined);
    }

    // ── Diffuseur et modérateurs ────────────────────────────────────────
    panel?.addEventListener('click', async (e) => {
        const invite = e.target.closest('[data-stage-invite]');
        const decline = e.target.closest('[data-stage-decline]');
        const action = e.target.closest('[data-stage-current-action]');
        const btn = invite || decline || action;
        if (!btn || btn.disabled) return;

        if (action?.dataset.kind === 'remove') {
            pendingRemove = action.dataset.speakerId;
            window.openConfirmModal('stage-confirm-form', `${action.dataset.name} quittera l'antenne : son micro et sa caméra seront coupés pour tous.`,
                'Terminer l\'intervention', 'Terminer', 'fa-phone-slash');
            return;
        }

        btn.disabled = true;
        try {
            if (invite) {
                setState(await api(url('stageInvite', invite.dataset.stageInvite), { method: 'POST' }));
                flash('Invitation envoyée : la personne a quelques secondes pour accepter.');
            } else {
                setState(await api(url('stageDecline', (decline || action).dataset.stageDecline || action.dataset.speakerId), { method: 'POST' }));
                flash(decline ? 'Demande retirée de la file.' : 'Invitation annulée.');
            }
        } catch (err) {
            flash(err.message, 'error');
            load();
        } finally {
            btn.disabled = false;
        }
    });

    $('#stage-confirm-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const speakerId = pendingRemove;
        pendingRemove = null;
        if (!speakerId) return;
        try {
            setState(await api(url('stageRemove', speakerId), { method: 'POST' }));
            flash('Intervention terminée.');
        } catch (err) {
            flash(err.message, 'error');
            load();
        }
    });

    $('[data-stage-enabled]', panel || document)?.addEventListener('change', async (e) => {
        const toggle = e.currentTarget;
        toggle.disabled = true;
        try {
            setState(await api(config.urls.stageSettings, { method: 'POST', body: { enabled: toggle.checked } }));
            flash(toggle.checked ? 'Demandes d\'intervention ouvertes.' : 'Demandes d\'intervention fermées. La file et l\'intervenant en cours sont conservés.');
        } catch (err) {
            toggle.checked = !toggle.checked;
            flash(err.message, 'error');
        } finally {
            toggle.disabled = false;
        }
    });

    // ── Spectateur : demande ────────────────────────────────────────────
    $('[data-stage-request-form]', panel || document)?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.currentTarget;
        const btn = form.querySelector('button[type="submit"]');
        const message = form.querySelector('textarea')?.value.trim() || null;
        btn.disabled = true;
        try {
            setState(await api(config.urls.stageRequest, { method: 'POST', body: { message } }));
            form.reset();
            flash('Demande envoyée : le diffuseur vous invitera à votre tour.');
        } catch (err) {
            flash(err.message, 'error');
        } finally {
            btn.disabled = false;
        }
    });

    async function withdraw() {
        try {
            setState(await api(config.urls.stageWithdraw, { method: 'DELETE' }));
        } catch (err) {
            flash(err.message, 'error');
        }
    }

    $('[data-stage-withdraw]', panel || document)?.addEventListener('click', async () => {
        await withdraw();
        closeInvite();
        flash('Votre demande est retirée.');
    });
    $('[data-stage-open-invite]', panel || document)?.addEventListener('click', () => state?.mine && openInvite(state.mine, true));

    // ── Spectateur : invitation ─────────────────────────────────────────
    const cameraSwitch = modal?.querySelector('input[name="stage_camera"]');
    const preview = $('[data-stage-preview]', modal || document);

    async function stopLocal() {
        for (const kind of ['audio', 'video']) {
            const track = local[kind];
            if (!track) continue;
            // Déjà dépubliée si le serveur a retiré les droits : on arrête seulement la capture.
            const published = [...room.localParticipant.trackPublications.values()].some((pub) => pub.track === track);
            if (published) {
                try {
                    await room.localParticipant.unpublishTrack(track);
                } catch {
                    /* connexion perdue */
                }
            }
            track.stop();
            local[kind] = null;
        }
    }

    async function togglePreview() {
        if (cameraSwitch.checked) {
            try {
                local.video ??= await createLocalVideoTrack({ facingMode: 'user', resolution: VideoPresets.h360.resolution });
                local.video.attach(preview);
                preview.hidden = false;
                $('[data-stage-preview-off]', modal).hidden = true;
            } catch {
                cameraSwitch.checked = false;
                flash('Caméra inaccessible. Autorisez-la dans le navigateur, ou intervenez avec le micro seulement.', 'error');
            }
        } else {
            local.video?.detach(preview);
            local.video?.stop();
            local.video = null;
            preview.hidden = true;
            $('[data-stage-preview-off]', modal).hidden = false;
        }
    }
    cameraSwitch?.addEventListener('change', togglePreview);

    function openInvite(mine, force = false) {
        if (!modal || (invitedFor === mine.id && !force)) return;
        invitedFor = mine.id;
        window.openModal('stage-invite-modal');
        const counter = $('[data-stage-invite-countdown]', modal);
        const expires = mine.expiresAt ? new Date(mine.expiresAt).getTime() : Date.now() + (state?.inviteTimeout || 60) * 1000;
        clearInterval(countdown);
        const tick = () => {
            const left = Math.max(0, Math.round((expires - Date.now()) / 1000));
            counter.textContent = left;
            if (left <= 0) {
                closeInvite('L\'invitation a expiré. Vous pouvez refaire une demande.');
                load();
            }
        };
        tick();
        countdown = setInterval(tick, 1000);
        if (force) return;
        flash('C\'est votre tour : le diffuseur vous invite à l\'antenne.', 'info');
        try {
            navigator.vibrate?.(200);
        } catch {
            /* facultatif */
        }
    }

    function closeInvite(message) {
        clearInterval(countdown);
        countdown = null;
        invitedFor = null;
        if (modal && !modal.hidden) window.closeModal('stage-invite-modal');
        if (!onStage) {
            local.video?.detach(preview);
            local.video?.stop();
            local.video = null;
            if (cameraSwitch) cameraSwitch.checked = false;
            if (preview) preview.hidden = true;
            const off = $('[data-stage-preview-off]', modal || document);
            if (off) off.hidden = false;
        }
        if (message) flash(message, 'warning');
    }

    $('[data-stage-invite-decline]', modal || document)?.addEventListener('click', async () => {
        closeInvite();
        await withdraw();
        flash('Invitation refusée.');
    });

    const waitForPublishPermission = async () => {
        for (let i = 0; i < 40; i++) {
            if (room.localParticipant.permissions?.canPublish) return true;
            await new Promise((r) => setTimeout(r, 250));
        }
        return false;
    };

    $('[data-stage-invite-accept]', modal || document)?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        const label = btn.querySelector('span');
        if (room.state !== ConnectionState.Connected) {
            flash('Vous n\'êtes pas connecté au direct. Rechargez la page.', 'error');
            return;
        }
        btn.disabled = true;
        accepting = true;
        label.textContent = 'Connexion…';
        try {
            try {
                local.audio ??= await createLocalAudioTrack({ echoCancellation: true, noiseSuppression: true, autoGainControl: true });
            } catch {
                throw new Error('Micro inaccessible. Autorisez-le dans le navigateur (icône à gauche de l\'adresse), puis réessayez.');
            }
            setState(await api(config.urls.stageAccept, {
                method: 'POST',
                body: { identity: room.localParticipant.identity, camera: !!local.video },
            }));
            if (!(await waitForPublishPermission())) throw new Error('Le service vidéo n\'a pas encore ouvert votre micro. Réessayez.');

            await room.localParticipant.publishTrack(local.audio, { source: Track.Source.Microphone });
            if (local.video) {
                local.video.detach(preview);
                await room.localParticipant.publishTrack(local.video, {
                    source: Track.Source.Camera,
                    simulcast: true,
                    videoSimulcastLayers: [VideoPresets.h180],
                });
            }
            onStage = true;
            closeInvite();
            showSelfBar();
            refreshPip();
            flash('Vous êtes à l\'antenne. Parlez librement : tout le monde vous entend.');
        } catch (err) {
            flash(err.message || 'Impossible de rejoindre l\'antenne.', 'error');
            if (!onStage) {
                local.audio?.stop();
                local.audio = null;
            }
            load();
        } finally {
            accepting = false;
            btn.disabled = false;
            label.textContent = 'Rejoindre l\'antenne';
        }
    });

    // ── Spectateur : à l'antenne ────────────────────────────────────────
    function showSelfBar() {
        if (!selfBar) return;
        selfBar.hidden = false;
        syncSelfToggle('microphone', !!local.audio && !local.audio.isMuted);
        syncSelfToggle('camera', !!local.video && !local.video.isMuted);
    }

    function syncSelfToggle(kind, on) {
        const btn = $(`[data-stage-self-toggle="${kind}"]`, selfBar);
        if (!btn) return;
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        btn.classList.toggle('chip-active', on);
        btn.classList.toggle('chip', !on);
        const icons = kind === 'camera' ? ['fa-video', 'fa-video-slash'] : ['fa-microphone', 'fa-microphone-slash'];
        btn.querySelector('i').className = `fa-solid ${on ? icons[0] : icons[1]}`;
        btn.title = kind === 'camera' ? (on ? 'Couper ma caméra' : 'Activer ma caméra') : on ? 'Couper mon micro' : 'Rétablir mon micro';
    }

    selfBar?.addEventListener('click', async (e) => {
        const toggle = e.target.closest('[data-stage-self-toggle]');
        if (toggle) {
            toggle.disabled = true;
            try {
                if (toggle.dataset.stageSelfToggle === 'microphone') {
                    local.audio.isMuted ? await local.audio.unmute() : await local.audio.mute();
                    syncSelfToggle('microphone', !local.audio.isMuted);
                } else if (local.video) {
                    local.video.isMuted ? await local.video.unmute() : await local.video.mute();
                    syncSelfToggle('camera', !local.video.isMuted);
                } else {
                    local.video = await createLocalVideoTrack({ facingMode: 'user', resolution: VideoPresets.h360.resolution });
                    await room.localParticipant.publishTrack(local.video, { source: Track.Source.Camera, simulcast: true, videoSimulcastLayers: [VideoPresets.h180] });
                    syncSelfToggle('camera', true);
                }
            } catch {
                flash('Action impossible sur cet appareil.', 'error');
            } finally {
                toggle.disabled = false;
                refreshPip();
            }
            return;
        }
        if (e.target.closest('[data-stage-self-leave]')) {
            leaving = true;
            await withdraw();
            await stopGuest('left');
            leaving = false;
        }
    });

    /** Fin de l'intervention (volontaire, retirée, coupure) : micro et caméra libérés. */
    async function stopGuest(reason) {
        if (!onStage) return;
        onStage = false;
        await stopLocal();
        if (selfBar) selfBar.hidden = true;
        refreshPip();
        const why = reason || state?.lastEndReason;
        flash(ENDED_MESSAGES[why] || ENDED_MESSAGES.left, why === 'banned' || why === 'disconnected' ? 'warning' : 'success');
    }

    // Droits retirés par le serveur (fin décidée par le diffuseur) : on libère aussitôt la caméra.
    room.on(RoomEvent.ParticipantPermissionsChanged, () => {
        if (onStage && room.localParticipant.permissions && !room.localParticipant.permissions.canPublish) {
            stopGuest(leaving ? 'left' : state?.lastEndReason || 'removed');
        }
    });

    window.addEventListener('beforeunload', (e) => {
        if (onStage) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    setInterval(load, 15000);
    load();

    return {
        refreshPip,
        /** Message temps réel « stage » : l'état est rechargé (le sujet n'est jamais diffusé). */
        onData(msg) {
            if (msg.type !== 'stage') return;
            if (msg.event === 'ended' && msg.speaker?.user?.id === config.currentUserId) {
                state = { ...(state || {}), lastEndReason: msg.reason };
                if (onStage) stopGuest(msg.reason);
            }
            if (staff && msg.event === 'requested') flash(`${msg.speaker?.user?.displayName || 'Quelqu\'un'} demande à intervenir.`, 'info');
            load();
        },
        async stop() {
            onStage = false;
            clearInterval(countdown);
            await stopLocal();
            if (selfBar) selfBar.hidden = true;
            if (pip) pip.hidden = true;
            if (panel) panel.hidden = true;
        },
    };
}

// ═══════════════════════════════════════════════════════════════════════
// Studio du diffuseur
// ═══════════════════════════════════════════════════════════════════════
function initStudio() {
    const video = $('[data-live-video]');
    const overlay = $('[data-live-overlay]');
    const startBtn = $('[data-live-start]');
    const switchBtn = $('[data-live-switch-camera]');
    const interactions = initInteractions();
    // Caméra IP / encodeur (docs/fonctionnalites/lives-camera-ip.md) : le flux arrive dans la salle
    // sous l'identité « host-camera-… » ; le studio l'affiche en aperçu et ne publie rien lui-même.
    const external = !!config.source && config.source !== 'browser';
    const isCameraIdentity = (identity) => String(identity || '').startsWith('host-camera-');
    let status = config.status;
    let tracks = [];
    let facing = 'user';
    let wakeLock = null;
    let timer = null;

    const room = new Room({
        adaptiveStream: false,
        dynacast: true,
        publishDefaults: {
            simulcast: true,
            videoSimulcastLayers: [VideoPresets.h180, VideoPresets.h360],
            videoCodec: 'vp8',
        },
    });

    const setOverlay = (text) => {
        overlay.textContent = text || '';
        overlay.hidden = !text;
    };

    const setConn = (text, tone) => {
        $('[data-live-conn]').textContent = text;
        setDot($('[data-live-conn-dot]'), tone);
    };

    room.on(RoomEvent.ConnectionStateChanged, (state) => {
        const map = {
            [ConnectionState.Connecting]: ['Connexion…', 'warn'],
            [ConnectionState.Connected]: ['Connecté', 'ok'],
            [ConnectionState.Reconnecting]: ['Reconnexion…', 'warn'],
            [ConnectionState.SignalReconnecting]: ['Reconnexion…', 'warn'],
            [ConnectionState.Disconnected]: ['Déconnecté', 'bad'],
        };
        setConn(...(map[state] || ['—', 'idle']));
    });
    room.on(RoomEvent.Reconnecting, () => flash('Connexion instable : reconnexion en cours…', 'warning'));
    room.on(RoomEvent.Reconnected, () => flash('Connexion rétablie.'));
    room.on(RoomEvent.Disconnected, () => {
        if (status === 'live') {
            flash('Vous avez été déconnecté. Rechargez la page pour reprendre le direct.', 'error');
            startBtn.hidden = false;
            startBtn.disabled = false;
            startBtn.querySelector('span').textContent = 'Reprendre le direct';
        }
    });
    room.on(RoomEvent.ConnectionQualityChanged, (quality, participant) => {
        if (!participant.isLocal) return;
        const map = {
            [ConnectionQuality.Excellent]: ['Excellente', 'ok'],
            [ConnectionQuality.Good]: ['Bonne', 'ok'],
            [ConnectionQuality.Poor]: ['Faible : la vidéo est réduite', 'warn'],
            [ConnectionQuality.Lost]: ['Connexion perdue', 'bad'],
        };
        const [text, tone] = map[quality] || ['—', 'idle'];
        $('[data-live-quality]').textContent = text;
        setDot($('[data-live-quality-dot]'), tone);
    });
    room.on(RoomEvent.DataReceived, (payload) => {
        const msg = decode(payload);
        if (!msg) return;
        if (msg.type === 'ended') return onEnded(msg.reason);
        interactions.onData(msg);
        stage.onData(msg);
    });

    // Intervenant : son audio est joué ici (le diffuseur l'entend) ; sa vidéo va dans le médaillon.
    const stage = initStage(room);
    room.on(RoomEvent.TrackSubscribed, (track, _pub, participant) => {
        if (isCameraIdentity(participant.identity)) {
            // Aperçu de la caméra IP : image dans le lecteur, son coupé ici (pas d'écho dans la salle).
            if (track.kind === Track.Kind.Video) {
                track.attach(video);
                video.classList.remove('-scale-x-100');
                setCameraState(true);
            }
            return;
        }
        if (track.kind !== Track.Kind.Audio) return;
        const el = track.attach();
        el.hidden = true;
        document.body.append(el);
    });
    room.on(RoomEvent.TrackUnsubscribed, (track, _pub, participant) => {
        if (isCameraIdentity(participant.identity)) {
            if (track.kind === Track.Kind.Video) setCameraState(false);
            return;
        }
        if (track.kind === Track.Kind.Audio) track.detach().forEach((el) => el.remove());
    });

    function setCameraState(receiving) {
        const badge = $('[data-live-camera-state]');
        if (badge) {
            badge.className = receiving ? 'badge-active' : 'badge-pending';
            badge.textContent = receiving ? 'Flux reçu' : 'En attente du flux';
        }
        if (!external) return;
        if (receiving) {
            setOverlay(status === 'live' ? '' : 'Aperçu de la caméra : vous n\'êtes pas encore à l\'antenne.');
            if (status !== 'live') startBtn.disabled = false;
        } else {
            setOverlay(status === 'live'
                ? 'Le flux de la caméra est interrompu : les spectateurs voient un écran noir.'
                : 'En attente du flux de la caméra…');
            if (status !== 'live') startBtn.disabled = true;
        }
    }

    /** Caméra IP : connexion à la salle sans rien publier, pour recevoir l'aperçu. */
    async function connectForPreview() {
        setOverlay('En attente du flux de la caméra…');
        try {
            const creds = await api(config.urls.hostToken, { method: 'POST' });
            if (room.state !== ConnectionState.Connected) await room.connect(creds.url, creds.token);
            const camera = [...room.remoteParticipants.values()].find((p) => isCameraIdentity(p.identity));
            const pub = camera?.getTrackPublication(Track.Source.Camera);
            if (pub?.track) {
                pub.track.attach(video);
                setCameraState(true);
            }
        } catch (err) {
            flash(err.message || 'Connexion au service vidéo impossible.', 'error');
        }
    }

    async function prepareTracks() {
        try {
            tracks.forEach((t) => t.stop());
            tracks = await createLocalTracks({
                audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
                video: { facingMode: facing, resolution: VideoPresets.h720.resolution },
            });
            const cam = tracks.find((t) => t.kind === Track.Kind.Video);
            cam?.attach(video);
            video.classList.toggle('-scale-x-100', facing === 'user');
            setOverlay(status === 'live' ? '' : 'Aperçu : vous n\'êtes pas encore à l\'antenne.');
            startBtn.disabled = false;
        } catch (err) {
            setOverlay('Caméra ou micro inaccessible. Autorisez-les dans le navigateur puis rechargez la page.');
            flash(err.message || 'Caméra ou micro inaccessible.', 'error');
        }
    }

    async function goLive() {
        if (!navigator.onLine) {
            flash('Aucune connexion Internet.', 'error');
            return;
        }
        startBtn.disabled = true;
        const label = startBtn.querySelector('span');
        label.textContent = 'Connexion…';
        try {
            const creds = await api(config.urls.hostToken, { method: 'POST' });
            if (room.state !== ConnectionState.Connected) {
                await room.connect(creds.url, creds.token);
            }
            // Caméra IP : rien à publier depuis le navigateur.
            for (const track of external ? [] : tracks) {
                const already = [...room.localParticipant.trackPublications.values()].some((p) => p.track === track);
                if (!already) await room.localParticipant.publishTrack(track, { source: track.kind === Track.Kind.Video ? Track.Source.Camera : Track.Source.Microphone });
            }
            const live = await api(config.urls.goLive, { method: 'POST' });
            status = 'live';
            renderStatus('live');
            startBtn.hidden = true;
            setOverlay('');
            clearInterval(timer);
            timer = startTimer(live.startedAt || config.startedAt || new Date().toISOString());
            keepAwake();
            flash('Vous êtes en direct.');
        } catch (err) {
            startBtn.disabled = false;
            label.textContent = status === 'live' ? 'Reprendre le direct' : 'Passer à l\'antenne';
            flash(err.message || 'Connexion au service vidéo impossible.', 'error');
        }
    }

    async function keepAwake() {
        try {
            wakeLock = await navigator.wakeLock?.request('screen');
        } catch {
            /* non pris en charge */
        }
    }
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && status === 'live') keepAwake();
    });

    function onEnded(reason) {
        status = 'ended';
        renderStatus('ended');
        clearInterval(timer);
        tracks.forEach((t) => t.stop());
        room.disconnect();
        wakeLock?.release?.();
        stage.stop();
        setOverlay(reason === 'moderator' ? 'Un modérateur a mis fin au direct.' : 'Le direct est terminé.');
        $$('[data-live-toggle], [data-live-switch-camera], [data-live-start]').forEach((b) => (b.disabled = true));
        setTimeout(() => (window.location = config.urls.show), 2500);
    }

    startBtn.addEventListener('click', goLive);

    $$('[data-live-toggle]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const kind = btn.dataset.liveToggle === 'camera' ? Track.Kind.Video : Track.Kind.Audio;
            const track = tracks.find((t) => t.kind === kind);
            if (!track) return;
            const on = btn.getAttribute('aria-pressed') === 'true';
            on ? await track.mute() : await track.unmute();
            btn.setAttribute('aria-pressed', on ? 'false' : 'true');
            btn.classList.toggle('chip-active', !on);
            btn.classList.toggle('chip', on);
            const icon = btn.querySelector('i');
            if (kind === Track.Kind.Video) {
                icon.className = on ? 'fa-solid fa-video-slash' : 'fa-solid fa-video';
                setOverlay(on ? 'Caméra coupée : les spectateurs voient un écran noir.' : (status === 'live' ? '' : 'Aperçu : vous n\'êtes pas encore à l\'antenne.'));
            } else {
                icon.className = on ? 'fa-solid fa-microphone-slash' : 'fa-solid fa-microphone';
            }
        });
    });

    switchBtn.addEventListener('click', async () => {
        const cam = tracks.find((t) => t.kind === Track.Kind.Video);
        if (!cam) return;
        facing = facing === 'user' ? 'environment' : 'user';
        try {
            await cam.restartTrack({ facingMode: facing, resolution: VideoPresets.h720.resolution });
            video.classList.toggle('-scale-x-100', facing === 'user');
        } catch {
            facing = facing === 'user' ? 'environment' : 'user';
            flash('Impossible de changer de caméra sur cet appareil.', 'error');
        }
    });

    $('#live-end-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await api(config.urls.end, { method: 'POST' });
            onEnded('host');
        } catch (err) {
            flash(err.message, 'error');
        }
    });

    window.addEventListener('beforeunload', (e) => {
        if (status === 'live') {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // Rafraîchissement de secours : audience, commentaires masqués, fin décidée ailleurs.
    const refresh = async () => {
        try {
            const stats = await api(config.urls.stats);
            interactions.renderStats(stats);
            if (stats.status === 'ended' && status !== 'ended') onEnded('moderator');
        } catch {
            /* réessai plus tard */
        }
    };
    setInterval(refresh, 15000);
    setInterval(() => interactions.loadComments(), 30000);

    interactions.loadComments();
    refresh();
    if (external) {
        $$('[data-live-toggle], [data-live-switch-camera]').forEach((b) => (b.hidden = true));
        connectForPreview().then(() => {
            if (config.status === 'live') {
                startBtn.querySelector('span').textContent = 'Reprendre le direct';
                goLive();
            }
        });
        return;
    }
    prepareTracks().then(() => {
        // Direct déjà à l'antenne (page rechargée) : reprise automatique.
        if (config.status === 'live') {
            startBtn.querySelector('span').textContent = 'Reprendre le direct';
            goLive();
        }
    });
}

// ═══════════════════════════════════════════════════════════════════════
// Page des spectateurs
// ═══════════════════════════════════════════════════════════════════════
function initViewer() {
    const video = $('[data-live-video]');
    const overlay = $('[data-live-overlay]');
    const unmute = $('[data-live-unmute]');
    const interactions = initInteractions();
    let status = config.status;
    let timer = null;
    let room = null;
    let stage = null;

    const setOverlay = (text) => {
        overlay.textContent = text || '';
        overlay.hidden = !text;
    };

    function onEnded() {
        status = 'ended';
        renderStatus('ended');
        clearInterval(timer);
        stage?.stop();
        room?.disconnect();
        video.srcObject = null;
        setOverlay('Ce direct est terminé. Merci de l\'avoir suivi.');
        $$('[data-live-react]').forEach((b) => (b.disabled = true));
        $('[data-live-comment-form]')?.remove();
        $('#live-end-form')?.closest('div')?.remove();
    }

    async function connect() {
        if (status === 'ended') return onEnded();
        setOverlay('Connexion au direct…');
        room = new Room({ adaptiveStream: true, dynacast: true });
        stage = initStage(room);

        // Vidéo du diffuseur en grand ; celle d'un intervenant dans le médaillon (initStage).
        room.on(RoomEvent.TrackSubscribed, (track, _pub, participant) => {
            if (track.kind === Track.Kind.Video) {
                if (!isHostIdentity(participant.identity)) return;
                track.attach(video);
                setOverlay('');
            } else if (track.kind === Track.Kind.Audio) {
                const el = track.attach();
                el.hidden = true;
                document.body.append(el);
            }
        });
        room.on(RoomEvent.TrackUnsubscribed, (track, _pub, participant) => {
            track.detach().forEach((el) => el !== video && !el.hasAttribute('data-stage-pip-video') && el.remove());
            if (track.kind === Track.Kind.Video && isHostIdentity(participant.identity) && status !== 'ended') setOverlay('Le diffuseur a coupé sa caméra.');
        });
        room.on(RoomEvent.TrackMuted, (pub, participant) => {
            if (pub.kind === Track.Kind.Video && isHostIdentity(participant.identity)) setOverlay('Le diffuseur a coupé sa caméra.');
        });
        room.on(RoomEvent.TrackUnmuted, (pub, participant) => {
            if (pub.kind === Track.Kind.Video && isHostIdentity(participant.identity)) setOverlay('');
        });
        room.on(RoomEvent.AudioPlaybackStatusChanged, () => (unmute.hidden = room.canPlaybackAudio));
        room.on(RoomEvent.Reconnecting, () => setOverlay('Connexion instable : reconnexion…'));
        room.on(RoomEvent.Reconnected, () => setOverlay(''));
        room.on(RoomEvent.Disconnected, () => {
            if (status !== 'ended') setOverlay('Connexion perdue. Rechargez la page pour revenir au direct.');
        });
        room.on(RoomEvent.DataReceived, (payload) => {
            const msg = decode(payload);
            if (!msg) return;
            if (msg.type === 'ended') return onEnded();
            if (msg.type === 'status' && msg.status === 'live') {
                status = 'live';
                renderStatus('live');
                timer = startTimer(new Date().toISOString());
            }
            interactions.onData(msg);
            stage.onData(msg);
        });

        try {
            const creds = await api(config.urls.viewerToken, { method: 'POST' });
            await room.connect(creds.url, creds.token);
            unmute.hidden = room.canPlaybackAudio;
            const hasVideo = [...room.remoteParticipants.values()].some(
                (p) => isHostIdentity(p.identity) && p.getTrackPublication(Track.Source.Camera)?.isSubscribed,
            );
            if (!hasVideo) setOverlay(status === 'live' ? 'En attente de la vidéo du diffuseur…' : 'Le direct va bientôt commencer.');
        } catch (err) {
            if (err.status === 410) return onEnded();
            setOverlay(err.message || 'Connexion au direct impossible. Rechargez la page.');
        }
    }

    unmute.addEventListener('click', async () => {
        await room?.startAudio();
        unmute.hidden = true;
    });

    $('#live-end-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await api(config.urls.end, { method: 'POST' });
            onEnded();
            flash('Le direct a été coupé.');
        } catch (err) {
            flash(err.message, 'error');
        }
    });

    const refresh = async () => {
        try {
            const stats = await api(config.urls.stats);
            interactions.renderStats(stats);
            if (stats.status === 'ended' && status !== 'ended') onEnded();
        } catch {
            /* réessai plus tard */
        }
    };
    setInterval(refresh, 15000);
    setInterval(() => interactions.loadComments(), 30000);

    interactions.loadComments();
    refresh();
    if (status === 'live') timer = startTimer(config.startedAt);
    if (config.configured) connect();
}

// ═══════════════════════════════════════════════════════════════════════
// Qui regarde (liste visible de tous) — docs/fonctionnalites/lives.md
// ═══════════════════════════════════════════════════════════════════════
function initViewersList() {
    const opener = $('[data-live-viewers-open]');
    const modal = $('#live-viewers-modal');
    if (!opener || !modal || !config?.urls?.viewers) return;
    const list = $('[data-live-viewers-list]', modal);

    const row = (text, className = 'px-5 py-6 text-center text-sm text-slate-500') => {
        const li = document.createElement('li');
        li.className = className;
        li.textContent = text;
        return li;
    };

    async function load() {
        list.replaceChildren(row('Chargement…'));
        try {
            const data = await api(config.urls.viewers);
            $('[data-live-viewers-total]', modal).textContent = `· ${data.total}`;
            const counter = $('[data-live-viewers]');
            if (counter) counter.textContent = data.total; // le compteur suit la liste
            const people = data.people || [];
            list.replaceChildren(
                ...(people.length
                    ? people.map((p) => {
                          const li = document.createElement('li');
                          li.className = 'flex items-center gap-3 px-5 py-2.5';
                          const avatar = document.createElement('span');
                          avatar.className = 'inline-flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-200 text-[11px] font-semibold text-slate-700';
                          if (p.avatarUrl) {
                              const img = document.createElement('img');
                              img.src = p.avatarUrl;
                              img.alt = '';
                              img.className = 'h-full w-full object-cover';
                              avatar.append(img);
                          } else {
                              avatar.textContent = p.initials || '?';
                          }
                          const name = document.createElement('a');
                          name.href = `/profiles/${encodeURIComponent(p.id)}`;
                          name.className = 'min-w-0 truncate text-sm font-medium text-slate-900 hover:underline';
                          name.textContent = p.displayName || 'Utilisateur';
                          li.append(avatar, name);
                          if (p.isVerified) {
                              const badge = document.createElement('i');
                              badge.className = 'fa-solid fa-circle-check text-xs text-slate-500';
                              badge.title = 'Organisation vérifiée';
                              li.append(badge);
                          }
                          return li;
                      })
                    : [row(data.anonymous ? 'Aucun compte connecté ne regarde pour le moment.' : 'Personne ne regarde pour le moment.')]),
            );
            const anon = $('[data-live-viewers-anonymous]', modal);
            anon.hidden = !data.anonymous;
            anon.textContent = data.anonymous > 1
                ? `+ ${data.anonymous} visiteurs non connectés`
                : `+ ${data.anonymous} visiteur non connecté`;
        } catch (err) {
            list.replaceChildren(row(err.message || 'La liste n\'a pas pu être chargée.'));
        }
    }

    opener.addEventListener('click', () => {
        window.openModal('live-viewers-modal');
        load();
    });
}

// ─── Démarrage ──────────────────────────────────────────────────────────
initViewersList();
const preflight = $('[data-live-preflight]');
if (preflight) initPreflight(preflight);
if (config?.mode === 'studio') initStudio();
if (config?.mode === 'viewer') initViewer();

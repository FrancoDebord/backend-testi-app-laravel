import './bootstrap';
import './videos';
import './tts';

/*
|--------------------------------------------------------------------------
| Interface commune TestiApp (charte ARISE & SHINE Krea) — JavaScript natif
|--------------------------------------------------------------------------
*/

// ── Messages éphémères ──────────────────────────────────────────────────
const ALERT_ICONS = {
    success: 'fa-circle-check',
    error: 'fa-circle-exclamation',
    warning: 'fa-triangle-exclamation',
    info: 'fa-circle-info',
};

function flash(message, type = 'success') {
    const stack = document.getElementById('toast-stack');
    if (!stack) return;

    const toast = document.createElement('div');
    toast.className = `alert-${type} pointer-events-auto shadow-lg`;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

    const icon = document.createElement('i');
    icon.className = `fa-solid ${ALERT_ICONS[type] ?? ALERT_ICONS.info} mt-0.5`;
    const text = document.createElement('p');
    text.className = 'min-w-0 flex-1';
    text.textContent = message;

    toast.append(icon, text);
    stack.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

// ── Modales ─────────────────────────────────────────────────────────────
let lastFocused = null;

function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    lastFocused = document.activeElement;
    modal.hidden = false;
    document.body.classList.add('overflow-hidden');
    modal.querySelector('[data-modal-autofocus], input, select, textarea, button:not([data-modal-close])')?.focus();
}

function closeModal(modal) {
    if (!modal) return;
    modal.hidden = true;
    if (!document.querySelector('[data-modal]:not([hidden])')) {
        document.body.classList.remove('overflow-hidden');
    }
    lastFocused?.focus?.();
}

document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-modal-open]');
    if (opener) {
        e.preventDefault();
        openModal(opener.dataset.modalOpen);
        return;
    }
    const closer = e.target.closest('[data-modal-close]');
    if (closer) {
        e.preventDefault();
        closeModal(closer.closest('[data-modal]'));
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    const open = [...document.querySelectorAll('[data-modal]:not([hidden])')].pop();
    if (open) closeModal(open);
});

// ── Confirmation (remplace confirm()) ───────────────────────────────────
let pendingConfirmForm = null;

function openConfirmModal(formId, message, title = 'Confirmer', confirmLabel = 'Confirmer', icon = 'fa-circle-question') {
    const form = document.getElementById(formId);
    if (!form) return;
    pendingConfirmForm = form;

    document.getElementById('confirm-modal-title').textContent = title;
    document.getElementById('confirm-modal-message').textContent = message;
    document.getElementById('confirm-modal-label').textContent = confirmLabel;
    document.getElementById('confirm-modal-icon').className = `fa-solid ${icon}`;
    openModal('confirm-modal');
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('#confirm-modal-submit')) return;
    const form = pendingConfirmForm;
    pendingConfirmForm = null;
    closeModal(document.getElementById('confirm-modal'));
    if (!form) return;
    // requestSubmit() déclenche la validation HTML et l'indicateur de chargement.
    if (form.requestSubmit) form.requestSubmit();
    else form.submit();
});

// ── Bascules d'affichage (menu, filtres, lignes repliées) ───────────────
document.addEventListener('click', (e) => {
    const toggler = e.target.closest('[data-toggle]');
    if (!toggler) return;
    const target = document.getElementById(toggler.dataset.toggle);
    if (!target) return;
    e.preventDefault();
    const nowHidden = target.classList.toggle('hidden');
    toggler.setAttribute('aria-expanded', nowHidden ? 'false' : 'true');
});

// ── Barre latérale mobile ───────────────────────────────────────────────
function setSidebar(open) {
    const sidebar = document.getElementById('app-sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (!sidebar) return;
    sidebar.classList.toggle('-translate-x-full', !open);
    if (backdrop) backdrop.hidden = !open;
    document.querySelectorAll('[data-sidebar-toggle]').forEach((b) => b.setAttribute('aria-expanded', open ? 'true' : 'false'));
}

document.addEventListener('click', (e) => {
    if (e.target.closest('[data-sidebar-toggle]')) {
        const sidebar = document.getElementById('app-sidebar');
        setSidebar(sidebar?.classList.contains('-translate-x-full'));
    } else if (e.target.closest('[data-sidebar-close]')) {
        setSidebar(false);
    }
});

// ── Hors connexion ──────────────────────────────────────────────────────
function syncOnlineState() {
    document.querySelectorAll('[data-offline-notice]').forEach((el) => {
        el.hidden = navigator.onLine;
    });
}
window.addEventListener('online', syncOnlineState);
window.addEventListener('offline', syncOnlineState);

// Phase de capture : s'exécute avant tout autre gestionnaire d'envoi.
document.addEventListener(
    'submit',
    (e) => {
        const form = e.target;
        if (form.hasAttribute('data-online-only') && !navigator.onLine) {
            e.preventDefault();
            e.stopImmediatePropagation();
            flash("Vous êtes hors connexion. Votre saisie est conservée : l'envoi sera possible dès le retour de la connexion.", 'warning');
        }
    },
    true,
);

// ── Indicateurs de chargement ───────────────────────────────────────────
function setButtonLoading(button, label) {
    if (!button || button.dataset.loading) return;
    button.dataset.loading = '1';
    button.dataset.originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '';
    const spinner = document.createElement('span');
    spinner.className = 'spinner';
    const text = document.createElement('span');
    text.textContent = label;
    button.append(spinner, text);
}

function resetLoading() {
    document.getElementById('loading-overlay')?.setAttribute('hidden', '');
    document.querySelectorAll('[data-loading]').forEach((button) => {
        button.innerHTML = button.dataset.originalHtml ?? button.innerHTML;
        button.disabled = false;
        delete button.dataset.loading;
    });
}

document.addEventListener('submit', (e) => {
    if (e.defaultPrevented) return;
    const form = e.target;
    if (form.hasAttribute('data-no-loading')) return;

    const method = (form.getAttribute('method') || 'get').toLowerCase();
    const submitter = e.submitter || form.querySelector('[type="submit"]');

    if (form.hasAttribute('data-loading-inline') || method === 'get') {
        setButtonLoading(submitter, form.dataset.loadingLabel || 'Recherche…');
        return;
    }

    const label = form.dataset.loadingLabel || 'Traitement en cours…';
    setButtonLoading(submitter, label);
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        document.getElementById('loading-overlay-label').textContent = label;
        overlay.hidden = false;
    }
});

// Retour arrière du navigateur (cache de page) : on retire les indicateurs.
window.addEventListener('pageshow', (e) => {
    if (e.persisted) resetLoading();
});

// ── Engagements obligatoires : bouton d'envoi désactivé ────────────────
function syncSubmitGuards(form) {
    const checks = [...form.querySelectorAll('[data-required-check]')];
    const ok = checks.every((c) => c.checked);
    form.querySelectorAll('[data-submit-guard]').forEach((b) => {
        if (!b.dataset.loading) b.disabled = !ok;
    });
}

document.addEventListener('change', (e) => {
    const form = e.target.closest?.('form');
    if (form?.querySelector('[data-submit-guard]')) syncSubmitGuards(form);
});

// ── Sauvegarde automatique du brouillon ─────────────────────────────────
function autosaveFields(form) {
    return [...form.elements].filter(
        (el) => el.name && el.name !== '_token' && el.name !== '_method' && el.type !== 'file' && el.type !== 'password' && !el.hasAttribute('data-autosave-ignore'),
    );
}

function initAutosave(form) {
    const key = `testiapp:draft:${form.dataset.autosave}`;
    const status = form.querySelector('[data-autosave-status]');
    const storage = (() => {
        try {
            return window.localStorage;
        } catch {
            return null;
        }
    })();
    if (!storage) return;

    // Restauration (sauf si le serveur a renvoyé la saisie après une erreur).
    if (form.dataset.hasOld !== '1') {
        try {
            const saved = JSON.parse(storage.getItem(key) || 'null');
            if (saved?.values) {
                autosaveFields(form).forEach((el) => {
                    if (!(el.name in saved.values)) return;
                    const value = saved.values[el.name];
                    if (el.type === 'radio') el.checked = el.value === value;
                    else if (el.type === 'checkbox') el.checked = Boolean(value);
                    else el.value = value;
                });
                if (status) status.textContent = `Brouillon restauré (enregistré le ${new Date(saved.at).toLocaleString('fr-FR')}).`;
            }
        } catch {
            storage.removeItem(key);
        }
    }

    let timer = null;
    const save = () => {
        const values = {};
        autosaveFields(form).forEach((el) => {
            if (el.type === 'radio') {
                if (el.checked) values[el.name] = el.value;
            } else if (el.type === 'checkbox') {
                values[el.name] = el.checked;
            } else {
                values[el.name] = el.value;
            }
        });
        try {
            storage.setItem(key, JSON.stringify({ at: Date.now(), values }));
            if (status) status.textContent = `Brouillon enregistré automatiquement à ${new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}.`;
        } catch {
            /* quota dépassé : on ignore */
        }
    };

    form.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(save, 600);
    });
    form.addEventListener('change', save);
    form.addEventListener('submit', (e) => {
        if (!e.defaultPrevented) storage.removeItem(key);
    });

    form.dispatchEvent(new CustomEvent('autosave:ready'));
}

// ── Éditeur de texte mis en forme (gras, italique, émojis) ─────────────
// Format enregistré : Markdown léger (**gras**, *italique*, \* = astérisque),
// identique à App\Support\RichText côté serveur.
const RT_STAR = String.fromCharCode(0xe000);
const NBSP_RE = new RegExp(String.fromCharCode(0xa0), "g");

function richTextToHtml(md) {
    let h = (md || '')
        .replace(/\r\n?/g, '\n')
        .replace(/\\\*/g, RT_STAR)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    h = h.replace(/\*\*\*(?=\S)([^\n]+?)(?<=\S)\*\*\*/gu, '<strong><em>$1</em></strong>');
    h = h.replace(/\*\*(?=\S)([^\n]+?)(?<=\S)\*\*/gu, '<strong>$1</strong>');
    h = h.replace(/(?<!\*)\*(?=[^\s*])([^\n]*?)(?<=[^\s*])\*(?!\*)/gu, '<em>$1</em>');
    h = h.replace(new RegExp(RT_STAR, 'g'), '*');
    return h ? h.split('\n').map((line) => `<div>${line || '<br>'}</div>`).join('') : '';
}

function richTextFromDom(root) {
    // Parcourt le contenu et relève, pour chaque morceau de texte, son style réellement affiché.
    const lines = [[]];
    const current = () => lines[lines.length - 1];
    const newLine = () => lines.push([]);
    const isBlock = (el) => /^(DIV|P|LI|H[1-6]|BLOCKQUOTE|UL|OL)$/.test(el.nodeName);

    const walk = (node) => {
        node.childNodes.forEach((n) => {
            if (n.nodeType === Node.TEXT_NODE) {
                const text = n.nodeValue.replace(NBSP_RE, " ").replace(/\n/g, ' ');
                if (!text) return;
                const style = getComputedStyle(n.parentElement);
                current().push({ text, b: parseInt(style.fontWeight, 10) >= 600, i: style.fontStyle === 'italic' });
            } else if (n.nodeName === 'BR') {
                newLine();
            } else if (n.nodeType === Node.ELEMENT_NODE) {
                const block = isBlock(n);
                if (block && current().length) newLine();
                walk(n);
                if (block && current().length) newLine();
            }
        });
    };
    walk(root);

    const out = lines.map((runs) => {
        const merged = [];
        runs.forEach((r) => {
            const last = merged[merged.length - 1];
            if (last && last.b === r.b && last.i === r.i) last.text += r.text;
            else merged.push({ ...r });
        });
        return merged
            .map((r) => {
                const text = r.text.replace(/\*/g, '\\*');
                const mark = r.b && r.i ? '***' : r.b ? '**' : r.i ? '*' : '';
                const m = text.match(/^(\s*)([\s\S]*?)(\s*)$/);
                return mark && m[2] ? m[1] + mark + m[2] + mark + m[3] : text;
            })
            .join('');
    });

    return out.join('\n').replace(/\s+$/, '');
}

function initRichEditor(wrap) {
    const content = wrap.querySelector('[data-rt-content]');
    const source = wrap.querySelector('[data-rt-source]');
    const panel = wrap.querySelector('[data-rt-emojis]');
    const emojiToggle = wrap.querySelector('[data-rt-emoji-toggle]');
    const error = wrap.nextElementSibling?.matches('[data-rt-error]') ? wrap.nextElementSibling : null;
    const form = wrap.closest('form');
    let savedRange = null;

    const render = () => {
        content.innerHTML = richTextToHtml(source.value);
    };
    const sync = () => {
        if (!content.textContent.trim()) content.innerHTML = '';
        source.value = richTextFromDom(content);
        if (error && source.value.trim()) error.hidden = true;
    };
    const selectionInside = () => {
        const sel = window.getSelection();
        return sel.rangeCount && content.contains(sel.getRangeAt(0).commonAncestorContainer);
    };
    const refreshButtons = () => {
        if (!selectionInside()) return;
        wrap.querySelectorAll('[data-rt-command]').forEach((btn) => {
            btn.setAttribute('aria-pressed', document.queryCommandState(btn.dataset.rtCommand) ? 'true' : 'false');
        });
    };
    const restoreSelection = () => {
        // Le curseur est déjà dans l'éditeur : on n'y touche pas.
        if (document.activeElement === content && selectionInside()) return;
        content.focus();
        if (savedRange) {
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(savedRange);
        }
    };

    render();
    try {
        document.execCommand('defaultParagraphSeparator', false, 'div');
    } catch {
        /* navigateur ancien */
    }

    content.addEventListener('input', sync);
    content.addEventListener('keyup', refreshButtons);
    content.addEventListener('mouseup', refreshButtons);
    document.addEventListener('selectionchange', () => {
        if (selectionInside()) {
            savedRange = window.getSelection().getRangeAt(0).cloneRange();
            refreshButtons();
        }
    });

    // Collage et dépôt : texte seul (pas de mise en forme ni de HTML externe).
    content.addEventListener('paste', (e) => {
        e.preventDefault();
        document.execCommand('insertText', false, e.clipboardData.getData('text/plain'));
    });
    content.addEventListener('drop', (e) => e.preventDefault());

    wrap.querySelectorAll('[data-rt-command]').forEach((btn) => {
        btn.addEventListener('mousedown', (e) => e.preventDefault()); // garde la sélection
        btn.addEventListener('click', () => {
            restoreSelection();
            document.execCommand(btn.dataset.rtCommand);
            sync();
            refreshButtons();
        });
    });

    emojiToggle?.addEventListener('mousedown', (e) => e.preventDefault());
    emojiToggle?.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        emojiToggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
    });
    panel?.querySelectorAll('[data-rt-emoji]').forEach((btn) => {
        btn.addEventListener('mousedown', (e) => e.preventDefault());
        btn.addEventListener('click', () => {
            restoreSelection();
            document.execCommand('insertText', false, btn.dataset.rtEmoji);
            sync();
        });
    });

    form?.addEventListener('autosave:ready', render);
    form?.addEventListener('submit', (e) => {
        sync();
        if (source.hasAttribute('data-rt-required') && !source.value.trim()) {
            e.preventDefault();
            if (error) error.hidden = false;
            content.focus();
            content.scrollIntoView({ block: 'center' });
        }
    });
}

// ── Inscription : personne ou organisation ─────────────────────────────
// Formulaire [data-account-type-form] : n'affiche que le bloc
// [data-account-section] du type choisi (radios name="account_type") et
// rend obligatoires ses champs [data-section-required]. Sans JavaScript,
// tous les blocs restent visibles et le serveur valide selon le type.
function initAccountTypeForm(form) {
    const sync = () => {
        const type = form.querySelector('input[name="account_type"]:checked')?.value || 'individual';
        form.querySelectorAll('[data-account-section]').forEach((section) => {
            const active = section.dataset.accountSection === type;
            section.hidden = !active;
            section.querySelectorAll('[data-section-required]').forEach((field) => {
                field.required = active;
            });
        });
    };
    form.querySelectorAll('[data-account-nojs-hint]').forEach((hint) => {
        hint.hidden = true;
    });
    form.querySelectorAll('input[type="radio"][name="account_type"]').forEach((radio) => {
        radio.addEventListener('change', sync);
    });
    sync();
}

// ── Liste avec recherche (pays) ─────────────────────────────────────────
// <select data-country-select> (components/country-select) → champ de recherche + liste filtrée.
// Le <select> reste dans le formulaire (masqué) : c'est lui qui est envoyé.
const normalize = (text) =>
    String(text)
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '') // accents retirés : « cote » trouve « Côte d'Ivoire »
        .toLowerCase()
        .trim();

let comboboxCount = 0;

function initCountrySelect(select) {
    const options = [...select.options].filter((o) => o.value);
    const listId = `combobox-list-${++comboboxCount}`;

    const wrap = document.createElement('div');
    wrap.className = 'relative';
    const input = document.createElement('input');
    input.type = 'text';
    input.id = select.id; // le <label for> désigne désormais le champ de recherche
    select.id = `${select.id}-select`;
    input.className = 'form-input pr-9';
    input.autocomplete = 'off';
    input.spellcheck = false;
    input.placeholder = select.dataset.placeholder || 'Rechercher un pays…';
    if (select.getAttribute('aria-label')) input.setAttribute('aria-label', select.getAttribute('aria-label'));
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', listId);
    if (select.getAttribute('aria-invalid')) input.setAttribute('aria-invalid', 'true');

    const chevron = document.createElement('i');
    chevron.className = 'fa-solid fa-chevron-down pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-slate-400';
    chevron.setAttribute('aria-hidden', 'true');

    const list = document.createElement('ul');
    list.id = listId;
    list.setAttribute('role', 'listbox');
    // min-w : la liste d'un champ étroit (indicatif) reste lisible.
    list.className = 'absolute z-30 mt-1 max-h-64 w-full min-w-[16rem] overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg';
    list.hidden = true;

    // Drapeau du pays choisi, à gauche dans le champ.
    const selectedFlag = document.createElement('img');
    selectedFlag.alt = '';
    selectedFlag.width = 20;
    selectedFlag.height = 15;
    selectedFlag.className = 'pointer-events-none absolute top-1/2 left-3 h-[15px] w-5 -translate-y-1/2 rounded-[2px] object-cover ring-1 ring-slate-200';
    selectedFlag.hidden = true;

    select.hidden = true;
    select.tabIndex = -1;
    select.after(wrap);
    wrap.append(selectedFlag, input, chevron, list);

    // Drapeaux de la liste chargés seulement quand ils deviennent visibles dans la liste (économie de données).
    const flagObserver =
        'IntersectionObserver' in window
            ? new IntersectionObserver(
                  (entries) =>
                      entries.forEach((entry) => {
                          if (!entry.isIntersecting) return;
                          flagObserver.unobserve(entry.target);
                          entry.target.src = entry.target.dataset.src;
                      }),
                  { root: list, rootMargin: '120px 0px' },
              )
            : null;

    function flag(url) {
        if (!url) {
            const blank = document.createElement('span');
            blank.className = 'h-[15px] w-5 shrink-0 rounded-[2px] bg-slate-100';
            blank.setAttribute('aria-hidden', 'true');
            return blank;
        }
        const img = document.createElement('img');
        img.alt = ''; // décoratif : le nom du pays suit
        img.width = 20;
        img.height = 15;
        img.decoding = 'async';
        img.className = 'h-[15px] w-5 shrink-0 rounded-[2px] object-cover ring-1 ring-slate-200';
        if (flagObserver) {
            img.dataset.src = url;
            flagObserver.observe(img);
        } else {
            img.src = url;
        }
        return img;
    }

    function showFlag(url) {
        if (url) selectedFlag.src = url;
        selectedFlag.hidden = !url;
        input.classList.toggle('pl-10', !!url);
    }

    let shown = [];
    let active = -1;
    // Texte affiché une fois le choix fait : data-label (« +229 » pour un indicatif), sinon le libellé.
    const display = (o) => o?.dataset.label || o?.textContent || '';
    const label = () => (select.value ? display(select.selectedOptions[0]) : '');
    const syncFlag = () => showFlag(select.value ? select.selectedOptions[0]?.dataset.flag : null);
    input.value = label();
    syncFlag();

    function render(query) {
        const q = normalize(query);
        // Pays qui commencent par la saisie d'abord, puis ceux qui la contiennent (« cote » → Côte d'Ivoire).
        shown = q
            ? options
                  .filter((o) => normalize(o.textContent).includes(q))
                  .sort((a, b) => Number(normalize(b.textContent).startsWith(q)) - Number(normalize(a.textContent).startsWith(q)))
            : options;

        if (!shown.length) {
            const li = document.createElement('li');
            li.className = 'px-3 py-2 text-sm text-slate-500';
            li.textContent = select.dataset.emptyText || 'Aucun pays ne correspond à votre recherche.';
            list.replaceChildren(li);
            active = -1;
            return highlight();
        }

        flagObserver?.disconnect();
        list.replaceChildren(
            ...shown.map((o, i) => {
                const li = document.createElement('li');
                li.id = `${listId}-${i}`;
                li.dataset.index = i;
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', o.value === select.value ? 'true' : 'false');
                li.className = 'flex cursor-pointer items-center gap-2.5 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100';
                const name = document.createElement('span');
                name.className = 'min-w-0 flex-1';
                name.textContent = o.textContent;
                li.append(flag(o.dataset.flag), name);
                if (o.value === select.value) {
                    li.classList.add('font-semibold', 'text-slate-900');
                    const check = document.createElement('i');
                    check.className = 'fa-solid fa-check text-xs text-slate-500';
                    check.setAttribute('aria-hidden', 'true');
                    li.append(check);
                }
                return li;
            }),
        );
        active = q ? 0 : Math.max(0, shown.findIndex((o) => o.value === select.value));
        highlight();
    }

    function highlight() {
        [...list.children].forEach((li, i) => li.classList.toggle('bg-slate-100', i === active));
        if (active >= 0) {
            input.setAttribute('aria-activedescendant', `${listId}-${active}`);
            list.children[active]?.scrollIntoView({ block: 'nearest' });
        } else {
            input.removeAttribute('aria-activedescendant');
        }
    }

    function open(query = '') {
        render(query);
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    function close() {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    }

    function choose(option) {
        select.value = option ? option.value : '';
        select.dispatchEvent(new Event('change', { bubbles: true }));
        input.value = label();
        syncFlag();
        close();
    }

    input.addEventListener('focus', () => {
        input.select();
        open('');
    });
    input.addEventListener('click', () => list.hidden && open(''));
    input.addEventListener('input', () => {
        showFlag(null); // pendant la saisie, le drapeau du choix précédent induirait en erreur
        open(input.value);
    });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (list.hidden) return open(input.value === label() ? '' : input.value);
            if (!shown.length) return;
            active = e.key === 'ArrowDown' ? Math.min(shown.length - 1, active + 1) : Math.max(0, active - 1);
            highlight();
        } else if (e.key === 'Enter' && !list.hidden) {
            e.preventDefault(); // choisit sans envoyer le formulaire
            if (active >= 0) choose(shown[active]);
        } else if (e.key === 'Escape' && !list.hidden) {
            e.preventDefault();
            e.stopPropagation(); // ne ferme pas une éventuelle modale
            input.value = label();
            syncFlag();
            close();
        }
    });

    // mousedown : garder le focus dans le champ pendant le choix (souris et toucher).
    list.addEventListener('mousedown', (e) => e.preventDefault());
    list.addEventListener('click', (e) => {
        const li = e.target.closest('[role="option"]');
        if (li) choose(shown[Number(li.dataset.index)]);
    });

    // En quittant le champ : une saisie exacte est retenue, un champ vidé retire le pays, sinon on revient au choix.
    input.addEventListener('blur', () => {
        const typed = normalize(input.value);
        if (!typed) return choose(null);
        const exact = options.find((o) => normalize(o.textContent) === typed || normalize(display(o)) === typed);
        if (exact) return choose(exact);
        input.value = label();
        syncFlag();
        close();
    });

    // Valeur changée ailleurs (brouillon restauré, réinitialisation du formulaire).
    select.addEventListener('change', () => {
        if (document.activeElement === input) return;
        input.value = label();
        syncFlag();
    });
    select.form?.addEventListener('reset', () =>
        setTimeout(() => {
            input.value = label();
            syncFlag();
        }),
    );
}

// ── Suivre un compte (components/follow-button) ─────────────────────────
// Bascule sans rechargement ; sans JavaScript, le formulaire est envoyé normalement.
document.addEventListener('submit', async (e) => {
    const form = e.target.closest('form[data-follow-form]');
    if (!form) return;
    e.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    if (button.disabled) return;

    const following = button.getAttribute('aria-pressed') === 'true';
    button.disabled = true;
    try {
        const res = await fetch(form.action, {
            method: following ? 'DELETE' : 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || (res.status === 429 ? 'Trop d\'actions en peu de temps. Patientez un instant.' : 'Action impossible. Réessayez.'));

        const now = !!data.following;
        const name = button.getAttribute('aria-label').replace(/^(Ne plus suivre|Suivre) /, '');
        button.setAttribute('aria-pressed', now ? 'true' : 'false');
        button.setAttribute('aria-label', `${now ? 'Ne plus suivre' : 'Suivre'} ${name}`);
        button.querySelector('[data-follow-label]').textContent = now ? 'Abonné' : 'Suivre';
        button.querySelector('[data-follow-icon]').className = `fa-solid ${now ? 'fa-check' : 'fa-user-plus'}`;
        // Bouton principal (en-tête de profil) : rouge pour « Suivre », secondaire une fois abonné.
        const primary = form.dataset.followPrimary === '1' && !now;
        button.classList.toggle('btn-primary', primary);
        button.classList.toggle('btn-secondary', !primary);
        form.querySelector('input[name="_method"]')?.remove();
        if (now) {
            const method = document.createElement('input');
            Object.assign(method, { type: 'hidden', name: '_method', value: 'DELETE' });
            form.append(method);
        }
        if (Number.isFinite(data.followerCount)) {
            document.querySelectorAll(`[data-follower-count="${CSS.escape(form.dataset.followUser)}"]`)
                .forEach((el) => (el.textContent = Number(data.followerCount).toLocaleString('fr-FR')));
        }
        flash(data.message || (now ? 'Abonnement effectué.' : 'Abonnement annulé.'));
    } catch (err) {
        flash(err.message, 'error');
    } finally {
        button.disabled = false;
    }
});

// Indicatif qui suit le pays choisi : <select data-follow-country="country"> (components/phone-input).
function initFollowCountry(dial) {
    const source = dial.form?.querySelector(`select[name="${CSS.escape(dial.dataset.followCountry)}"]`);
    source?.addEventListener('change', () => {
        const code = source.selectedOptions[0]?.dataset.code;
        if (!code || dial.value === code) return;
        dial.value = code;
        dial.dispatchEvent(new Event('change', { bubbles: true })); // met à jour le champ et son drapeau
    });
}

// Copier la valeur d'un champ : <button data-copy="id-du-champ"> ; afficher un secret : <button data-reveal="id">.
document.addEventListener('click', async (e) => {
    const copy = e.target.closest('[data-copy]');
    if (copy) {
        const field = document.getElementById(copy.dataset.copy);
        try {
            await navigator.clipboard.writeText(field?.value ?? '');
            flash('Copié dans le presse-papiers.');
        } catch {
            field?.select();
            flash('Copie impossible : sélectionnez le texte puis copiez-le.', 'warning');
        }
        return;
    }
    const reveal = e.target.closest('[data-reveal]');
    if (reveal) {
        const field = document.getElementById(reveal.dataset.reveal);
        if (!field) return;
        const shown = field.type === 'text';
        field.type = shown ? 'password' : 'text';
        const what = reveal.dataset.revealLabel || 'la clé';
        reveal.setAttribute('aria-label', `${shown ? 'Afficher' : 'Masquer'} ${what}`);
        reveal.querySelector('i').className = shown ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
    }
});

// Photo de couverture (profile/edit) : aperçu avant l'envoi ; « Retirer » masque l'aperçu.
function initCoverPreview(input) {
    const form = input.form;
    const preview = form?.querySelector('[data-cover-preview]');
    const remove = form?.querySelector('[data-cover-remove]');
    if (!preview) return;
    const saved = preview.getAttribute('src') || '';
    let objectUrl = null;

    const render = () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;
        const file = input.files?.[0];
        if (file && file.type.startsWith('image/')) {
            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            preview.hidden = false;
            if (remove) remove.checked = false;
        } else if (saved && !remove?.checked) {
            preview.src = saved;
            preview.hidden = false;
        } else {
            preview.hidden = true;
        }
    };

    input.addEventListener('change', render);
    remove?.addEventListener('change', () => {
        if (remove.checked) input.value = '';
        render();
    });
}

// ── Événements (docs/fonctionnalites/evenements.md) ─────────────────────
// Carrousel [data-carousel] : défilement natif (glisser au doigt), boutons précédent / suivant,
// points [data-carousel-dot] (aria-current), flèches du clavier quand le carrousel a le focus.
function initCarousel(root) {
    const track = root.querySelector('[data-carousel-track]');
    const slides = [...root.querySelectorAll('[data-carousel-slide]')];
    const dots = [...root.querySelectorAll('[data-carousel-dot]')];
    if (!track || slides.length < 2) return;

    const current = () => Math.round(track.scrollLeft / Math.max(track.clientWidth, 1));
    const go = (index) => {
        const i = (index + slides.length) % slides.length;
        track.scrollTo({ left: i * track.clientWidth, behavior: 'smooth' });
    };
    const sync = () => {
        const i = current();
        dots.forEach((dot, d) => dot.setAttribute('aria-current', d === i ? 'true' : 'false'));
    };

    root.querySelector('[data-carousel-prev]')?.addEventListener('click', () => go(current() - 1));
    root.querySelector('[data-carousel-next]')?.addEventListener('click', () => go(current() + 1));
    dots.forEach((dot) => dot.addEventListener('click', () => go(Number(dot.dataset.carouselDot))));
    track.addEventListener('scroll', () => window.requestAnimationFrame(sync), { passive: true });
    root.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') { e.preventDefault(); go(current() - 1); }
        if (e.key === 'ArrowRight') { e.preventDefault(); go(current() + 1); }
    });
}

// Invités [data-guests] : ajout / retrait de lignes (modèle <template data-guest-template>, __INDEX__), data-max lignes au plus.
function initGuests(root) {
    const list = root.querySelector('[data-guests-list]');
    const template = root.querySelector('template[data-guest-template]');
    const add = root.querySelector('[data-guest-add]');
    const limit = root.querySelector('[data-guests-limit]');
    const max = Number(root.dataset.max) || 10;
    if (!list || !template || !add) return;
    let next = list.querySelectorAll('[data-guest-row]').length;
    const count = () => list.querySelectorAll('[data-guest-row]').length;

    const sync = () => {
        const full = count() >= max;
        add.disabled = full;
        if (limit) limit.hidden = !full;
    };

    add.addEventListener('click', () => {
        if (count() >= max) return;
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(next++)));
        list.lastElementChild?.querySelector('input')?.focus();
        sync();
    });
    list.addEventListener('click', (e) => {
        const remove = e.target.closest('[data-guest-remove]');
        if (!remove) return;
        const row = remove.closest('[data-guest-row]');
        if (count() > 1) {
            row.remove();
        } else {
            row.querySelectorAll('input').forEach((input) => { input.value = ''; });
        }
        add.focus();
        sync();
    });
    sync();
}

// Images choisies [data-event-images] : vignettes d'aperçu, data-max fichiers au plus.
function initEventImages(input) {
    const form = input.form;
    const preview = form?.querySelector('[data-event-images-preview]');
    const error = form?.querySelector('[data-event-images-error]');
    const max = Number(input.dataset.max) || 6;
    let urls = [];

    input.addEventListener('change', () => {
        urls.forEach((u) => URL.revokeObjectURL(u));
        urls = [];
        preview?.replaceChildren();
        const files = [...(input.files || [])];
        const message = files.length > max ? `Vous pouvez choisir ${max} image${max > 1 ? 's' : ''} au plus.` : '';
        input.setCustomValidity(message);
        input.setAttribute('aria-invalid', message ? 'true' : 'false');
        if (error) {
            error.textContent = message;
            error.hidden = !message;
        }

        files.filter((f) => f.type.startsWith('image/')).forEach((file) => {
            const url = URL.createObjectURL(file);
            urls.push(url);
            const li = document.createElement('li');
            li.className = 'aspect-video overflow-hidden rounded-md border border-slate-200 bg-slate-100';
            const img = document.createElement('img');
            img.src = url;
            img.alt = file.name;
            img.className = 'h-full w-full object-cover';
            li.appendChild(img);
            preview?.appendChild(li);
        });
    });
}

// « Enregistrer comme témoignage » : une seule modale ; l'adresse du commentaire vient du bouton.
document.addEventListener('click', (e) => {
    const button = e.target.closest('[data-promote-url]');
    const form = document.getElementById('promote-form');
    if (button && form) form.action = button.dataset.promoteUrl;
});

// ── Choisir une personne (components/user-picker) ───────────────────────
// Résultats au fil de la saisie (JSON, route users.search) ; chaque résultat est un formulaire POST
// « Ajouter » (envoi classique). Sans JavaScript, la recherche recharge la page (?personne=…).
function initUserPicker(root) {
    const form = root.querySelector('[data-user-picker-form]');
    const input = root.querySelector('[data-user-picker-input]');
    const list = root.querySelector('[data-user-picker-results]');
    const status = root.querySelector('[data-user-picker-status]');
    if (!form || !input || !list) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    let timer = null;
    let controller = null;

    const setStatus = (text) => { if (status) status.textContent = text; };

    const avatar = (person) => {
        if (person.avatarUrl) {
            const img = document.createElement('img');
            img.src = person.avatarUrl;
            img.alt = '';
            img.className = 'h-8 w-8 text-xs shrink-0 rounded-full object-cover';
            return img;
        }
        const span = document.createElement('span');
        span.className = 'h-8 w-8 text-xs inline-flex shrink-0 items-center justify-center rounded-full bg-slate-200 font-semibold text-slate-700';
        span.setAttribute('aria-hidden', 'true');
        span.textContent = person.initials || '?';
        return span;
    };

    const row = (person) => {
        const li = document.createElement('li');
        li.className = 'flex items-center gap-3 py-2.5';
        const name = document.createElement('span');
        name.className = 'min-w-0 flex-1 truncate text-sm font-semibold text-slate-900';
        name.textContent = person.displayName;

        const add = document.createElement('form');
        add.method = 'POST';
        add.action = root.dataset.addUrl;
        add.dataset.loadingLabel = 'Ajout…';
        for (const [field, value] of [['_token', csrf], ['user_id', person.id]]) {
            const hidden = document.createElement('input');
            Object.assign(hidden, { type: 'hidden', name: field, value });
            add.append(hidden);
        }
        const button = document.createElement('button');
        button.type = 'submit';
        button.className = 'btn-secondary btn-sm';
        button.setAttribute('aria-label', `Ajouter ${person.displayName}`);
        button.innerHTML = '<i class="fa-solid fa-user-plus" aria-hidden="true"></i>';
        button.append('Ajouter');
        add.append(button);

        li.append(avatar(person), name, add);
        return li;
    };

    const search = async () => {
        const q = input.value.trim();
        controller?.abort();
        if (q.length < 2) {
            list.replaceChildren();
            setStatus('');
            return;
        }
        controller = new AbortController();
        const url = new URL(root.dataset.searchUrl, window.location.origin);
        url.searchParams.set('q', q);
        if (root.dataset.exclude) url.searchParams.set('exclude', root.dataset.exclude);
        setStatus('Recherche…');
        try {
            const res = await fetch(url, {
                credentials: 'same-origin',
                signal: controller.signal,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error(res.status === 429 ? 'Trop de recherches en peu de temps. Patientez un instant.' : 'La recherche a échoué. Réessayez.');
            const people = (await res.json()).data ?? [];
            list.replaceChildren(...people.map(row));
            setStatus(people.length ? `${people.length} personne${people.length > 1 ? 's' : ''} trouvée${people.length > 1 ? 's' : ''}.` : 'Aucune personne trouvée.');
        } catch (err) {
            if (err.name === 'AbortError') return;
            setStatus(err.message);
        }
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(search, 300);
    });
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        clearTimeout(timer);
        search();
    });
}

// ── Initialisation ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-carousel]').forEach(initCarousel);
    document.querySelectorAll('[data-guests]').forEach(initGuests);
    document.querySelectorAll('[data-user-picker]').forEach(initUserPicker);
    document.querySelectorAll('input[data-event-images]').forEach(initEventImages);
    document.querySelectorAll('select[data-country-select]').forEach(initCountrySelect);
    document.querySelectorAll('select[data-follow-country]').forEach(initFollowCountry);
    document.querySelectorAll('form[data-account-type-form]').forEach(initAccountTypeForm);
    document.querySelectorAll('[data-rich-editor]').forEach(initRichEditor);
    document.querySelectorAll('input[data-cover-input]').forEach(initCoverPreview);
    syncOnlineState();
    document.querySelectorAll('form[data-autosave]').forEach(initAutosave);
    document.querySelectorAll('form').forEach((form) => {
        if (form.querySelector('[data-submit-guard]')) syncSubmitGuards(form);
    });
    document.querySelectorAll('[data-dismiss-alert]').forEach((btn) => {
        btn.addEventListener('click', () => btn.closest('[role="alert"], [role="status"]')?.remove());
    });
});

window.flash = flash;
window.openModal = openModal;
window.closeModal = (id) => closeModal(document.getElementById(id));
window.openConfirmModal = openConfirmModal;

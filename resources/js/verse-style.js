/*
|--------------------------------------------------------------------------
| Verset du jour : design et réglages du texte (docs/fonctionnalites/designs-du-verset.md)
|--------------------------------------------------------------------------
| Même règle que l'app : un design choisi vaut pour le jour du choix (design_chosen_on), sauf
| « Toujours utiliser ce design ». Membre connecté → PUT /verset-du-jour/style (session) ;
| visiteur → localStorage, avec les noms de l'app (verse_design_id, verse_font…).
| Éléments : [data-verse-design] (surface), [data-verse-thumb] (vignette du sélecteur),
| #verse-style-config (JSON), #verse-style-panel (sélecteur).
*/

const STORAGE_KEYS = {
    design_id: 'verse_design_id',
    design_chosen_on: 'verse_design_chosen_on',
    design_always: 'verse_design_always',
    font: 'verse_font',
    text_size: 'verse_text_size',
    text_align: 'verse_text_align',
};
const PREFIXES = ['verse-design--', 'verse-font--', 'verse-align--', 'verse-tone--'];

const configEl = document.getElementById('verse-style-config');
if (configEl) init(JSON.parse(configEl.textContent));

function init(config) {
    const designs = new Map(config.designs.map((d) => [d.id, d]));
    const valid = {
        font: config.fonts,
        text_size: config.sizes,
        text_align: config.aligns,
    };
    let style = { ...config.defaults, ...(config.signedIn ? config.style || {} : readLocal(valid)) };

    const effective = () => {
        const chosen = style.design_id;
        if (chosen && designs.has(chosen) && (style.design_always || style.design_chosen_on === config.today)) return chosen;
        return config.designOfTheDay;
    };

    // ── Rendu ────────────────────────────────────────────────────────────
    function setClasses(el, classes) {
        [...el.classList].filter((c) => PREFIXES.some((p) => c.startsWith(p))).forEach((c) => el.classList.remove(c));
        el.classList.add(...classes);
    }

    function apply(fade = false) {
        const id = effective();
        const tone = designs.get(id)?.tone || 'light';
        const surfaces = document.querySelectorAll('[data-verse-design]');
        const paint = () => {
            surfaces.forEach((el) => {
                setClasses(el, ['verse-design--' + id, 'verse-font--' + style.font, 'verse-align--' + style.text_align, 'verse-tone--' + tone]);
                el.dataset.designId = id;
                const sizes = JSON.parse(el.dataset.verseSizes || '{}');
                if (sizes[style.text_size]) el.style.setProperty('--verse-size', sizes[style.text_size] + 'px');
            });
            document.querySelectorAll('[data-verse-thumb]').forEach((el) => {
                setClasses(el, ['verse-design--' + el.dataset.verseThumb, 'verse-font--' + style.font, 'verse-align--' + style.text_align]);
            });
            fitAll();
            clampCards();
            syncPanel(id);
        };
        if (!fade || matchMedia('(prefers-reduced-motion: reduce)').matches) {
            paint();
            return;
        }
        surfaces.forEach((el) => el.classList.add('is-switching'));
        setTimeout(() => {
            paint();
            requestAnimationFrame(() => surfaces.forEach((el) => el.classList.remove('is-switching')));
        }, 180);
    }

    // Plein écran : réduit le texte de 1 px en 1 px jusqu'à ce qu'il tienne dans le premier écran.
    function fitAll() {
        document.querySelectorAll('[data-verse-fit]').forEach((el) => {
            const sizes = JSON.parse(el.dataset.verseSizes || '{}');
            let size = Math.round(sizes[style.text_size] || 22);
            const min = Number(el.dataset.verseFit) || 22;
            const header = document.querySelector('header.sticky')?.offsetHeight || 64;
            const available = window.innerHeight - header;
            el.style.setProperty('--verse-size', size + 'px');
            while (el.offsetHeight > available + 1 && size > min) {
                size -= 1;
                el.style.setProperty('--verse-size', size + 'px');
            }
        });
    }

    // Carte : au-delà de 8 lignes, le texte est coupé et « Lire en entier » apparaît.
    function clampCards() {
        document.querySelectorAll('[data-verse-card]').forEach((card) => {
            const text = card.querySelector('.verse-design__text');
            const more = card.querySelector('[data-verse-more]');
            if (text && more) more.hidden = text.scrollHeight <= text.clientHeight + 1;
        });
    }

    // Halos : rayon relatif au plus grand côté de la surface.
    if ('ResizeObserver' in window) {
        const ro = new ResizeObserver((entries) => {
            entries.forEach((e) => {
                const { width, height } = e.contentRect;
                e.target.style.setProperty('--verse-long', Math.max(width, height) + 'px');
            });
        });
        document.querySelectorAll('[data-verse-design], [data-verse-thumb]').forEach((el) => ro.observe(el));
    }

    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            fitAll();
            clampCards();
        }, 150);
    });

    // ── Enregistrement ───────────────────────────────────────────────────
    async function save() {
        if (!config.signedIn) {
            writeLocal(style);
            return;
        }
        try {
            const res = await fetch(config.endpoint, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(style),
            });
            if (res.ok) style = { ...style, ...(await res.json()).data };
        } catch (e) {
            /* hors ligne : le choix reste affiché pour cette visite */
        }
    }

    function update(changes) {
        style = { ...style, ...changes };
        apply(true);
        save();
    }

    // ── Sélecteur ────────────────────────────────────────────────────────
    const panel = document.getElementById('verse-style-panel');

    function syncPanel(id) {
        if (!panel) return;
        panel.querySelectorAll('[data-pick-design]').forEach((b) => {
            const on = b.dataset.pickDesign === id;
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
            b.querySelector('[data-verse-check]')?.toggleAttribute('hidden', !on);
        });
        const always = panel.querySelector('[data-verse-always]');
        if (always) always.checked = !!style.design_always;
        [['font', 'pickFont'], ['text_size', 'pickSize'], ['text_align', 'pickAlign']].forEach(([field, key]) => {
            panel.querySelectorAll(`[data-${key.replace('pick', 'pick-').toLowerCase()}]`).forEach((b) => {
                const on = b.dataset[key] === style[field];
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
                b.className = b.className.replace(/\bchip(-active)?\b/, on ? 'chip-active' : 'chip');
            });
        });
    }

    document.querySelectorAll('[data-verse-style-open]').forEach((b) => b.addEventListener('click', () => panel?.showModal()));
    panel?.querySelectorAll('[data-verse-style-close]').forEach((b) => b.addEventListener('click', () => panel.close()));
    panel?.addEventListener('click', (e) => {
        if (e.target === panel) panel.close(); // clic sur le fond
    });

    panel?.querySelectorAll('[data-verse-tab]').forEach((tab) => {
        tab.addEventListener('click', () => {
            panel.querySelectorAll('[data-verse-tab]').forEach((t) => {
                const on = t === tab;
                t.className = t.className.replace(/\btab(-active)?\b/, on ? 'tab-active' : 'tab');
                t.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            panel.querySelectorAll('[data-verse-pane]').forEach((p) => (p.hidden = p.dataset.versePane !== tab.dataset.verseTab));
        });
    });

    panel?.querySelectorAll('[data-pick-design]').forEach((b) =>
        b.addEventListener('click', () => update({ design_id: b.dataset.pickDesign, design_chosen_on: config.today })),
    );
    panel?.querySelector('[data-verse-always]')?.addEventListener('change', (e) => {
        const changes = { design_always: e.target.checked };
        if (e.target.checked && !style.design_id) Object.assign(changes, { design_id: effective(), design_chosen_on: config.today });
        update(changes);
    });
    panel?.querySelectorAll('[data-pick-font]').forEach((b) => b.addEventListener('click', () => update({ font: b.dataset.pickFont })));
    panel?.querySelectorAll('[data-pick-size]').forEach((b) => b.addEventListener('click', () => update({ text_size: b.dataset.pickSize })));
    panel?.querySelectorAll('[data-pick-align]').forEach((b) => b.addEventListener('click', () => update({ text_align: b.dataset.pickAlign })));

    apply(false);
    document.fonts?.ready.then(() => {
        fitAll();
        clampCards();
    });
}

// ── Stockage local (visiteurs) ─────────────────────────────────────────────
function readLocal(valid) {
    const out = {};
    try {
        const get = (k) => localStorage.getItem(STORAGE_KEYS[k]);
        if (get('design_id')) out.design_id = get('design_id');
        if (/^\d{4}-\d{2}-\d{2}$/.test(get('design_chosen_on') || '')) out.design_chosen_on = get('design_chosen_on');
        if (get('design_always') !== null) out.design_always = get('design_always') === 'true';
        ['font', 'text_size', 'text_align'].forEach((k) => {
            if (valid[k].includes(get(k))) out[k] = get(k);
        });
    } catch (e) {
        /* stockage indisponible : valeurs par défaut */
    }
    return out;
}

function writeLocal(style) {
    try {
        Object.entries(STORAGE_KEYS).forEach(([field, key]) => {
            const v = style[field];
            if (v === null || v === undefined || v === '') localStorage.removeItem(key);
            else localStorage.setItem(key, String(v));
        });
    } catch (e) {
        /* stockage indisponible */
    }
}

{{--
    Autocomplétion des barres de recherche (docs/fonctionnalites/recherche.md).
    Inclus une fois par le gabarit (layouts/app) : il suffit d'ajouter sur un <input> :
      data-search-autocomplete="all|testimonies|users|events|prayer|bible|local"
        (« local » : recherches récentes seulement, sans appel au serveur — ex. carnet privé)
      data-search-recent-key="…"  (facultatif : liste de recherches récentes propre à la barre ; défaut = portée)
      data-search-submit="first-result" (facultatif : Entrée sans choix ouvre le premier résultat — ex. livres de la Bible)

    - Saisie ≥ 2 caractères : GET /api/v1/search/suggest (public), anti-rebond 250 ms, requête obsolète annulée,
      suggestions de texte (partie saisie surlignée, flèche « Compléter » qui remplit sans lancer) et résultats
      directs (icône par type, ouvre la page).
    - Champ vide : recherches récentes (localStorage, effaçables une à une ou toutes).
    - Liste déroulante ARIA « combobox » : ↑ ↓, Entrée, Échap, Tab ; annonce du nombre de propositions.
    - Hors ligne ou serveur indisponible : récentes seulement. La soumission du formulaire reste celle de la page.
    Script en ligne (comme components/mention-input) : aucune recompilation des fichiers JavaScript n'est nécessaire.
--}}
@once
<style>
    .sa-box{position:absolute;left:0;right:0;top:100%;z-index:40;margin-top:.25rem;max-height:min(24rem,70vh);overflow-y:auto;border:1px solid #e2e8f0;border-radius:.75rem;background:#fff;padding:.25rem 0;box-shadow:0 10px 25px -5px rgb(15 23 42/.15);font-size:.875rem;text-align:left}
    .sa-head{display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.5rem .75rem .25rem;font-size:.7rem;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#64748b}
    .sa-head button{font-size:.75rem;font-weight:500;text-transform:none;letter-spacing:0;color:var(--color-primary-600,#4f46e5);background:none;border:0;cursor:pointer;padding:0}
    .sa-opt{display:flex;align-items:center;gap:.625rem;min-height:2.5rem;padding:.375rem .75rem;cursor:pointer;color:#0f172a}
    .sa-opt[aria-selected=true],.sa-opt:hover{background:#f1f5f9}
    .sa-ico{flex-shrink:0;width:1.75rem;height:1.75rem;border-radius:9999px;display:inline-flex;align-items:center;justify-content:center;background:#f1f5f9;color:#64748b;font-size:.8rem;object-fit:cover}
    .sa-txt{min-width:0;flex:1}
    .sa-lbl{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .sa-sub{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.75rem;color:#64748b}
    .sa-lbl mark{background:none;color:inherit;font-weight:700}
    .sa-act{flex-shrink:0;width:2rem;height:2rem;border-radius:9999px;border:0;background:none;color:#94a3b8;cursor:pointer;display:inline-flex;align-items:center;justify-content:center}
    .sa-act:hover,.sa-act:focus-visible{background:#e2e8f0;color:#334155}
    .sa-msg{padding:.5rem .75rem;color:#64748b}
    .sa-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
</style>
@push('scripts')
<script>
(function () {
    const SUGGEST_URL = @json(url('/api/v1/search/suggest'));
    const MIN = 2, DELAY = 250, MAX_RECENT = 8;
    const ICONS = {
        testimony: 'fa-solid fa-book-open', user: 'fa-solid fa-user', event: 'fa-solid fa-calendar-days',
        category: 'fa-solid fa-tag', bible: 'fa-solid fa-book-bible', prayer_session: 'fa-solid fa-hands-praying',
        suggestion: 'fa-solid fa-magnifying-glass', recent: 'fa-solid fa-clock-rotate-left',
    };
    const TYPE_NAMES = { testimony: 'Témoignage', user: 'Compte', event: 'Événement', category: 'Catégorie', bible: 'Livre de la Bible', prayer_session: 'Session de prière' };
    let seq = 0;

    // Pliage sans accents ni casse, caractère par caractère (garde les positions d'origine).
    const foldChar = (c) => c.normalize('NFD').replace(/\p{M}/gu, '').replace(/œ/gi, 'oe').replace(/æ/gi, 'ae').toLowerCase();
    function findMatch(text, q) {
        const chars = Array.from(text);
        let norm = '', map = [];
        chars.forEach((c, i) => { for (const f of foldChar(c)) { norm += f; map.push(i); } });
        const nq = Array.from(q.trim()).map(foldChar).join('');
        if (!nq) return null;
        let p = norm.startsWith(nq) ? 0 : norm.indexOf(' ' + nq);
        if (p > 0) p += 1; else if (p < 0) p = norm.indexOf(nq);
        if (p < 0) return null;
        const start = map[p], end = map[p + nq.length - 1] + 1;
        return [start, end - start];
    }
    function highlighted(text, range) {
        const span = document.createElement('span');
        span.className = 'sa-lbl';
        const chars = Array.from(text);
        if (!range || range[1] <= 0) { span.textContent = text; return span; }
        const [s, l] = range;
        const mark = document.createElement('mark');
        mark.textContent = chars.slice(s, s + l).join('');
        span.append(chars.slice(0, s).join(''), mark, chars.slice(s + l).join(''));
        return span;
    }

    const storage = {
        key: (input) => 'testiapp.search.recent.' + (input.dataset.searchRecentKey || input.dataset.searchAutocomplete || 'all'),
        get(input) { try { return JSON.parse(localStorage.getItem(this.key(input)) || '[]').filter((s) => typeof s === 'string'); } catch { return []; } },
        set(input, list) { try { localStorage.setItem(this.key(input), JSON.stringify(list.slice(0, MAX_RECENT))); } catch {} },
        add(input, q) {
            q = q.trim().replace(/\s+/g, ' ').slice(0, 100);
            if (q.length < MIN) return;
            const f = Array.from(q).map(foldChar).join('');
            this.set(input, [q, ...this.get(input).filter((s) => Array.from(s).map(foldChar).join('') !== f)]);
        },
        remove(input, q) { this.set(input, this.get(input).filter((s) => s !== q)); },
    };

    function setup(input) {
        if (input.dataset.saReady) return;
        input.dataset.saReady = '1';
        const id = 'sa-' + (++seq);
        const scope = input.dataset.searchAutocomplete || 'all';
        const holder = input.parentElement;
        if (getComputedStyle(holder).position === 'static') holder.style.position = 'relative';

        const box = document.createElement('div');
        box.className = 'sa-box';
        box.id = id + '-list';
        box.setAttribute('role', 'listbox');
        box.setAttribute('aria-label', 'Suggestions de recherche');
        box.hidden = true;
        const live = document.createElement('div');
        live.className = 'sa-sr';
        live.setAttribute('role', 'status');
        live.setAttribute('aria-live', 'polite');
        holder.append(box, live);

        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', box.id);
        input.setAttribute('autocomplete', 'off');

        let options = [], active = -1, timer = null, controller = null, lastQuery = null;

        const close = () => {
            box.hidden = true; box.replaceChildren(); options = []; active = -1;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        };
        const select = (i) => {
            options.forEach((o, j) => o.el.setAttribute('aria-selected', j === i ? 'true' : 'false'));
            active = i;
            if (options[i]) { input.setAttribute('aria-activedescendant', options[i].el.id); options[i].el.scrollIntoView({ block: 'nearest' }); }
            else input.removeAttribute('aria-activedescendant');
        };
        const submit = (text) => {
            input.value = text;
            close();
            const form = input.form;
            if (!form) { input.dispatchEvent(new Event('change', { bubbles: true })); return; }
            storage.add(input, text);
            form.requestSubmit ? form.requestSubmit() : form.submit();
        };
        const fill = (text) => {
            input.value = text + ' ';
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
            input.dispatchEvent(new Event('input', { bubbles: true }));
        };
        const open = (item) => {
            storage.add(input, input.value);
            close();
            const url = new URL(item.url, window.location.origin);
            // Livre de la Bible : rester dans la traduction en cours de lecture.
            if (item.type === 'bible' && input.dataset.searchTranslation) url.searchParams.set('translation', input.dataset.searchTranslation);
            window.location.href = url.toString();
        };

        function header(text, action) {
            const h = document.createElement('div');
            h.className = 'sa-head';
            h.setAttribute('role', 'presentation');
            const t = document.createElement('span');
            t.textContent = text;
            h.append(t);
            if (action) {
                const b = document.createElement('button');
                b.type = 'button'; b.textContent = action.label; b.tabIndex = -1;
                b.addEventListener('mousedown', (e) => { e.preventDefault(); action.run(); });
                h.append(b);
            }
            box.append(h);
        }

        function option({ icon, avatar, label, range, sub, srLabel, onChoose, extra }) {
            const el = document.createElement('div');
            el.className = 'sa-opt';
            el.id = `${id}-opt-${options.length}`;
            el.setAttribute('role', 'option');
            el.setAttribute('aria-selected', 'false');
            if (srLabel) el.setAttribute('aria-label', srLabel);
            let ico;
            if (avatar) { ico = document.createElement('img'); ico.src = avatar; ico.alt = ''; ico.loading = 'lazy'; }
            else { ico = document.createElement('span'); const i = document.createElement('i'); i.className = icon; ico.append(i); }
            ico.classList.add('sa-ico');
            ico.setAttribute('aria-hidden', 'true');
            const txt = document.createElement('span');
            txt.className = 'sa-txt';
            txt.append(highlighted(label, range));
            if (sub) { const s = document.createElement('span'); s.className = 'sa-sub'; s.textContent = sub; txt.append(s); }
            el.append(ico, txt);
            if (extra) {
                const b = document.createElement('button');
                b.type = 'button'; b.className = 'sa-act'; b.tabIndex = -1;
                b.title = extra.title; b.setAttribute('aria-label', extra.title);
                const i = document.createElement('i'); i.className = extra.icon; i.setAttribute('aria-hidden', 'true');
                if (extra.rotate) i.style.transform = 'rotate(-45deg)';
                b.append(i);
                b.addEventListener('mousedown', (e) => { e.preventDefault(); e.stopPropagation(); extra.run(); });
                el.append(b);
            }
            el.addEventListener('mousedown', (e) => { e.preventDefault(); onChoose(); });
            el.addEventListener('mousemove', () => { if (active !== options.indexOf(entry)) select(options.indexOf(entry)); });
            const entry = { el, onChoose, fill: extra?.fillText };
            options.push(entry);
            box.append(el);
        }

        function show(msg) {
            box.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            live.textContent = msg;
            if (!options.length) close();
        }

        function renderRecent() {
            close();
            const recent = storage.get(input);
            if (!recent.length) return;
            header('Recherches récentes', { label: 'Tout effacer', run: () => { storage.set(input, []); close(); live.textContent = 'Recherches récentes effacées.'; } });
            recent.forEach((q) => option({
                icon: ICONS.recent, label: q, srLabel: `${q}, recherche récente`,
                onChoose: () => submit(q),
                extra: { icon: 'fa-solid fa-xmark', title: `Retirer « ${q} » des recherches récentes`, run: () => { storage.remove(input, q); renderRecent(); input.focus(); } },
            }));
            show(`${recent.length} recherche${recent.length > 1 ? 's' : ''} récente${recent.length > 1 ? 's' : ''}. Flèches haut et bas pour parcourir.`);
        }

        function render(q, data) {
            close();
            const recent = storage.get(input).filter((s) => findMatch(s, q) && s.trim() !== q.trim()).slice(0, 3);
            const suggestions = (data?.suggestions || []).filter((s) => !recent.includes(s.text));
            const results = data?.results || [];
            if (recent.length) {
                header('Récentes');
                recent.forEach((s) => option({ icon: ICONS.recent, label: s, range: findMatch(s, q), srLabel: `${s}, recherche récente`,
                    onChoose: () => submit(s),
                    extra: { icon: 'fa-solid fa-arrow-up', rotate: true, title: `Compléter avec « ${s} »`, run: () => fill(s), fillText: s } }));
            }
            if (suggestions.length) {
                header('Suggestions');
                suggestions.forEach((s) => option({ icon: ICONS.suggestion, label: s.text, range: s.highlight || findMatch(s.text, q), srLabel: s.text,
                    onChoose: () => submit(s.text),
                    extra: { icon: 'fa-solid fa-arrow-up', rotate: true, title: `Compléter avec « ${s.text} »`, run: () => fill(s.text), fillText: s.text } }));
            }
            if (results.length) {
                header('Accès direct');
                results.forEach((r) => option({ icon: ICONS[r.type] || ICONS.suggestion, avatar: r.avatar, label: r.label,
                    range: r.highlight || findMatch(r.label, q), sub: r.sublabel || TYPE_NAMES[r.type],
                    srLabel: `${r.label}, ${TYPE_NAMES[r.type] || ''}${r.verified ? ', vérifié' : ''}${r.sublabel ? ', ' + r.sublabel : ''}`,
                    onChoose: () => open(r) }));
                options.forEach((o, i) => { if (i >= options.length - results.length) o.result = results[i - (options.length - results.length)]; });
            }
            const n = options.length;
            show(n ? `${n} proposition${n > 1 ? 's' : ''}. Flèches haut et bas pour parcourir, Entrée pour choisir.` : '');
        }

        async function query() {
            const q = input.value;
            controller?.abort();
            if (q.trim().length < MIN) { lastQuery = null; return q.trim() === '' ? renderRecent() : close(); }
            if (q === lastQuery && !box.hidden) return;
            lastQuery = q;
            if (scope === 'local' || !navigator.onLine) return render(q, null);
            controller = new AbortController();
            const url = new URL(SUGGEST_URL, window.location.origin);
            url.searchParams.set('q', q.trim());
            url.searchParams.set('scope', scope);
            url.searchParams.set('limit', '6');
            try {
                const res = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
                if (!res.ok) throw new Error(String(res.status));
                const data = (await res.json()).data;
                if (document.activeElement !== input || input.value !== q) return;
                render(q, data);
            } catch (err) {
                if (err.name !== 'AbortError' && document.activeElement === input) render(q, null);
            }
        }

        input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(query, input.value.trim() ? DELAY : 0); });
        input.addEventListener('focus', () => { if (!input.value.trim()) renderRecent(); });
        input.addEventListener('blur', () => setTimeout(() => { if (document.activeElement !== input) close(); }, 120));
        input.addEventListener('keydown', (e) => {
            const opened = !box.hidden && options.length;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!opened) { lastQuery = null; return query(); }
                select((active + 1) % options.length);
            } else if (e.key === 'ArrowUp' && opened) {
                e.preventDefault();
                select(active <= 0 ? options.length - 1 : active - 1);
            } else if (e.key === 'ArrowRight' && opened && options[active]?.fill && input.selectionStart === input.value.length) {
                e.preventDefault(); fill(options[active].fill); // → : compléter sans lancer
            } else if (e.key === 'Enter') {
                if (opened && options[active]) { e.preventDefault(); options[active].onChoose(); return; }
                if (input.dataset.searchSubmit === 'first-result') {
                    const first = options.find((o) => o.result);
                    e.preventDefault();
                    if (first) first.onChoose();
                    return;
                }
                storage.add(input, input.value);
                close();
            } else if (e.key === 'Escape' && !box.hidden) {
                e.preventDefault(); close();
            } else if (e.key === 'Tab') {
                close();
            }
        });
        input.form?.addEventListener('submit', () => { storage.add(input, input.value); close(); });
    }

    const init = () => document.querySelectorAll('input[data-search-autocomplete]').forEach(setup);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
@endpush
@endonce

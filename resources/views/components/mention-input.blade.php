{{--
    Autocomplétion des mentions « @ » (docs/fonctionnalites/mentions.md) :
    @include('components.mention-input') une fois dans la page, puis data-mention-input sur chaque <textarea>
    (facultatif : data-mention-testimony="{id}" pour proposer d'abord les participants du témoignage).

    - « @ » + quelques lettres : comptes actifs proposés (route users.mentions, JSON, session + CSRF du site) ;
      anti-rebond 300 ms, requête précédente annulée, clavier (↑ ↓ Entrée Échap).
    - Le texte reste lisible (« @Nom ») ; à l'envoi, chaque mention choisie devient le jeton
      « @[Nom](user:id) » que le serveur valide (comptes actifs seulement) avant d'enregistrer.
    - Bouton « Répondre » portant data-mention + data-mention-id : la mention préremplie est reconnue aussi.
    Script en ligne (comme events/show) : aucune recompilation des fichiers JavaScript n'est nécessaire.
--}}
@once
@push('scripts')
<script>
(function () {
    const SEARCH_URL = @json(route('users.mentions'));
    const store = new WeakMap(); // textarea → Map(nom → id)
    const mentionsOf = (ta) => { if (!store.has(ta)) store.set(ta, new Map()); return store.get(ta); };
    const escapeRe = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    let box = null, items = [], active = -1, target = null, timer = null, controller = null, range = null;

    function close() {
        box?.remove();
        box = null; items = []; active = -1; range = null;
        target?.setAttribute('aria-expanded', 'false');
    }

    /** « @requête » juste avant le curseur, ou null. */
    function queryAt(ta) {
        const caret = ta.selectionStart;
        if (caret !== ta.selectionEnd) return null;
        const before = ta.value.slice(0, caret);
        const m = before.match(/(?:^|[\s(])@([^@\n\[\]]{0,40})$/u);
        if (!m) return null;
        const q = m[1];
        if (/\s{2,}/.test(q) || q.split(' ').length > 3) return null;
        return { q, start: caret - q.length - 1, end: caret };
    }

    function render(ta, people, status) {
        close();
        target = ta;
        box = document.createElement('ul');
        box.setAttribute('role', 'listbox');
        box.className = 'absolute left-0 right-0 z-30 mt-1 max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg';
        if (!people.length) {
            const li = document.createElement('li');
            li.className = 'px-3 py-2 text-slate-500';
            li.textContent = status;
            box.append(li);
        }
        items = people.map((p, i) => {
            const li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.id = `mention-option-${i}`;
            li.className = 'flex cursor-pointer items-center gap-2 px-3 py-2 hover:bg-slate-50';
            const avatar = document.createElement(p.avatarUrl ? 'img' : 'span');
            avatar.className = 'h-7 w-7 shrink-0 rounded-full object-cover bg-slate-200 inline-flex items-center justify-center text-xs font-semibold text-slate-700';
            if (p.avatarUrl) { avatar.src = p.avatarUrl; avatar.alt = ''; } else { avatar.textContent = p.initials || '?'; avatar.setAttribute('aria-hidden', 'true'); }
            const name = document.createElement('span');
            name.className = 'min-w-0 flex-1 truncate font-medium text-slate-900';
            name.textContent = p.displayName;
            li.append(avatar, name);
            if (p.isVerified) {
                const badge = document.createElement('i');
                badge.className = 'fa-solid fa-circle-check text-primary-600';
                badge.setAttribute('aria-label', 'Compte vérifié');
                li.append(badge);
            }
            if (p.isFollowing) {
                const tag = document.createElement('span');
                tag.className = 'text-xs text-slate-500';
                tag.textContent = 'Abonné';
                li.append(tag);
            }
            li.addEventListener('mousedown', (e) => { e.preventDefault(); choose(p); });
            box.append(li);
            return { li, person: p };
        });
        const holder = ta.parentElement;
        if (getComputedStyle(holder).position === 'static') holder.style.position = 'relative';
        holder.append(box);
        ta.setAttribute('aria-expanded', 'true');
        if (items.length) highlight(0);
    }

    function highlight(i) {
        items.forEach((it, j) => it.li.classList.toggle('bg-slate-100', j === i));
        active = i;
        if (items[i]) { target?.setAttribute('aria-activedescendant', items[i].li.id); items[i].li.scrollIntoView({ block: 'nearest' }); }
    }

    function choose(person) {
        const ta = target;
        const r = range || queryAt(ta);
        if (!ta || !r) return close();
        const insert = `@${person.displayName} `;
        ta.value = ta.value.slice(0, r.start) + insert + ta.value.slice(r.end);
        const caret = r.start + insert.length;
        ta.setSelectionRange(caret, caret);
        mentionsOf(ta).set(person.displayName, person.id);
        close();
        ta.focus();
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    }

    async function search(ta) {
        const r = queryAt(ta);
        controller?.abort();
        if (!r) return close();
        range = r;
        controller = new AbortController();
        const url = new URL(SEARCH_URL, window.location.origin);
        url.searchParams.set('q', r.q);
        url.searchParams.set('limit', '8');
        if (ta.dataset.mentionTestimony) url.searchParams.set('testimony_id', ta.dataset.mentionTestimony);
        try {
            const res = await fetch(url, {
                credentials: 'same-origin',
                signal: controller.signal,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error(res.status === 429 ? 'Trop de recherches. Patientez un instant.' : 'Recherche indisponible.');
            const people = (await res.json()).data ?? [];
            if (document.activeElement !== ta) return;
            const keep = range;
            render(ta, people, r.q ? 'Aucun compte trouvé.' : 'Tapez un nom après « @ ».');
            range = keep;
        } catch (err) {
            if (err.name !== 'AbortError') render(ta, [], err.message);
        }
    }

    document.addEventListener('input', (e) => {
        const ta = e.target.closest?.('textarea[data-mention-input]');
        if (!ta) return;
        clearTimeout(timer);
        if (!queryAt(ta)) { controller?.abort(); return close(); }
        timer = setTimeout(() => search(ta), 300);
    });

    document.addEventListener('keydown', (e) => {
        if (!box || e.target !== target) return;
        if (e.key === 'ArrowDown' && items.length) { e.preventDefault(); highlight((active + 1) % items.length); }
        else if (e.key === 'ArrowUp' && items.length) { e.preventDefault(); highlight((active - 1 + items.length) % items.length); }
        else if ((e.key === 'Enter' || e.key === 'Tab') && items[active]) { e.preventDefault(); choose(items[active].person); }
        else if (e.key === 'Escape') { e.preventDefault(); close(); }
    });

    document.addEventListener('focusout', (e) => { if (e.target === target) setTimeout(close, 150); });

    // « Répondre » à une réponse : la mention préremplie (data-mention) est reconnue.
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-mention][data-mention-id]');
        if (!btn) return;
        setTimeout(() => {
            const form = document.querySelector(`[data-reply-form="${CSS.escape(btn.dataset.replyToggle || '')}"]`);
            const ta = form?.querySelector('textarea[data-mention-input]');
            if (ta) mentionsOf(ta).set(btn.dataset.mention, btn.dataset.mentionId);
        }, 0);
    });

    // Envoi (phase de capture : avant les scripts de la page) : « @Nom » choisi → jeton « @[Nom](user:id) ».
    document.addEventListener('submit', (e) => {
        e.target.querySelectorAll?.('textarea[data-mention-input]').forEach((ta) => {
            const map = store.get(ta);
            if (!map?.size) return;
            const plain = ta.value;
            let value = plain;
            [...map.entries()].sort((a, b) => b[0].length - a[0].length).forEach(([name, id]) => {
                const re = new RegExp(`(^|[^\\p{L}\\p{N}_\\[])@${escapeRe(name)}(?![\\p{L}\\p{N}_\\]])`, 'gu');
                value = value.replace(re, (_, lead) => `${lead}@[${name}](user:${id})`);
            });
            if (value === plain) return;
            ta.value = value;
            // Le texte lisible revient dès que la page a lu le champ (envoi dynamique ou classique).
            setTimeout(() => { if (ta.value === value) ta.value = plain; }, 0);
        });
    }, true);
})();
</script>
@endpush
@endonce

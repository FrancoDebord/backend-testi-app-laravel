@extends('layouts.app')
@section('title', $passage . ' (' . $translation . ')')
@use('App\Support\BibleReference')
@php
    $fullBleed = true;
    // Liens partageables /bible/{traduction}/{livre}/{chapitre}/{versets} : docs/fonctionnalites/liens-bible.md
    $chapterUrl = fn ($c) => url(BibleReference::passagePath($translation, $currentBook->number, $c));
@endphp

@if($meta)
@push('meta')
    <x-share-meta :meta="$meta" />
@endpush
@endif

@section('content')
<div class="flex min-h-[calc(100vh-4rem)] min-w-0">

    {{-- ── Livres et chapitres ───────────────────────────────────────── --}}
    <div id="bible-backdrop" class="fixed inset-0 top-16 z-20 bg-slate-900/40 md:hidden" hidden></div>
    <aside id="bible-sidebar"
           class="fixed top-16 bottom-0 left-0 z-30 hidden w-72 shrink-0 overflow-y-auto border-r border-slate-200 bg-white md:sticky md:block md:h-[calc(100vh-4rem)]"
           aria-label="Livres de la Bible">
        <div class="border-b border-slate-100 p-4">
            <label for="translation-select" class="form-label">Traduction</label>
            <select id="translation-select" class="form-input">
                @foreach($translations as $t)
                <option value="{{ $t }}" @selected($t === $translation)>{{ $t }}</option>
                @endforeach
            </select>
            {{-- Aller à un livre (autocomplétion) ─ docs/fonctionnalites/recherche.md --}}
            <form method="GET" action="{{ route('bible.reader') }}" role="search" class="mt-3">
                <label for="bible-book-q" class="sr-only">Aller à un livre de la Bible</label>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
                    <input id="bible-book-q" type="search" maxlength="60" class="form-input pl-9" placeholder="Aller à un livre…"
                           data-search-autocomplete="bible" data-search-submit="first-result" data-search-recent-key="bible"
                           data-search-translation="{{ $translation }}">
                </div>
            </form>
        </div>

        <div id="book-list" class="p-2">
            @php $currentTestament = null; @endphp
            @foreach($books as $book)
                @if($book->testament !== $currentTestament)
                    @php $currentTestament = $book->testament; @endphp
                    <p class="section-title px-3 pt-3 pb-1">{{ $book->testament === 'OT' ? 'Ancien Testament' : 'Nouveau Testament' }}</p>
                @endif
                <a href="#"
                   class="book-item {{ $currentBook?->number === $book->number ? 'bg-primary-50 font-semibold text-primary-700' : 'text-slate-700 hover:bg-slate-50' }} block rounded-lg px-3 py-1.5 text-sm"
                   data-book="{{ $book->number }}" data-chapters="{{ $book->chapters_count }}" data-name="{{ $book->name }}"
                   data-slug="{{ BibleReference::slug($book->number, $translation) }}">
                    {{ $book->name }}
                </a>
            @endforeach
        </div>

        <div id="chapter-panel" class="p-3" hidden>
            <div class="mb-3 flex items-center justify-between gap-2">
                <span id="chapter-book-name" class="truncate text-sm font-semibold text-slate-900"></span>
                <button type="button" id="back-to-books" class="btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i>Livres</button>
            </div>
            <div id="chapter-grid" class="grid grid-cols-6 gap-1"></div>
        </div>
    </aside>

    {{-- ── Lecture ───────────────────────────────────────────────────── --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" id="bible-sidebar-toggle" class="btn-secondary btn-sm md:hidden" aria-controls="bible-sidebar" aria-expanded="false">
                    <i class="fa-solid fa-list"></i>Livres
                </button>
                <h1 class="truncate text-lg font-semibold text-primary-600">
                    @if($selected)
                        {{ $passage }}
                    @else
                        {{ $currentBook?->name ?? '—' }}
                        @if($currentBook)<span class="font-normal text-slate-500">chapitre {{ $chapter }}</span>@endif
                    @endif
                </h1>
                <span class="badge-neutral">{{ $translation }}</span>
            </div>

            @if($currentBook)
            <div class="flex items-center gap-2">
                @if($shareUrl && $verses->isNotEmpty())
                {{-- Partage la sélection, sinon le chapitre. --}}
                <button type="button" class="btn-secondary btn-sm" data-bible-share>
                    <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>Partager
                </button>
                @endif
                @if($prevChapter)
                <a href="{{ $chapterUrl($prevChapter) }}" class="action-btn-view" aria-label="Chapitre précédent"><i class="fa-solid fa-chevron-left"></i></a>
                @endif
                <span class="text-sm text-slate-500">{{ $chapter }} / {{ $currentBook->chapters_count }}</span>
                @if($nextChapter)
                <a href="{{ $chapterUrl($nextChapter) }}" class="action-btn-view" aria-label="Chapitre suivant"><i class="fa-solid fa-chevron-right"></i></a>
                @endif
            </div>
            @endif
        </div>

        <div class="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6">
            @if($verses->isEmpty())
                <div class="card px-6 py-12 text-center">
                    <p class="text-sm font-semibold text-slate-900">Aucun verset disponible</p>
                    <p class="mt-1 text-sm text-slate-500">La traduction {{ $translation }} n’a pas encore été importée.</p>
                    @if(Auth::user()?->isAdmin())
                    <code class="mt-3 inline-block rounded bg-slate-100 px-2 py-1 text-xs break-all text-slate-700">php artisan bible:import {{ $translation }} storage/bible/…</code>
                    @endif
                </div>
            @else
                <p class="mb-3 text-sm text-slate-500"><i class="fa-regular fa-hand-pointer mr-1" aria-hidden="true"></i>Touchez un ou plusieurs versets pour les partager.</p>
                <div class="card divide-y divide-slate-100 px-5 sm:px-6">
                    @foreach($verses as $verse)
                    @php $isSelected = in_array($verse->verse, $selected, true); @endphp
                    {{-- Toucher le verset (ou son numéro, au clavier) l'ajoute à la sélection à partager. --}}
                    <div id="v{{ $verse->verse }}" data-verse="{{ $verse->verse }}"
                         class="flex cursor-pointer scroll-mt-24 gap-3 py-2.5 leading-relaxed {{ $isSelected ? '-mx-5 bg-sun-50 px-5 sm:-mx-6 sm:px-6' : '' }}">
                        <button type="button" data-verse-toggle aria-pressed="{{ $isSelected ? 'true' : 'false' }}" aria-label="Sélectionner le verset {{ $verse->verse }}"
                                class="w-6 shrink-0 self-start pt-1 text-right text-xs font-semibold {{ $isSelected ? 'text-primary-700' : 'text-slate-400' }} hover:text-primary-700">{{ $verse->verse }}</button>
                        <span data-verse-text class="min-w-0 text-[17px] break-words text-slate-800">{{ $verse->text }}</span>
                    </div>
                    @endforeach
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                    @if($prevChapter)
                    <a href="{{ $chapterUrl($prevChapter) }}" class="btn-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i>Chapitre {{ $prevChapter }}</a>
                    @else
                    <span></span>
                    @endif
                    @if($nextChapter)
                    <a href="{{ $chapterUrl($nextChapter) }}" class="btn-secondary btn-sm">Chapitre {{ $nextChapter }}<i class="fa-solid fa-chevron-right"></i></a>
                    @endif
                </div>

                {{-- Place pour la barre de sélection, qui ne doit pas cacher les derniers versets. --}}
                <div id="verse-actions-spacer" class="h-28" @if(!$selected) hidden @endif></div>
            @endif
        </div>
    </div>
</div>

@if($verses->isNotEmpty())
{{-- Versets sélectionnés : partager, copier le texte ou le lien (docs/fonctionnalites/liens-bible.md) --}}
<div id="verse-actions" class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white px-4 py-3 shadow-lg" @if(!$selected) hidden @endif
     role="region" aria-label="Versets sélectionnés">
    <div class="mx-auto flex max-w-3xl flex-wrap items-center gap-2">
        <p class="min-w-0 flex-1 basis-40 truncate text-sm font-semibold text-primary-700" data-selection-label aria-live="polite">{{ $selected ? $passage : '' }}</p>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn-primary btn-sm" data-selection-share><i class="fa-solid fa-share-nodes" aria-hidden="true"></i>Partager</button>
            <button type="button" class="btn-secondary btn-sm" data-selection-copy="text"><i class="fa-regular fa-copy" aria-hidden="true"></i>Copier le texte</button>
            <button type="button" class="btn-secondary btn-sm" data-selection-copy="link"><i class="fa-solid fa-link" aria-hidden="true"></i>Copier le lien</button>
            <button type="button" class="btn-ghost btn-sm" data-selection-clear aria-label="Effacer la sélection"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const BIBLE_URL      = @json(url('/bible'));
    const translation    = @json($translation);
    const currentBookNum = {{ (int) ($currentBook?->number ?? 1) }};
    const currentChapter = {{ (int) $chapter }};
    // Lien du chapitre (adresse publique) ; la sélection y ajoute « /16-18,20 ».
    const CHAPTER_URL    = @json($currentBook ? BibleReference::passageUrl($translation, $currentBook->number, $chapter) : null);
    const CHAPTER_PATH   = @json($currentBook ? BibleReference::passagePath($translation, $currentBook->number, $chapter) : null);
    const BOOK_NAME      = @json($currentBook?->name);
    const MAX_RANGES     = {{ BibleReference::MAX_RANGES }};

    const sidebar  = document.getElementById('bible-sidebar');
    const backdrop = document.getElementById('bible-backdrop');
    const toggle   = document.getElementById('bible-sidebar-toggle');

    function setSidebar(open) {
        sidebar.classList.toggle('hidden', !open);
        backdrop.hidden = !open;
        toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    toggle?.addEventListener('click', function () { setSidebar(sidebar.classList.contains('hidden')); });
    backdrop.addEventListener('click', function () { setSidebar(false); });

    // ── Sélection de versets à partager ─ docs/fonctionnalites/liens-bible.md ──
    const SELECTED_ROW = ['-mx-5', 'bg-sun-50', 'px-5', 'sm:-mx-6', 'sm:px-6'];
    const rows     = Array.from(document.querySelectorAll('[data-verse]'));
    const selected = new Set(rows.filter(r => r.classList.contains('bg-sun-50')).map(r => Number(r.dataset.verse)));
    const bar      = document.getElementById('verse-actions');
    const spacer   = document.getElementById('verse-actions-spacer');

    // [16, 17, 18, 20] → [[16, 18], [20, 20]]
    function ranges() {
        const out = [];
        Array.from(selected).sort((a, b) => a - b).forEach(function (n) {
            const last = out[out.length - 1];
            if (last && n === last[1] + 1) last[1] = n; else out.push([n, n]);
        });
        return out;
    }
    const formatVerses = rs => rs.map(r => r[0] === r[1] ? String(r[0]) : r[0] + '-' + r[1]).join(',');
    const label = rs => BOOK_NAME + ' ' + currentChapter + (rs.length ? ':' + formatVerses(rs).replaceAll(',', ', ') : '');
    // Au-delà de MAX_RANGES plages, le lien serait refusé : lien du chapitre.
    const link = rs => rs.length && rs.length <= MAX_RANGES ? CHAPTER_URL + '/' + formatVerses(rs) : CHAPTER_URL;

    // Texte cité : numéros des versets dès qu'il y en a plusieurs, « … » entre deux passages éloignés.
    function quote(rs) {
        const many = selected.size > 1;
        return rs.map(function (r) {
            const parts = [];
            for (let n = r[0]; n <= r[1]; n++) {
                const text = document.querySelector('#v' + n + ' [data-verse-text]').textContent.trim();
                parts.push(many ? n + ' ' + text : text);
            }
            return parts.join(' ');
        }).join(' … ');
    }
    const shareBody = rs => '« ' + quote(rs) + ' »\n— ' + label(rs) + ' (' + translation + ')';

    function render() {
        const rs = ranges();
        rows.forEach(function (row) {
            const on = selected.has(Number(row.dataset.verse));
            SELECTED_ROW.forEach(c => row.classList.toggle(c, on));
            const btn = row.querySelector('[data-verse-toggle]');
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            btn.classList.toggle('text-primary-700', on);
            btn.classList.toggle('text-slate-400', !on);
        });
        if (bar) {
            bar.hidden = spacer.hidden = rs.length === 0;
            bar.querySelector('[data-selection-label]').textContent = rs.length ? label(rs) : '';
        }
        // L'adresse suit la sélection : elle peut être copiée depuis le navigateur.
        if (CHAPTER_PATH) {
            const path = CHAPTER_PATH + (rs.length && rs.length <= MAX_RANGES ? '/' + formatVerses(rs) : '');
            if (window.location.pathname !== path) history.replaceState(null, '', path);
        }
    }

    rows.forEach(function (row) {
        row.addEventListener('click', function () {
            // Texte surligné à la souris pour le copier : pas de sélection du verset.
            if (String(window.getSelection?.() ?? '').length > 0) return;
            const n = Number(row.dataset.verse);
            if (selected.has(n)) selected.delete(n); else selected.add(n);
            render();
        });
    });

    async function copy(text, message) {
        try {
            await navigator.clipboard.writeText(text);
            window.flash?.(message);
        } catch (e) {
            window.flash?.('Copie impossible dans ce navigateur.', 'error');
        }
    }

    async function share() {
        const rs = ranges();
        const title = label(rs) + ' (' + translation + ')';
        const data = rs.length ? { title: title, text: shareBody(rs), url: link(rs) } : { title: title, url: CHAPTER_URL };
        if (navigator.share) {
            try { await navigator.share(data); } catch (e) { /* partage annulé */ }
            return;
        }
        copy(rs.length ? data.text + '\n' + data.url : data.url, 'Lien copié.');
    }

    document.querySelector('[data-bible-share]')?.addEventListener('click', share);
    bar?.querySelector('[data-selection-share]').addEventListener('click', share);
    bar?.querySelectorAll('[data-selection-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const rs = ranges();
            if (btn.dataset.selectionCopy === 'link') copy(link(rs), 'Lien copié.');
            else copy(shareBody(rs) + '\n' + link(rs), 'Versets copiés.');
        });
    });
    bar?.querySelector('[data-selection-clear]').addEventListener('click', function () { selected.clear(); render(); });

    // Même passage dans l'autre traduction (le numéro du livre est redirigé vers son nom).
    document.getElementById('translation-select').addEventListener('change', function () {
        window.location = [BIBLE_URL, encodeURIComponent(this.value), currentBookNum, currentChapter, ranges().length <= MAX_RANGES ? formatVerses(ranges()) : ''].filter(Boolean).join('/');
    });

    document.querySelector('[data-verse].bg-sun-50')?.scrollIntoView({ block: 'center' });

    const bookList  = document.getElementById('book-list');
    const panel     = document.getElementById('chapter-panel');
    const grid      = document.getElementById('chapter-grid');
    const panelName = document.getElementById('chapter-book-name');

    function showChapters(item) {
        const bookNum  = parseInt(item.dataset.book, 10);
        const chapters = parseInt(item.dataset.chapters, 10);
        panelName.textContent = item.dataset.name;
        grid.innerHTML = '';
        for (let i = 1; i <= chapters; i++) {
            const a = document.createElement('a');
            a.href = [BIBLE_URL, encodeURIComponent(translation), item.dataset.slug, i].join('/');
            a.textContent = i;
            a.className = (bookNum === currentBookNum && i === currentChapter)
                ? 'flex aspect-square items-center justify-center rounded-md bg-slate-900 text-xs font-semibold text-white'
                : 'flex aspect-square items-center justify-center rounded-md border border-slate-200 text-xs text-slate-700 hover:border-slate-300 hover:bg-slate-50';
            grid.appendChild(a);
        }
        bookList.hidden = true;
        panel.hidden = false;
    }

    document.querySelectorAll('.book-item').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            showChapters(el);
        });
    });

    document.getElementById('back-to-books').addEventListener('click', function () {
        panel.hidden = true;
        bookList.hidden = false;
    });

    // Au chargement : grille du livre en cours.
    const current = document.querySelector('.book-item[data-book="' + currentBookNum + '"]');
    if (current) showChapters(current);
})();
</script>
@endpush

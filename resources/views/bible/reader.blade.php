@extends('layouts.app')
@section('title', ($currentBook?->name ?? 'Bible') . ' ' . $chapter)
@php
    $fullBleed = true;
    $chapterUrl = fn ($c) => route('bible.reader', ['translation' => $translation, 'book' => $currentBook?->number, 'chapter' => $c]);
@endphp

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
                   data-book="{{ $book->number }}" data-chapters="{{ $book->chapters_count }}" data-name="{{ $book->name }}">
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
                    {{ $currentBook?->name ?? '—' }}
                    @if($currentBook)<span class="font-normal text-slate-500">chapitre {{ $chapter }}</span>@endif
                </h1>
                <span class="badge-neutral">{{ $translation }}</span>
            </div>

            @if($currentBook)
            <div class="flex items-center gap-2">
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
                <div class="card divide-y divide-slate-100 px-5 sm:px-6">
                    @foreach($verses as $verse)
                    <p class="flex gap-3 py-2.5 leading-relaxed">
                        <span class="w-6 shrink-0 pt-1 text-right text-xs font-semibold text-slate-400">{{ $verse->verse }}</span>
                        <span class="min-w-0 text-[17px] break-words text-slate-800">{{ $verse->text }}</span>
                    </p>
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
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const READER_URL     = @json(route('bible.reader'));
    const translation    = @json($translation);
    const currentBookNum = {{ (int) ($currentBook?->number ?? 1) }};
    const currentChapter = {{ (int) $chapter }};

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

    document.getElementById('translation-select').addEventListener('change', function () {
        const params = new URLSearchParams({ translation: this.value, book: currentBookNum, chapter: 1 });
        window.location = READER_URL + '?' + params.toString();
    });

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
            const params = new URLSearchParams({ translation: translation, book: bookNum, chapter: i });
            a.href = READER_URL + '?' + params.toString();
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

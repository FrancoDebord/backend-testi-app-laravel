@extends('layouts.app')

@section('title', ($currentBook?->name ?? 'Bible') . ' ' . $chapter . ' — TestiApp')

@push('styles')
<style>
    .bible-sidebar {
        width: 280px;
        min-width: 280px;
        height: calc(100vh - 70px);
        overflow-y: auto;
        position: sticky;
        top: 70px;
        border-right: 1px solid #e5e7eb;
        background: #fff;
    }
    .bible-main {
        flex: 1;
        min-width: 0;
        max-width: 760px;
        margin: 0 auto;
    }
    .book-item {
        padding: .4rem .75rem;
        border-radius: 8px;
        cursor: pointer;
        font-size: .875rem;
        color: #374151;
        text-decoration: none;
        display: block;
        transition: background .1s;
    }
    .book-item:hover { background: #f3f4f6; color: #374151; }
    .book-item.active { background: #eef2ff; color: #6366f1; font-weight: 600; }
    .testament-label {
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #9ca3af;
        padding: .75rem .75rem .25rem;
    }
    .chapter-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 4px; padding: .5rem .75rem; }
    .chap-btn {
        aspect-ratio: 1;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        font-size: .8rem;
        background: #fff;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        text-decoration: none;
        color: #374151;
        transition: background .1s;
    }
    .chap-btn:hover { background: #eef2ff; color: #6366f1; border-color: #6366f1; }
    .chap-btn.active { background: #6366f1; color: #fff; border-color: #6366f1; }

    .verse-line { display: flex; gap: .75rem; padding: .55rem 0; line-height: 1.8; border-bottom: 1px solid #f3f4f6; }
    .verse-line:last-child { border-bottom: none; }
    .verse-num { font-size: .7rem; font-weight: 700; color: #6366f1; min-width: 22px; padding-top: .35rem; }
    .verse-text { font-size: 1.05rem; color: #1f2937; }

    .bible-header { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 1rem 1.5rem; }
    .translation-badge { font-size: .75rem; background: #eef2ff; color: #6366f1; border-radius: 6px; padding: .25rem .6rem; font-weight: 600; }

    @media (max-width: 768px) {
        .bible-sidebar { display: none; }
        .bible-sidebar.show { display: block; position: fixed; top: 70px; left: 0; z-index: 1040; height: calc(100vh - 70px); box-shadow: 4px 0 16px rgba(0,0,0,.15); }
    }
</style>
@endpush

@section('content')
<div class="d-flex" style="min-height: calc(100vh - 70px);">

    {{-- ── Sidebar ─────────────────────────────────────────────────────── --}}
    <aside class="bible-sidebar" id="bibleSidebar">

        {{-- Sélecteur de traduction --}}
        <div class="p-3 border-bottom">
            <label class="form-label small fw-semibold text-muted mb-1">Traduction</label>
            <select class="form-select form-select-sm" id="translationSelect">
                @foreach($translations as $t)
                    <option value="{{ $t }}" {{ $t === $translation ? 'selected' : '' }}>{{ $t }}</option>
                @endforeach
            </select>
        </div>

        {{-- Liste des livres --}}
        <div id="bookList">
            @php $currentTestament = null; @endphp
            @foreach($books as $book)
                @if($book->testament !== $currentTestament)
                    @php $currentTestament = $book->testament; @endphp
                    <p class="testament-label">{{ $book->testament === 'OT' ? 'Ancien Testament' : 'Nouveau Testament' }}</p>
                @endif
                <a href="#"
                   class="book-item {{ $currentBook?->number === $book->number ? 'active' : '' }}"
                   data-book="{{ $book->number }}"
                   data-chapters="{{ $book->chapters_count }}"
                   data-name="{{ $book->name }}">
                    {{ $book->name }}
                </a>
            @endforeach
        </div>

        {{-- Grille de chapitres (masquée par défaut, affichée via JS) --}}
        <div id="chapterPanel" class="d-none border-top mt-1 pb-2">
            <div class="d-flex align-items-center justify-content-between p-2 ps-3">
                <span class="small fw-semibold" id="chapterBookName"></span>
                <button class="btn btn-sm btn-link text-muted p-0" id="backToBooks">← Livres</button>
            </div>
            <div class="chapter-grid" id="chapterGrid"></div>
        </div>
    </aside>

    {{-- ── Contenu principal ──────────────────────────────────────────── --}}
    <div class="flex-grow-1 d-flex flex-column">

        {{-- Header chapitre --}}
        <div class="bible-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-light btn-sm d-md-none" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <h5 class="mb-0 fw-bold">
                    {{ $currentBook?->name ?? '—' }}
                    @if($currentBook) <span class="text-muted fw-normal">chapitre {{ $chapter }}</span> @endif
                </h5>
                <span class="translation-badge">{{ $translation }}</span>
            </div>

            <div class="d-flex align-items-center gap-2">
                @if($prevChapter)
                <a href="{{ route('bible.reader', ['translation' => $translation, 'book' => $currentBook?->number, 'chapter' => $prevChapter]) }}"
                   class="btn btn-light btn-sm">
                    <i class="bi bi-chevron-left"></i>
                </a>
                @endif

                <span class="small text-muted">{{ $chapter }} / {{ $currentBook?->chapters_count }}</span>

                @if($nextChapter)
                <a href="{{ route('bible.reader', ['translation' => $translation, 'book' => $currentBook?->number, 'chapter' => $nextChapter]) }}"
                   class="btn btn-light btn-sm">
                    <i class="bi bi-chevron-right"></i>
                </a>
                @endif
            </div>
        </div>

        {{-- Versets --}}
        <div class="bible-main p-4">
            @if($verses->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="bi bi-book fs-1 d-block mb-3 opacity-25"></i>
                    <p>Aucun verset disponible.<br>Importez d'abord la traduction {{ $translation }}.</p>
                    <code class="small">php artisan bible:import {{ $translation }} storage/bible/...</code>
                </div>
            @else
                <div class="mb-4">
                    @foreach($verses as $verse)
                    <div class="verse-line">
                        <span class="verse-num">{{ $verse->verse }}</span>
                        <span class="verse-text">{{ $verse->text }}</span>
                    </div>
                    @endforeach
                </div>

                {{-- Navigation bas de page --}}
                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                    @if($prevChapter)
                    <a href="{{ route('bible.reader', ['translation' => $translation, 'book' => $currentBook?->number, 'chapter' => $prevChapter]) }}"
                       class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-chevron-left me-1"></i>Chapitre {{ $prevChapter }}
                    </a>
                    @else
                    <div></div>
                    @endif

                    @if($nextChapter)
                    <a href="{{ route('bible.reader', ['translation' => $translation, 'book' => $currentBook?->number, 'chapter' => $nextChapter]) }}"
                       class="btn btn-outline-primary btn-sm">
                        Chapitre {{ $nextChapter }}<i class="bi bi-chevron-right ms-1"></i>
                    </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const translation = '{{ $translation }}';
const currentBookNum = {{ $currentBook?->number ?? 1 }};
const currentChapter = {{ $chapter }};

// ── Sélecteur de traduction ──────────────────────────────────────────
document.getElementById('translationSelect').addEventListener('change', function () {
    window.location = `/bible?translation=${this.value}&book=${currentBookNum}&chapter=1`;
});

// ── Sidebar mobile ───────────────────────────────────────────────────
document.getElementById('sidebarToggle')?.addEventListener('click', function () {
    document.getElementById('bibleSidebar').classList.toggle('show');
});

// ── Navigation livres → chapitres ────────────────────────────────────
const bookList    = document.getElementById('bookList');
const chapterPanel = document.getElementById('chapterPanel');
const chapterGrid = document.getElementById('chapterGrid');
const chapterBookName = document.getElementById('chapterBookName');

document.querySelectorAll('.book-item').forEach(function (el) {
    el.addEventListener('click', function (e) {
        e.preventDefault();
        const bookNum   = this.dataset.book;
        const chapters  = parseInt(this.dataset.chapters);
        const bookName  = this.dataset.name;

        chapterBookName.textContent = bookName;
        chapterGrid.innerHTML = '';

        for (let i = 1; i <= chapters; i++) {
            const a = document.createElement('a');
            a.href = `/bible?translation=${translation}&book=${bookNum}&chapter=${i}`;
            a.className = 'chap-btn' + (parseInt(bookNum) === currentBookNum && i === currentChapter ? ' active' : '');
            a.textContent = i;
            chapterGrid.appendChild(a);
        }

        bookList.classList.add('d-none');
        chapterPanel.classList.remove('d-none');
    });
});

document.getElementById('backToBooks')?.addEventListener('click', function () {
    chapterPanel.classList.add('d-none');
    bookList.classList.remove('d-none');
});

// Au chargement : ouvrir directement la grille du livre actif
document.querySelector(`.book-item[data-book="{{ $currentBook?->number }}"]`)?.click();
</script>
@endpush

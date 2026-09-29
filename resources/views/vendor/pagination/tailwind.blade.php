@if ($paginator->hasPages())
<nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-slate-500">
        @if ($paginator->firstItem())
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} sur {{ $paginator->total() }}
        @else
            {{ $paginator->count() }} élément(s)
        @endif
    </p>

    <div class="flex items-center gap-1">
        @if ($paginator->onFirstPage())
            <span class="action-btn-view pointer-events-none opacity-50" aria-disabled="true" aria-label="Page précédente"><i class="fa-solid fa-chevron-left"></i></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="action-btn-view" aria-label="Page précédente"><i class="fa-solid fa-chevron-left"></i></a>
        @endif

        <span class="px-2 text-sm text-slate-600 sm:hidden">Page {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

        <span class="hidden items-center gap-1 sm:flex">
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-1 text-sm text-slate-400">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg bg-primary-600 px-2 text-xs font-semibold text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="action-btn-view" aria-label="Page {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        </span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="action-btn-view" aria-label="Page suivante"><i class="fa-solid fa-chevron-right"></i></a>
        @else
            <span class="action-btn-view pointer-events-none opacity-50" aria-disabled="true" aria-label="Page suivante"><i class="fa-solid fa-chevron-right"></i></span>
        @endif
    </div>
</nav>
@endif

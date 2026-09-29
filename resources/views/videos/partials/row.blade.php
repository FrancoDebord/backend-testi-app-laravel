{{--
    Ligne de la « liste compacte » (docs/fonctionnalites/affichage-et-lecture.md) :
    @include('videos.partials.row', ['testimony' => $t, 'url' => …])
    Miniature, titre, auteur et informations sur une ligne ; le chevron déplie les détails (data-row-toggle).
    Aucune vidéo n'est chargée pour la miniature : image de couverture ou pictogramme (économie de données).
--}}
@php
    $type      = $testimony->type->value;
    $url       = $url ?? route('videos.show', $testimony->id);
    $pill      = $testimony->typePill();
    $duration  = $testimony->durationLabel();
    $status    = $testimony->status->value !== 'approved' ? $testimony->status : null;
    $category  = $testimony->relationLoaded('category') ? $testimony->category : null;
    $detailsId = 'row-details-' . $testimony->id;
    [$actionIcon, $actionLabel] = match ($type) {
        'audio' => ['fa-headphones', 'Écouter'],
        'text'  => ['fa-book-open', 'Lire'],
        default => ['fa-play', 'Regarder'],
    };
    $thumbIcon = ['text' => 'fa-file-lines', 'audio' => 'fa-microphone'][$type] ?? 'fa-play';
    $summary   = $testimony->body_text ? Str::limit($testimony->body_plain, 400) : null;
    // narrow : colonne étroite (« À regarder également ») → petite miniature à toutes les largeurs.
    $narrow    = ($narrow ?? false) === true;
@endphp
<article class="min-w-0" data-row>
    <div class="{{ $narrow ? 'gap-3' : 'gap-3 sm:gap-4' }} flex items-center p-3">
        <a href="{{ $url }}" tabindex="-1" aria-hidden="true"
           class="{{ $type === 'text' ? 'bg-slate-100 text-slate-400' : 'bg-slate-900 text-slate-600' }} {{ $narrow ? 'w-28' : 'w-28 sm:w-40' }} relative flex aspect-video shrink-0 items-center justify-center overflow-hidden rounded-lg">
            @if($testimony->cover_url)
                <img src="{{ $testimony->cover_url }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
            @else
                <i class="fa-solid {{ $thumbIcon }} text-lg" aria-hidden="true"></i>
            @endif
            @if($duration && $type !== 'text')
            <span class="absolute right-1 bottom-1 rounded bg-slate-900/85 px-1 py-px text-[10px] font-semibold text-white tabular-nums">{{ $duration }}</span>
            @endif
        </a>

        <div class="min-w-0 flex-1">
            <h3 class="line-clamp-2 text-sm leading-snug font-semibold text-primary-700">
                <a href="{{ $url }}" class="rounded-sm hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">{{ $testimony->title }}</a>
            </h3>
            <p class="mt-0.5 flex min-w-0 items-center gap-1.5 text-xs text-slate-600">
                <a href="{{ route('profiles.show', $testimony->user->id) }}" class="truncate hover:text-slate-900 hover:underline">{{ $testimony->user->display_name }}</a>
                @include('components.verified-badge', ['user' => $testimony->user])
            </p>
            <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                @if($testimony->isInJournal())
                <span class="badge-neutral">Carnet privé</span>
                @elseif($status)
                <span class="{{ $status->badgeClass() }}">{{ $status->label() }}</span>
                @elseif($pill)
                <span class="inline-flex items-center gap-1 font-medium text-slate-600"><i class="fa-solid {{ $pill[0] }} text-[10px] text-slate-400" aria-hidden="true"></i>{{ $pill[1] }}</span>
                @endif
                <span>{{ $testimony->viewsLabel() }}</span>
                <span aria-hidden="true">·</span>
                <time datetime="{{ $testimony->publishedAt()?->toIso8601String() }}">{{ $testimony->publishedAt()?->diffForHumans() }}</time>
            </p>
        </div>

        <button type="button" class="btn-ghost btn-sm shrink-0" data-row-toggle aria-expanded="false" aria-controls="{{ $detailsId }}"
                aria-label="Détails : {{ $testimony->title }}" title="Afficher les détails">
            <i class="fa-solid fa-chevron-down transition-transform" data-row-icon aria-hidden="true"></i>
        </button>
    </div>

    <div id="{{ $detailsId }}" class="{{ $narrow ? 'px-3' : 'px-3 sm:pl-[11.75rem]' }} pb-4 text-sm" hidden>
        @if($summary)
        <p class="line-clamp-4 break-words whitespace-pre-line text-slate-700">{{ $summary }}</p>
        @else
        <p class="text-slate-500">Aucune description.</p>
        @endif

        @if($testimony->bible_ref)
        <p class="mt-2 text-xs font-medium text-slate-500"><i class="fa-solid fa-book-bible mr-1 text-slate-400" aria-hidden="true"></i>{{ $testimony->bible_ref }}</p>
        @endif

        <p class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
            @if($category)<span><i class="fa-solid fa-tag mr-1 text-slate-400" aria-hidden="true"></i>{{ $category->name }}</span>@endif
            @if($duration)<span><i class="fa-regular fa-clock mr-1 text-slate-400" aria-hidden="true"></i>{{ $duration }}</span>@endif
            <span><i class="fa-solid fa-thumbs-up mr-1 text-slate-400" aria-hidden="true"></i>{{ number_format($testimony->like_count, 0, ',', ' ') }}<span class="sr-only"> J'aime</span></span>
            <span><i class="fa-solid fa-hands-praying mr-1 text-slate-400" aria-hidden="true"></i>{{ number_format($testimony->prayer_count, 0, ',', ' ') }}<span class="sr-only"> prières</span></span>
            <span><i class="fa-regular fa-comment mr-1 text-slate-400" aria-hidden="true"></i>{{ number_format($testimony->comment_count, 0, ',', ' ') }}<span class="sr-only"> commentaires</span></span>
        </p>

        <a href="{{ $url }}" class="btn-secondary btn-sm mt-3"><i class="fa-solid {{ $actionIcon }}" aria-hidden="true"></i>{{ $actionLabel }}</a>
    </div>
</article>

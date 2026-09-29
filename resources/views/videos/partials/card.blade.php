{{--
    Carte de témoignage (page Vidéos, accueil, exploration, profil, sauvegardes, mes témoignages) :
    @include('videos.partials.card', ['testimony' => $t, 'short' => false, 'large' => false, 'url' => …])
    url : page ouverte au clic (par défaut /videos/{id}) · short : format vertical · large : mise en avant.
    Témoignage non publié (Mes témoignages) : pastille de statut à la place du type.
    Toute la carte est cliquable (lien étiré sur le titre) ; l'auteur reste un lien distinct.
    Vidéo sans miniature : la première image est chargée à l'approche de l'écran (data-lazy-frame).
    Survol (souris) : courte prévisualisation muette (data-preview).
--}}
@php
    // Un @include voit les variables de la vue parente : seules de vraies valeurs booléennes comptent.
    $short    = ($short ?? false) === true;
    $large    = ($large ?? false) === true;
    $type     = $testimony->type->value;
    $url      = $url ?? route('videos.show', $testimony->id);
    $category = $testimony->relationLoaded('category') ? $testimony->category : null;
    $status   = $testimony->status->value !== 'approved' ? $testimony->status : null;
    $pill     = $testimony->typePill();
    $duration = $testimony->durationLabel();
    $thumbBg = match ($type) {
        'text'  => 'bg-slate-100',
        'audio' => 'bg-slate-800',
        default => 'bg-slate-900',
    };
    $preview = $type === 'video' && $testimony->media_url ? $testimony->media_url : null;
@endphp
<article class="group relative flex min-w-0 flex-col" @if($preview) data-preview="{{ $preview }}" @endif>
    <div class="{{ $thumbBg }} {{ $short ? 'aspect-[9/16]' : 'aspect-video' }} relative overflow-hidden rounded-xl" data-preview-frame>
        @if($testimony->cover_url)
            <img src="{{ $testimony->cover_url }}" alt="" loading="lazy" decoding="async"
                 class="h-full w-full object-cover transition-transform duration-300 motion-safe:group-hover:scale-[1.03]">
        @elseif($type === 'video' && $testimony->media_url)
            <div class="absolute inset-0 flex items-center justify-center text-3xl text-slate-600" aria-hidden="true"><i class="fa-solid fa-play"></i></div>
            <video data-lazy-frame data-src="{{ $testimony->media_url }}#t=1" preload="none" muted playsinline tabindex="-1" aria-hidden="true"
                   class="relative h-full w-full object-cover opacity-0 transition-opacity duration-300"></video>
        @elseif($type === 'text')
            <div class="flex h-full flex-col justify-center gap-2 px-4 pt-4 pb-9 sm:px-5">
                <i class="fa-solid fa-quote-left text-slate-300" aria-hidden="true"></i>
                <p class="line-clamp-4 text-sm leading-relaxed text-slate-700 {{ $short ? '' : 'sm:line-clamp-3' }}">{{ Str::limit($testimony->body_plain, 260) }}</p>
            </div>
        @else
            <div class="flex h-full items-center justify-center text-3xl text-slate-500" aria-hidden="true">
                <i class="fa-solid {{ $type === 'audio' ? 'fa-microphone' : 'fa-play' }}"></i>
            </div>
        @endif

        @if($testimony->isInJournal())
        <span class="badge-neutral absolute top-2 left-2">Carnet privé</span>
        @elseif($status)
        <span class="{{ $status->badgeClass() }} absolute top-2 left-2">{{ $status->label() }}</span>
        @elseif($pill)
        <span class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-md bg-white/90 px-1.5 py-0.5 text-[11px] font-semibold text-slate-800">
            <i class="fa-solid {{ $pill[0] }} text-[10px]" aria-hidden="true"></i>{{ $pill[1] }}
        </span>
        @endif
        @if($duration)
        <span class="absolute right-2 bottom-2 rounded-md bg-slate-900/85 px-1.5 py-0.5 text-[11px] font-semibold text-white tabular-nums">
            <span class="sr-only">Durée : </span>{{ $duration }}
        </span>
        @endif
    </div>

    <div class="mt-3 flex gap-3">
        @unless($short)
        <a href="{{ route('profiles.show', $testimony->user->id) }}" class="relative z-10 shrink-0 self-start rounded-full" tabindex="-1" aria-hidden="true">
            @include('components.avatar', ['user' => $testimony->user, 'size' => 'md'])
        </a>
        @endunless
        <div class="min-w-0 flex-1">
            <h3 class="{{ $large ? 'text-base sm:text-lg' : 'text-sm' }} line-clamp-2 leading-snug font-semibold text-primary-700">
                <a href="{{ $url }}" class="rounded-sm after:absolute after:inset-0 after:content-[''] group-hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">{{ $testimony->title }}</a>
            </h3>
            @if(!$short && $type !== 'text' && $testimony->body_text)
            <p class="{{ $large ? 'line-clamp-2 text-sm' : 'line-clamp-1 text-xs' }} mt-1 text-slate-500">{{ $testimony->body_plain }}</p>
            @endif
            @unless($short)
            {{-- Nom (et coche) prioritaire ; la catégorie garde au plus 40 % de la ligne pour rester lisible. --}}
            <p class="mt-1 flex min-w-0 items-center gap-2 text-xs text-slate-600">
                <span class="flex min-w-0 items-center gap-1">
                    <a href="{{ route('profiles.show', $testimony->user->id) }}" class="relative z-10 truncate hover:text-slate-900 hover:underline">{{ $testimony->user->display_name }}</a>
                    @include('components.verified-badge', ['user' => $testimony->user])
                </span>
                @if($category)
                <span class="shrink-0 text-slate-300" aria-hidden="true">·</span><span class="max-w-[40%] shrink-0 truncate text-slate-500">{{ $category->name }}</span>
                @endif
            </p>
            @endunless
            <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
                <span>{{ $testimony->viewsLabel() }}</span>
                @unless($short)
                <span aria-hidden="true">·</span>
                <time datetime="{{ $testimony->publishedAt()?->toIso8601String() }}">{{ $testimony->publishedAt()?->diffForHumans() }}</time>
                @if($testimony->comment_count)
                <span aria-hidden="true">·</span>
                <span><i class="fa-regular fa-comment mr-0.5 text-slate-400" aria-hidden="true"></i>{{ number_format($testimony->comment_count, 0, ',', ' ') }}<span class="sr-only"> commentaires</span></span>
                @endif
                @endunless
            </p>
        </div>
    </div>
</article>

{{--
    Carte de témoignage (maquette « Témoignages de Gloire ») : carte par défaut de toutes les listes
    en affichage « Grandes cartes » (accueil, Explorer, Vidéos, profil, sauvegardes, mes témoignages).
    @include('videos.partials.tile', ['testimony' => $t, 'url' => route('testimonies.show', $t->id)])
    Miniature (type en haut à droite, durée en bas à droite, statut en haut à gauche pour ses propres
    témoignages), catégorie posée sur le bord de l'image, titre, extrait, auteur · vues · date,
    puis J'aime / commentaires / partages. Toute la carte est cliquable (lien étiré sur le titre).
    Survol (souris) d'une vidéo : courte prévisualisation muette (data-preview, resources/js/videos.js).
--}}
@php
    $type     = $testimony->type->value;
    // Catégorie chargée, sinon retrouvée par son nom court (anciens témoignages sans category_id).
    $category = ($testimony->relationLoaded('category') ? $testimony->category : null) ?? \App\Models\Category::forSlug($testimony->category_slug);
    $status   = $testimony->status->value !== 'approved' ? $testimony->status : null;
    $duration = $testimony->durationLabel();
    $isYouTube = $testimony->isYouTube();
    $typeIcon = $isYouTube ? 'fa-brands fa-youtube' : 'fa-solid ' . (['video' => 'fa-video', 'audio' => 'fa-microphone', 'text' => 'fa-quote-left'][$type] ?? 'fa-play');
    $preview  = $type === 'video' && $testimony->media_url && !$isYouTube ? $testimony->media_url : null;
    $n = fn ($v) => $v >= 1000 ? rtrim(rtrim(number_format($v / 1000, 1, ',', ' '), '0'), ',') . 'k' : (string) (int) $v;
    $thumbBg = match (true) {
        $type === 'text'  => 'bg-sun-50',
        $type === 'audio' => 'bg-primary-50',
        default           => 'bg-slate-900',
    };
@endphp
<article class="card group relative flex min-w-0 flex-col transition-shadow hover:shadow-lg" @if($preview) data-preview="{{ $preview }}" @endif>
    <div class="relative">
        <div class="{{ $thumbBg }} relative aspect-video overflow-hidden rounded-t-xl" data-preview-frame>
            @if($testimony->cover_url)
                <img src="{{ $testimony->cover_url }}" alt="" loading="lazy" decoding="async"
                     class="h-full w-full object-cover transition-transform duration-300 motion-safe:group-hover:scale-[1.03]">
            @elseif($type === 'video' && $testimony->media_url)
                <div class="absolute inset-0 flex items-center justify-center text-3xl text-slate-600" aria-hidden="true"><i class="fa-solid fa-play"></i></div>
                <video data-lazy-frame data-src="{{ $testimony->media_url }}#t=1" preload="none" muted playsinline tabindex="-1" aria-hidden="true"
                       class="relative h-full w-full object-cover opacity-0 transition-opacity duration-300"></video>
            @elseif($type === 'audio')
                {{-- Audio : bouton de lecture et onde stylisée --}}
                <div class="flex h-full items-center gap-3 px-5" aria-hidden="true">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-600 text-white shadow-soft"><i class="fa-solid fa-play ml-0.5"></i></span>
                    <span class="flex h-10 flex-1 items-center gap-[3px] overflow-hidden">
                        @foreach([30, 60, 45, 80, 55, 95, 40, 70, 50, 85, 35, 65, 90, 45, 75, 30, 60, 50, 80, 40, 70, 55, 35, 65, 50, 75] as $h)
                        <span class="w-[3px] shrink-0 rounded-full bg-primary-400" style="height: {{ $h }}%"></span>
                        @endforeach
                    </span>
                </div>
            @elseif($type === 'text')
                <div class="flex h-full flex-col justify-center gap-2 px-5" aria-hidden="true">
                    <i class="fa-solid fa-quote-left text-xl text-sun-500"></i>
                    <p class="line-clamp-3 text-sm leading-relaxed font-bold text-primary-700">{{ Str::limit($testimony->body_plain, 150) }}</p>
                </div>
            @else
                <div class="flex h-full items-center justify-center text-3xl text-slate-500" aria-hidden="true"><i class="fa-solid fa-play"></i></div>
            @endif

            {{-- Statut (ses propres témoignages) ou carnet privé --}}
            @if($testimony->isInJournal())
            <span class="badge-neutral absolute top-2 left-2">Carnet privé</span>
            @elseif($status)
            <span class="{{ $status->badgeClass() }} absolute top-2 left-2">{{ $status->label() }}</span>
            @endif

            <span class="absolute top-2 right-2 flex h-7 w-7 items-center justify-center rounded-full bg-white/95 text-xs {{ $isYouTube ? 'text-error-500' : 'text-primary-600' }} shadow-soft" title="{{ $isYouTube ? 'YouTube' : $testimony->type->label() }}">
                <i class="{{ $typeIcon }}" aria-hidden="true"></i><span class="sr-only">{{ $isYouTube ? 'Vidéo YouTube' : $testimony->type->label() }}</span>
            </span>
            @if($duration)
            <span class="absolute right-2 bottom-2 rounded-md bg-slate-900/85 px-1.5 py-0.5 text-[11px] font-semibold text-white tabular-nums">
                <span class="sr-only">Durée : </span>{{ $duration }}
            </span>
            @endif
        </div>

        {{-- Catégorie posée sur le bord de l'image --}}
        @if($category)
        <span class="{{ $category->presentation()['badge'] }} absolute -bottom-3 left-3 z-[1] ring-2 ring-white">{{ $category->name }}</span>
        @endif
    </div>

    <div class="flex flex-1 flex-col px-4 {{ $category ? 'pt-5' : 'pt-3' }} pb-3">
        <h3 class="line-clamp-2 text-[15px] leading-snug font-bold text-primary-700">
            <a href="{{ $url }}" class="rounded-sm after:absolute after:inset-0 after:content-[''] group-hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">{{ $testimony->title }}</a>
        </h3>
        @if($testimony->body_text && $type !== 'text')
        <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-slate-500">{{ $testimony->body_plain }}</p>
        @elseif($testimony->body_text)
        <p class="mt-1 line-clamp-1 text-xs text-slate-500">{{ $testimony->readingMinutes() }} min de lecture</p>
        @endif

        <p class="mt-auto flex min-w-0 items-center gap-1.5 pt-3 text-[11px] text-slate-500">
            <a href="{{ route('profiles.show', $testimony->user->id) }}" class="relative z-10 flex min-w-0 shrink items-center gap-1.5 hover:text-primary-600" title="{{ $testimony->user->display_name }}">
                @include('components.avatar', ['user' => $testimony->user, 'size' => 'xs'])
                <span class="truncate font-medium text-slate-700">{{ $testimony->user->display_name }}</span>
            </a>
            @include('components.verified-badge', ['user' => $testimony->user])
            <span class="shrink-0" aria-hidden="true">·</span>
            <span class="shrink-0 whitespace-nowrap">{{ $n($testimony->views_count ?? 0) }} {{ ($testimony->views_count ?? 0) > 1 ? 'vues' : 'vue' }}</span>
            @if($testimony->publishedAt())
            <span class="shrink-0" aria-hidden="true">·</span>
            <time class="min-w-0 truncate" datetime="{{ $testimony->publishedAt()->toIso8601String() }}">{{ $testimony->publishedAt()->diffForHumans() }}</time>
            @endif
        </p>
    </div>

    <p class="flex items-center gap-5 px-4 pb-4 text-xs font-semibold text-slate-600">
        <span title="J'aime"><i class="fa-solid fa-heart mr-1.5 text-error-500" aria-hidden="true"></i>{{ $n($testimony->like_count ?? 0) }}<span class="sr-only"> j'aime</span></span>
        <span title="Commentaires"><i class="fa-regular fa-comment-dots mr-1.5 text-primary-600" aria-hidden="true"></i>{{ $n($testimony->comment_count ?? 0) }}<span class="sr-only"> commentaires</span></span>
        <span title="Partages"><i class="fa-solid fa-share mr-1.5 text-primary-600" aria-hidden="true"></i>{{ $n($testimony->share_count ?? 0) }}<span class="sr-only"> partages</span></span>
    </p>
</article>

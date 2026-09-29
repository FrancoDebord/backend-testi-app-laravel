{{--
    Carte encadrée de l'accueil (« Témoignages récents », maquette « Témoignages de Gloire ») :
    @include('videos.partials.tile', ['testimony' => $t, 'url' => route('testimonies.show', $t->id)])
    Miniature (type en haut à droite, durée en bas à droite), catégorie, titre, extrait, auteur,
    puis J'aime / commentaires / partages. Toute la carte est cliquable (lien étiré sur le titre).
--}}
@php
    $type     = $testimony->type->value;
    $category = $testimony->relationLoaded('category') ? $testimony->category : null;
    $duration = $testimony->durationLabel();
    $typeIcon = ['video' => 'fa-video', 'audio' => 'fa-microphone', 'text' => 'fa-quote-left'][$type] ?? 'fa-play';
    $n = fn ($v) => $v >= 1000 ? number_format($v / 1000, 1, ',', ' ') . ' k' : (string) (int) $v;
@endphp
<article class="card group relative flex min-w-0 flex-col overflow-hidden">
    <div class="relative aspect-video overflow-hidden {{ $type === 'text' ? 'bg-sun-50' : ($type === 'audio' ? 'bg-primary-50' : 'bg-slate-900') }}">
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
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-600 text-white"><i class="fa-solid fa-play ml-0.5"></i></span>
                <span class="flex h-10 flex-1 items-center gap-[3px] overflow-hidden">
                    @foreach([30, 60, 45, 80, 55, 95, 40, 70, 50, 85, 35, 65, 90, 45, 75, 30, 60, 50, 80, 40, 70, 55, 35, 65] as $h)
                    <span class="w-[3px] shrink-0 rounded-full bg-primary-400" style="height: {{ $h }}%"></span>
                    @endforeach
                </span>
            </div>
        @elseif($type === 'text')
            <div class="flex h-full flex-col justify-center gap-2 px-5" aria-hidden="true">
                <i class="fa-solid fa-quote-left text-xl text-sun-500"></i>
                <p class="line-clamp-3 text-sm leading-relaxed font-semibold text-primary-700">{{ Str::limit($testimony->body_plain, 160) }}</p>
            </div>
        @else
            <div class="flex h-full items-center justify-center text-3xl text-slate-500" aria-hidden="true"><i class="fa-solid fa-play"></i></div>
        @endif

        <span class="absolute top-2 right-2 flex h-7 w-7 items-center justify-center rounded-full bg-white/95 text-xs text-primary-600 shadow-soft" aria-hidden="true">
            <i class="fa-solid {{ $typeIcon }}"></i>
        </span>
        @if($duration)
        <span class="absolute right-2 bottom-2 rounded-md bg-slate-900/85 px-1.5 py-0.5 text-[11px] font-semibold text-white tabular-nums">
            <span class="sr-only">Durée : </span>{{ $duration }}
        </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-4">
        @if($category)
        <span class="{{ $category->presentation()['badge'] }} mb-2 self-start">{{ $category->name }}</span>
        @endif
        <h3 class="line-clamp-2 text-sm leading-snug font-bold text-primary-700">
            <a href="{{ $url }}" class="rounded-sm after:absolute after:inset-0 after:content-[''] group-hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">{{ $testimony->title }}</a>
        </h3>
        @if($testimony->body_text)
        <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $testimony->body_plain }}</p>
        @endif
        <div class="mt-auto pt-3 text-[11px] text-slate-500">
            <a href="{{ route('profiles.show', $testimony->user->id) }}" class="relative z-10 flex min-w-0 items-center gap-1.5 hover:text-primary-600">
                @include('components.avatar', ['user' => $testimony->user, 'size' => 'xs'])
                <span class="truncate font-medium text-slate-700">{{ $testimony->user->display_name }}</span>
                @include('components.verified-badge', ['user' => $testimony->user])
            </a>
            <p class="mt-1 truncate">
                {{ $testimony->viewsLabel() }} · <time datetime="{{ $testimony->publishedAt()?->toIso8601String() }}">{{ $testimony->publishedAt()?->diffForHumans() }}</time>
            </p>
        </div>
    </div>

    <p class="flex items-center gap-4 border-t border-slate-100 px-4 py-2.5 text-xs font-medium text-slate-600">
        <span title="J'aime"><i class="fa-solid fa-heart mr-1 text-error-500" aria-hidden="true"></i>{{ $n($testimony->like_count ?? 0) }}<span class="sr-only"> j'aime</span></span>
        <span title="Commentaires"><i class="fa-regular fa-comment mr-1 text-primary-600" aria-hidden="true"></i>{{ $n($testimony->comment_count ?? 0) }}<span class="sr-only"> commentaires</span></span>
        <span title="Partages"><i class="fa-solid fa-share mr-1 text-primary-600" aria-hidden="true"></i>{{ $n($testimony->share_count ?? 0) }}<span class="sr-only"> partages</span></span>
    </p>
</article>

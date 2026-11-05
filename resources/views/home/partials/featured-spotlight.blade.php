{{--
    À la une en gros plan (accueil) : le premier témoignage mis en avant en grand (image large, titre, extrait,
    auteur, bouton selon le type), les suivants en vignettes sous lui. Toute la carte du gros plan est cliquable
    (lien étiré sur le titre) ; survol d'une vidéo : prévisualisation muette (data-preview, resources/js/videos.js).
    @include('home.partials.featured-spotlight', ['featured' => $featured])
    Voir docs/fonctionnalites/accueil.md
--}}
@php
    $hero      = $featured->first();
    $others    = $featured->slice(1, 4);
    $type      = $hero->type->value;
    $category  = ($hero->relationLoaded('category') ? $hero->category : null) ?? \App\Models\Category::forSlug($hero->category_slug);
    $isYouTube = $hero->isYouTube();
    $preview   = $type === 'video' && !$isYouTube ? $hero->mp4PlaybackUrl() : null;
    $duration  = $hero->durationLabel();
    $url       = route('testimonies.show', $hero->id);
    [$ctaIcon, $ctaLabel] = match ($type) {
        'audio' => ['fa-headphones', __('discover.home.spotlight.listen')],
        'text'  => ['fa-book-open', __('discover.home.spotlight.read')],
        default => ['fa-play', __('discover.home.spotlight.watch')],
    };
    $typeIcons = ['video' => 'fa-video', 'audio' => 'fa-microphone', 'text' => 'fa-quote-left'];
@endphp
<section class="card p-5" aria-labelledby="home-featured">
    <div class="mb-4 flex items-center justify-between gap-2">
        <h2 id="home-featured" class="card-title flex items-center gap-2"><i class="fa-solid fa-star text-sun-400" aria-hidden="true"></i>{{ __('discover.home.featured') }}</h2>
        <a href="{{ route('explore') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline">{{ __('discover.home.explore_all') }}<i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></a>
    </div>

    {{-- Gros plan --}}
    <article class="group relative grid grid-cols-1 gap-5 md:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] md:items-center" @if($preview) data-preview="{{ $preview }}" @endif>
        <div class="{{ match ($type) { 'text' => 'bg-sun-50', 'audio' => 'bg-primary-50', default => 'bg-slate-900' } }} relative aspect-video overflow-hidden rounded-xl" data-preview-frame>
            @if($hero->cover_url)
                <img src="{{ $hero->cover_url }}" alt="" decoding="async" fetchpriority="high"
                     class="h-full w-full object-cover transition-transform duration-300 motion-safe:group-hover:scale-[1.02]">
            @elseif($preview)
                <video data-lazy-frame data-src="{{ $preview }}#t=1" preload="none" muted playsinline tabindex="-1" aria-hidden="true"
                       class="h-full w-full object-cover opacity-0 transition-opacity duration-300"></video>
            @elseif($type === 'text')
                <div class="flex h-full flex-col justify-center gap-3 px-6 sm:px-8" aria-hidden="true">
                    <i class="fa-solid fa-quote-left text-2xl text-sun-500"></i>
                    <p class="line-clamp-4 text-base leading-relaxed font-bold text-primary-700 sm:text-lg">{{ Str::limit($hero->body_plain, 220) }}</p>
                </div>
            @elseif($type === 'audio')
                <div class="flex h-full items-center gap-[3px] px-8 opacity-40" aria-hidden="true">
                    @foreach([30, 60, 45, 80, 55, 95, 40, 70, 50, 85, 35, 65, 90, 45, 75, 30, 60, 50, 80, 40, 70, 55, 35, 65, 50, 75, 45, 85, 60, 40] as $h)
                    <span class="w-1 flex-1 rounded-full bg-primary-400" style="height: {{ $h }}%"></span>
                    @endforeach
                </div>
            @endif

            @if($type !== 'text')
            <span class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-white/95 text-2xl text-primary-600 shadow-card transition-transform duration-200 motion-safe:group-hover:scale-105">
                    <i class="fa-solid {{ $type === 'audio' ? 'fa-headphones' : 'fa-play ml-1' }}"></i>
                </span>
            </span>
            @endif

            <span class="badge-yellow absolute top-3 left-3"><i class="fa-solid fa-star" aria-hidden="true"></i>{{ __('discover.home.spotlight.badge') }}</span>
            @if($duration)
            <span class="absolute right-3 bottom-3 rounded-md bg-slate-900/85 px-2 py-0.5 text-xs font-semibold text-white tabular-nums">
                <span class="sr-only">{{ __('videos.common.duration') }}</span>{{ $duration }}
            </span>
            @endif
        </div>

        <div class="flex min-w-0 flex-col">
            <p class="flex flex-wrap items-center gap-2">
                @if($category)<span class="{{ $category->presentation()['badge'] }}">{{ $category->displayName() }}</span>@endif
                @if($hero->isConversion())<span class="badge-yellow"><i class="{{ $hero->conversionIcon() }}" aria-hidden="true"></i>{{ $hero->conversionLabel('badge') }}</span>@endif
                <span class="text-xs text-slate-500"><i class="{{ $isYouTube ? 'fa-brands fa-youtube' : 'fa-solid ' . ($typeIcons[$type] ?? 'fa-play') }} mr-1" aria-hidden="true"></i>{{ $hero->type->label() }}</span>
            </p>

            <h3 class="mt-3 line-clamp-3 text-xl leading-tight font-extrabold text-primary-700 sm:text-2xl">
                <a href="{{ $url }}" class="rounded-sm after:absolute after:inset-0 after:content-[''] group-hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">{{ $hero->title }}</a>
            </h3>

            @if($hero->body_text && $type !== 'text')
            <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-slate-600">{{ $hero->body_plain }}</p>
            @endif

            <p class="mt-4 flex min-w-0 items-center gap-2 text-xs text-slate-500">
                @if($hero->showsAuthorOnWeb(auth()->user()))
                <a href="{{ route('profiles.show', $hero->user->id) }}" class="relative z-10 flex min-w-0 shrink items-center gap-2 hover:text-primary-600">
                    @include('components.avatar', ['user' => $hero->user, 'size' => 'sm'])
                    <span class="truncate font-semibold text-slate-700">{{ $hero->user->display_name }}</span>
                </a>
                @include('components.verified-badge', ['user' => $hero->user])
                @else
                <span class="flex min-w-0 shrink items-center gap-2">@include('components.avatar', ['user' => null, 'size' => 'sm'])<span class="truncate italic">{{ __('seo.anonymous') }}</span></span>
                @endif
                <span class="shrink-0" aria-hidden="true">·</span>
                <span class="shrink-0 whitespace-nowrap">{{ $hero->viewsLabel() }}</span>
            </p>

            {{-- Repère visuel : le lien est le titre (toute la carte est cliquable) --}}
            <span class="btn-primary mt-5 self-start" aria-hidden="true"><i class="fa-solid {{ $ctaIcon }}"></i>{{ $ctaLabel }}</span>
        </div>
    </article>

    {{-- Les autres témoignages à la une --}}
    @if($others->isNotEmpty())
    <ul class="mt-5 grid grid-cols-1 gap-3 border-t border-slate-100 pt-5 sm:grid-cols-2 2xl:grid-cols-4">
        @foreach($others as $item)
        <li class="min-w-0">
            <a href="{{ route('testimonies.show', $item->id) }}" class="flex min-w-0 items-center gap-3 rounded-lg p-1.5 hover:bg-slate-50">
                <span class="relative flex aspect-video w-24 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-primary-50 text-primary-600" aria-hidden="true">
                    @if($item->cover_url)<img src="{{ $item->cover_url }}" alt="" loading="lazy" class="h-full w-full object-cover">@else<i class="fa-solid {{ $typeIcons[$item->type->value] ?? 'fa-play' }}"></i>@endif
                </span>
                <span class="min-w-0">
                    <span class="line-clamp-2 text-sm leading-snug font-semibold text-primary-700">{{ $item->title }}</span>
                    <span class="block truncate text-[11px] text-slate-500">{{ $item->type->label() }}@if($item->durationLabel()) · {{ $item->durationLabel() }}@endif</span>
                </span>
            </a>
        </li>
        @endforeach
    </ul>
    @endif
</section>

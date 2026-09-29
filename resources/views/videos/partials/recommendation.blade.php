{{-- Élément compact (« À regarder également », « À la une ») : @include('videos.partials.recommendation', ['testimony' => $t, 'url' => …]) --}}
@php
    $type     = $testimony->type->value;
    $url      = $url ?? route('videos.show', $testimony->id);
    $duration = $type === 'text' ? $testimony->readingMinutes() . ' min' : ($testimony->duration_sec > 0 ? $testimony->formattedDuration() : null);
    $icon     = ['text' => 'fa-file-lines', 'audio' => 'fa-microphone', 'video' => 'fa-play'][$type] ?? 'fa-play';
@endphp
<article class="group relative flex gap-3">
    <div class="{{ $type === 'text' ? 'bg-slate-100 text-slate-400' : 'bg-slate-900 text-slate-600' }} relative flex aspect-video w-40 shrink-0 items-center justify-center overflow-hidden rounded-lg">
        @if($testimony->cover_url)
            <img src="{{ $testimony->cover_url }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
        @else
            <i class="fa-solid {{ $icon }} text-xl" aria-hidden="true"></i>
        @endif
        @if($duration)
        <span class="absolute right-1 bottom-1 rounded bg-slate-900/85 px-1 py-px text-[10px] font-semibold text-white tabular-nums"><span class="sr-only">Durée : </span>{{ $duration }}</span>
        @endif
    </div>
    <div class="min-w-0 flex-1">
        <h3 class="line-clamp-2 text-sm leading-snug font-semibold text-primary-700">
            <a href="{{ $url }}" class="rounded-sm after:absolute after:inset-0 after:content-[''] group-hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">{{ $testimony->title }}</a>
        </h3>
        <p class="mt-1 flex min-w-0 items-center gap-1 text-xs text-slate-600"><span class="truncate">{{ $testimony->user->display_name }}</span>@include('components.verified-badge', ['user' => $testimony->user])</p>
        <p class="text-xs text-slate-500">{{ $testimony->viewsLabel() }} · {{ $testimony->publishedAt()?->diffForHumans() }}</p>
    </div>
</article>

{{-- Direct en cours ou en préparation : @include('videos.partials.live-card', ['live' => $live]) --}}
@php
    $liveUrl = $live->isHost(Auth::user()) ? route('lives.studio', $live->id) : route('lives.show', $live->id);
@endphp
<article class="group relative flex min-w-0 flex-col">
    <div class="relative flex aspect-video items-center justify-center overflow-hidden rounded-xl bg-slate-900 text-4xl text-slate-600">
        <i class="fa-solid fa-tower-broadcast" aria-hidden="true"></i>
        <span class="{{ $live->status->badgeClass() }} absolute top-2 left-2">{{ $live->status->label() }}</span>
        @if($live->started_at)
        <span class="absolute right-2 bottom-2 rounded-md bg-slate-900/85 px-1.5 py-0.5 text-[11px] font-semibold text-white">
            depuis {{ $live->started_at->diffForHumans(null, true) }}
        </span>
        @endif
    </div>
    <div class="mt-3 flex gap-3">
        @include('components.avatar', ['user' => $live->host, 'size' => 'md'])
        <div class="min-w-0 flex-1">
            <h3 class="line-clamp-2 text-sm leading-snug font-semibold text-primary-700">
                <a href="{{ $liveUrl }}" class="rounded-sm after:absolute after:inset-0 after:content-[''] group-hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">{{ $live->title }}</a>
            </h3>
            <p class="mt-1 truncate text-xs text-slate-600">{{ $live->host->display_name }}</p>
            <p class="mt-0.5 text-xs text-slate-500">
                {{ number_format($live->comment_count, 0, ',', ' ') }} commentaire{{ $live->comment_count > 1 ? 's' : '' }}
            </p>
        </div>
    </div>
</article>

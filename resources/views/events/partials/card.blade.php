{{--
    Carte d'événement (liste /evenements) : @include('events.partials.card', ['event' => $event, 'showStatus' => false])
    Relations attendues : organizer, images, lives (directs à l'antenne seulement).
--}}
@php
    $cover   = $event->images->first()?->url;
    $onAir   = $event->relationLoaded('lives') && $event->lives->isNotEmpty();
    $place   = collect([$event->city, $event->country])->filter(fn ($v) => filled($v))->implode(', ');
@endphp
<article class="card group relative flex min-w-0 flex-col overflow-hidden">
    <div class="relative flex aspect-video items-center justify-center overflow-hidden bg-primary-50 text-4xl text-primary-600">
        @if($cover)
            <img src="{{ $cover }}" alt="" class="h-full w-full object-cover" loading="lazy">
        @else
            <i class="fa-solid {{ $event->type->icon() }}" aria-hidden="true"></i>
        @endif
        <div class="absolute top-2 left-2 flex flex-wrap gap-1.5">
            @if($onAir)<span class="badge-live">En direct</span>@endif
            @if($event->isCancelled())
                <span class="badge-rejected">Annulé</span>
            @elseif(!empty($showStatus) && $event->status !== \App\Enums\EventStatus::Published)
                <span class="{{ $event->status->badgeClass() }}">{{ $event->status->label() }}</span>
            @elseif($event->phase() === 'ongoing')
                <span class="badge-validated">En cours</span>
            @endif
        </div>
    </div>
    <div class="flex flex-1 flex-col gap-1.5 p-4">
        <p><span class="badge-blue"><i class="fa-solid {{ $event->type->icon() }}" aria-hidden="true"></i>{{ $event->type->label() }}</span></p>
        <h3 class="line-clamp-2 text-base leading-snug font-semibold text-primary-700">
            <a href="{{ route('events.show', $event->id) }}" class="rounded-sm after:absolute after:inset-0 after:content-[''] group-hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">{{ $event->title }}</a>
        </h3>
        <p class="text-sm text-slate-700">
            <i class="fa-regular fa-calendar mr-1 w-4 text-center text-slate-400" aria-hidden="true"></i>
            <time datetime="{{ $event->starts_at?->toIso8601String() }}">{{ $event->starts_at?->translatedFormat('D j M Y · H:i') }}</time>
        </p>
        @if($place)
        <p class="truncate text-sm text-slate-600"><i class="fa-solid fa-location-dot mr-1 w-4 text-center text-slate-400" aria-hidden="true"></i>{{ $place }}</p>
        @endif
        <div class="mt-auto flex flex-wrap items-center justify-between gap-x-3 gap-y-1 pt-2 text-xs text-slate-500">
            <span class="flex min-w-0 items-center gap-1.5">
                <span class="truncate">{{ $event->organizer?->display_name }}</span>
                @include('components.verified-badge', ['user' => $event->organizer])
            </span>
            <span class="whitespace-nowrap"><i class="fa-solid fa-user-check mr-1 text-slate-400" aria-hidden="true"></i>{{ number_format($event->going_count, 0, ',', ' ') }} participant{{ $event->going_count > 1 ? 's' : '' }}</span>
        </div>
    </div>
</article>

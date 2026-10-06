{{--
    Ligne compacte d'un événement (liste /evenements en « Liste compacte ») :
    @include('events.partials.row', ['event' => $event, 'showStatus' => false])
    Vignette date, titre, lieu / ville, organisateur, pastilles d'état. Relations : organizer, lives.
--}}
@php
    $onAir = $event->relationLoaded('lives') && $event->lives->isNotEmpty();
    $place = collect([$event->location, $event->city])->filter(fn ($v) => filled($v))->implode(', ');
    $day   = $event->starts_at;
@endphp
<li class="group relative flex min-w-0 items-center gap-3 px-3 py-3 hover:bg-slate-50 sm:px-4">
    <span class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-lg border border-slate-200 bg-white leading-none {{ $event->isDateTbd() || $event->isCancelled() ? 'opacity-60' : '' }}" aria-hidden="true">
        @if($event->isDateTbd())
            <i class="fa-regular fa-calendar text-base text-slate-400"></i><span class="mt-1 font-bold text-amber-600" style="font-size: 10px">?</span>
        @else
            <span class="text-base font-bold text-primary-700">{{ $day?->format('j') }}</span>
            <span class="mt-0.5 font-bold uppercase text-amber-600" style="font-size: 10px">{{ Str::of($day?->translatedFormat('M'))->replace('.', '') }}</span>
        @endif
    </span>
    <div class="min-w-0 flex-1">
        <p class="flex min-w-0 items-center gap-2">
            <a href="{{ route('events.show', $event->id) }}" class="truncate text-sm font-semibold text-primary-700 after:absolute after:inset-0 after:content-[''] group-hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">{{ $event->title }}</a>
        </p>
        <p class="mt-0.5 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-500">
            <span class="sr-only">{{ \App\Http\Controllers\Web\EventController::dateRange($event) }} —</span>
            @if($place)<span class="max-w-full truncate"><i class="fa-solid fa-location-dot mr-1 text-slate-400" aria-hidden="true"></i>{{ $place }}</span>@endif
            <span class="flex min-w-0 items-center gap-1"><span class="truncate">{{ $event->organizer?->display_name }}</span>@include('components.verified-badge', ['user' => $event->organizer])</span>
        </p>
    </div>
    <div class="flex shrink-0 flex-wrap justify-end gap-1 text-xs" style="max-width: 45%">
        @include('events.partials.status-badges', ['event' => $event, 'showStatus' => $showStatus ?? false, 'onAir' => $onAir])
    </div>
</li>

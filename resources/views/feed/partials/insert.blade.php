{{--
    Élément inséré dans un fil de témoignages (videos/partials/cards, variable $inserts) :
    événement (App\Models\Event) ou requête de prière, avec une étiquette qui dit pourquoi il est là.
    @include('feed.partials.insert', ['insert' => $model])
    En liste compacte (une seule carte à lignes séparées), la carte insérée est décollée des bords.
--}}
@php $compactList = \App\Support\FeedLayout::isCompact(); @endphp
@if($insert instanceof \App\Models\Event)
<div class="flex min-w-0 flex-col gap-1.5 {{ $compactList ? 'p-3 sm:max-w-md' : '' }}">
    <p class="flex items-center gap-1.5 truncate text-xs font-semibold text-secondary-700">
        <i class="fa-solid fa-calendar-day" aria-hidden="true"></i>
        <span class="truncate">Événement · {{ $insert->organizer?->display_name }}</span>
    </p>
    @include('events.partials.card', ['event' => $insert])
</div>
@elseif($insert instanceof \App\Models\PrayerRequest)
<div class="flex min-w-0 flex-col gap-1.5 {{ $compactList ? 'p-3 sm:max-w-md' : '' }}">
    <p class="flex items-center gap-1.5 truncate text-xs font-semibold text-secondary-700">
        <i class="fa-solid fa-hands-praying" aria-hidden="true"></i>
        <span class="truncate">Requête de prière{{ $insert->event ? ' · ' . $insert->event->title : '' }}</span>
    </p>
    @include('prayer.partials.feed-card', ['request' => $insert])
</div>
@endif

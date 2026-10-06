{{--
    Élément inséré dans un fil de témoignages (videos/partials/cards, variable $inserts) :
    événement (App\Models\Event), session de prière (App\Models\PrayerSession), requête de prière ou parole
    prophétique rendue publique (App\Models\Prophecy), avec une étiquette qui dit pourquoi il est là.
    @include('feed.partials.insert', ['insert' => $model])
    En liste compacte (une seule carte à lignes séparées), la carte insérée est décollée des bords.
--}}
@php $compactList = \App\Support\FeedLayout::isCompact(); @endphp
@if($insert instanceof \App\Models\Event)
<div class="flex min-w-0 flex-col gap-1.5 {{ $compactList ? 'p-3 sm:max-w-md' : '' }}">
    <p class="flex items-center gap-1.5 truncate text-xs font-semibold text-accent-700">
        <i class="fa-solid fa-calendar-day" aria-hidden="true"></i>
        <span class="truncate">Événement · {{ $insert->organizer?->display_name }}</span>
    </p>
    @include('events.partials.card', ['event' => $insert])
</div>
@elseif($insert instanceof \App\Models\PrayerRequest)
<div class="flex min-w-0 flex-col gap-1.5 {{ $compactList ? 'p-3 sm:max-w-md' : '' }}">
    <p class="flex items-center gap-1.5 truncate text-xs font-semibold text-accent-700">
        <i class="fa-solid fa-hands-praying" aria-hidden="true"></i>
        <span class="truncate">Requête de prière{{ $insert->event ? ' · ' . $insert->event->title : '' }}</span>
    </p>
    @include('prayer.partials.feed-card', ['request' => $insert])
</div>
@elseif($insert instanceof \App\Models\PrayerSession)
<div class="flex min-w-0 flex-col gap-1.5 {{ $compactList ? 'p-3 sm:max-w-md' : '' }}">
    <p class="flex items-center gap-1.5 truncate text-xs font-semibold text-accent-700">
        <i class="fa-solid fa-people-group" aria-hidden="true"></i>
        <span class="truncate">Session de prière · {{ $insert->host?->display_name }}</span>
    </p>
    @include('prayer.partials.session-card', ['session' => $insert])
</div>
@elseif($insert instanceof \App\Models\Prophecy && $insert->testimony)
{{-- Parole prophétique publiée avec son témoignage d'accomplissement (docs/fonctionnalites/paroles-prophetiques.md) --}}
<div class="flex min-w-0 flex-col gap-1.5 {{ $compactList ? 'p-3 sm:max-w-md' : '' }}">
    <p class="flex items-center gap-1.5 truncate text-xs font-semibold text-accent-700">
        <i class="fa-solid fa-bullhorn" aria-hidden="true"></i>
        <span class="truncate">Parole prophétique accomplie · {{ $insert->user?->display_name }}</span>
    </p>
    <a href="{{ route('testimonies.show', $insert->testimony_id) }}" class="card-insight flex h-full flex-col gap-2 p-4 hover:border-sun-400 sm:p-5">
        @if(filled($insert->title))<h3 class="font-semibold break-words text-slate-900">{{ $insert->title }}</h3>@endif
        <p class="text-xs text-slate-600">
            @if($insert->received_on)Reçue le {{ $insert->received_on->translatedFormat('j F Y') }}@endif{{ filled($insert->given_by) ? ', donnée par ' . $insert->given_by : '' }}
        </p>
        @if(filled($insert->body_text))
        <p class="text-sm break-words text-slate-800 italic">« {{ Str::limit($insert->body_text, 220) }} »</p>
        @elseif($insert->audio_url)
        <p class="text-sm text-slate-700"><i class="fa-solid fa-microphone mr-1" aria-hidden="true"></i>Parole enregistrée en audio</p>
        @endif
        <p class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-semibold">
            @if($insert->fulfilled_on)<span class="text-success-700"><i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i>Accomplie le {{ $insert->fulfilled_on->translatedFormat('j F Y') }}</span>@endif
            <span class="min-w-0 truncate text-primary-600">Lire le témoignage : {{ $insert->testimony->title }}</span>
        </p>
    </a>
</div>
@endif

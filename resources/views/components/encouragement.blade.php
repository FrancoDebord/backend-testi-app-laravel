{{--
    Message pour inciter à témoigner (config/encouragements.php) : un verset (texte + référence) ou une invitation,
    avec un bouton « Témoigner » vers la page de publication.

    <x-encouragement />                                  bandeau, message du jour
    <x-encouragement variant="feed" :index="3" />        dans une liste de témoignages (components.testimony-list)
    <x-encouragement verse="Psaume 66:16" :action="false" />   verset précis, sans bouton
    <x-encouragement message="Vous avez vécu… ?" />      texte libre (invitation)

    index : choix déterministe (pair : verset, impair : invitation ; la position fait tourner les messages).
    kind  : « verse » ou « call » pour imposer le genre. compact : ligne de la liste compacte (variant="feed").
--}}
@props([
    'variant' => 'banner',
    'index'   => null,
    'kind'    => null,
    'verse'   => null,
    'message' => null,
    'action'  => true,
    'compact' => false,
])
@php
    $verses = array_values(config('encouragements.verses', []));
    $calls  = array_values(config('encouragements.calls', []));
    // Sans index : message du jour (change chaque jour, identique pour tous).
    $i      = (int) ($index ?? now()->dayOfYear);

    $chosenVerse = null;
    $chosenCall  = $message;
    if (!$chosenCall) {
        if ($verse) {
            $chosenVerse = collect($verses)->firstWhere('ref', $verse);
        }
        if (!$chosenVerse) {
            $wantVerse = $kind ? $kind === 'verse' : $i % 2 === 0;
            if ($wantVerse && $verses) {
                $chosenVerse = $verses[intdiv($i, 2) % count($verses)];
            } elseif ($calls) {
                $chosenCall = $calls[intdiv($i, 2) % count($calls)];
            } elseif ($verses) {
                $chosenVerse = $verses[$i % count($verses)];
            }
        }
    }
    $showAction = $action && Route::has('publish');
    $actionUrl  = Auth::check() ? route('publish') : route('login');
@endphp
@if($chosenVerse || $chosenCall)
@if($variant === 'feed' && $compact)
{{-- Ligne de la liste compacte --}}
<aside {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-4 gap-y-2 bg-sun-50 px-4 py-3']) }} aria-label="Encouragement à témoigner">
    <i class="fa-solid fa-sun text-sun-500" aria-hidden="true"></i>
    <p class="min-w-0 flex-1 text-sm text-slate-900">
        @if($chosenVerse)
            <span class="italic">« {{ $chosenVerse['text'] }} »</span>
            <span class="font-semibold whitespace-nowrap text-primary-700">{{ $chosenVerse['ref'] }}</span>
        @else
            <span class="font-medium">{{ $chosenCall }}</span>
        @endif
    </p>
    @if($showAction)
    <a href="{{ $actionUrl }}" class="btn-secondary btn-sm shrink-0"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>Témoigner</a>
    @endif
</aside>
@else
{{-- Bandeau (pages) ou carte pleine largeur (grilles de témoignages) --}}
<aside {{ $attributes->merge(['class' => ($variant === 'feed' ? 'col-span-full ' : '') . 'card-insight flex flex-col gap-4 p-5 sm:flex-row sm:items-center']) }} aria-label="Encouragement à témoigner">
    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-sun-500 shadow-soft" aria-hidden="true">
        <i class="fa-solid {{ $chosenVerse ? 'fa-book-bible' : 'fa-sun' }}"></i>
    </span>
    <div class="min-w-0 flex-1">
        @if($chosenVerse)
            <blockquote class="text-[15px] leading-relaxed break-words text-slate-900 italic">« {{ $chosenVerse['text'] }} »</blockquote>
            <p class="mt-1 text-sm font-semibold text-primary-700">{{ $chosenVerse['ref'] }}</p>
        @else
            <p class="text-[15px] leading-relaxed font-semibold break-words text-primary-700">{{ $chosenCall }}</p>
        @endif
    </div>
    @if($showAction)
    <a href="{{ $actionUrl }}" class="btn-primary btn-sm shrink-0 self-start sm:self-center">
        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>Témoigner
    </a>
    @endif
</aside>
@endif
@endif

{{--
    « Pourquoi témoigner ? » : raisons bibliques de témoigner, en accordéon (config/encouragements.php, why_testify).
    Balises natives <details> / <summary> : fonctionne sans JavaScript, au clavier et avec un lecteur d'écran.
    Les raisons d'un même bloc s'excluent (attribut name) : en ouvrir une referme la précédente.

    <x-why-testify />                         carte dépliée, raisons repliées, bouton « Témoigner »
    <x-why-testify collapsible />             carte elle-même repliée (une ligne), pour les pages chargées
    <x-why-testify :open="0" />               première raison ouverte
    <x-why-testify :action="false" />         sans bouton (déjà sur Publier, ou bouton ailleurs)

    Voir docs/fonctionnalites/pourquoi-temoigner.md
--}}
@props([
    'collapsible' => false,
    'open'        => null,
    'action'      => true,
])
@php
    $reasons    = array_values(config('encouragements.why_testify', []));
    $uid        = 'why-testify-' . substr(md5(uniqid('', true)), 0, 8);
    $verseCount = array_sum(array_map(fn ($r) => count($r['verses'] ?? []), $reasons));
    $showAction = $action && Route::has('publish');
    $actionUrl  = Auth::check() ? route('publish') : route('login');
@endphp
@if($reasons)
<section {{ $attributes->merge(['class' => 'card @container overflow-hidden']) }} aria-labelledby="{{ $uid }}-title">
    @if($collapsible)<details class="why-testify group/why">@endif

    {{-- En-tête (résumé cliquable en mode replié) --}}
    @if($collapsible)<summary class="why-testify-head cursor-pointer list-none hover:bg-slate-50">@else<div class="why-testify-head">@endif
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-sun-100 text-sun-600" aria-hidden="true">
            <i class="fa-solid fa-book-bible"></i>
        </span>
        <span class="min-w-0 flex-1">
            <span id="{{ $uid }}-title" class="block text-base leading-tight font-bold text-primary-600 @md:text-lg">Pourquoi témoigner ?</span>
            <span class="mt-0.5 block text-sm text-slate-500">{{ count($reasons) }} raisons et {{ $verseCount }} versets de la Bible</span>
        </span>
        @if($collapsible)
        <span class="accordion-chevron group-open/why:rotate-180" aria-hidden="true"><i class="fa-solid fa-chevron-down"></i></span>
        @endif
    @if($collapsible)</summary>@else</div>@endif

    {{-- Raisons --}}
    <div class="accordion border-t border-slate-100">
        @foreach($reasons as $i => $reason)
        <details class="accordion-item group" name="{{ $uid }}" @if($open === $i) open @endif>
            <summary class="accordion-summary">
                <span class="accordion-icon" aria-hidden="true"><i class="fa-solid {{ $reason['icon'] ?? 'fa-book-open' }}"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm leading-snug font-semibold text-slate-900 @md:text-[15px]">{{ $reason['title'] }}</span>
                    @if(!empty($reason['summary']))
                    <span class="mt-0.5 block text-xs leading-snug text-slate-500 @md:text-sm">{{ $reason['summary'] }}</span>
                    @endif
                </span>
                <span class="hidden shrink-0 text-xs text-slate-400 @sm:inline">{{ count($reason['verses']) }} {{ count($reason['verses']) > 1 ? 'versets' : 'verset' }}</span>
                <span class="accordion-chevron group-open:rotate-180" aria-hidden="true"><i class="fa-solid fa-chevron-down"></i></span>
            </summary>
            <div class="accordion-panel">
                @foreach($reason['verses'] as $v)
                <figure class="why-testify-verse">
                    <blockquote class="text-[15px] leading-relaxed break-words text-slate-900 italic">« {{ $v['text'] }} »</blockquote>
                    <figcaption class="mt-1.5 text-sm font-semibold text-primary-700">{{ $v['ref'] }}</figcaption>
                </figure>
                @endforeach
            </div>
        </details>
        @endforeach
    </div>

    @if($showAction)
    <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 px-4 py-4 @sm:px-5 @lg:flex-row @lg:items-center @lg:justify-between">
        <p class="text-sm text-slate-600">Dieu a agi dans votre vie ? Votre histoire peut fortifier quelqu'un.</p>
        <a href="{{ $actionUrl }}" class="btn-primary btn-sm shrink-0 self-start @lg:self-auto">
            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>Témoigner
        </a>
    </div>
    @endif

    @if($collapsible)</details>@endif
</section>
@endif

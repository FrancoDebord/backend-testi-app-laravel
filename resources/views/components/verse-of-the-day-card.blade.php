{{--
    Carte « Verset du jour » (accueil) dans le design du jour ou celui choisi par le membre
    (docs/fonctionnalites/designs-du-verset.md). Classe : App\View\Components\VerseOfTheDayCard.
    Texte coupé au-delà de 8 lignes, avec « Lire en entier ». Le visiteur retrouve son choix
    (localStorage) grâce à resources/js/verse-style.js.
--}}
@php($tone = \App\Support\VerseDesigns::tone($designId))
<a href="{{ route('daily-verse.show') }}"
   class="verse-card verse-design verse-design--{{ $designId }} verse-font--{{ $style['font'] }} verse-align--{{ $style['text_align'] }} verse-tone--{{ $tone }} block p-5"
   style="--verse-size: {{ $sizes[$style['text_size']] }}px; --verse-lines: {{ config('verse_designs.card_max_lines', 8) }}"
   data-verse-design data-verse-card data-verse-sizes='@json($sizes)'
   aria-labelledby="home-verse-title" data-testid="home-daily-verse">
    <span class="verse-design__bg" aria-hidden="true"></span>
    <span class="verse-design__quote" aria-hidden="true">&rdquo;</span>
    <span class="verse-design__content verse-design__align block">
        <span id="home-verse-title" class="verse-design__label mb-3 flex items-center gap-2 text-xs font-bold tracking-wide uppercase"><i class="fa-solid fa-sun" aria-hidden="true"></i>Verset du jour</span>
        <span class="verse-design__line mb-3" aria-hidden="true"></span>
        <span class="verse-design__text block">{{ $text }}</span>
        <span class="verse-design__ref mt-3 block text-sm">{{ $verse->reference }}</span>
        <span class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-semibold">
            <span data-verse-more hidden>Lire en entier</span>
            <span>Méditer, prier, commenter <i class="fa-solid fa-arrow-right ml-1" aria-hidden="true"></i></span>
        </span>
    </span>
</a>
<script type="application/json" id="verse-style-config">@json($config)</script>

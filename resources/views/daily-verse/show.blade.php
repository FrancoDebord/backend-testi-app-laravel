@extends('layouts.app')
{{--
    Verset du jour en plein écran (docs/fonctionnalites/verset-du-jour.md) : le premier écran est
    entièrement occupé par le design du jour ou celui choisi (docs/fonctionnalites/designs-du-verset.md) :
    verset, référence, j'aime / commentaires / partage, vignette du sélecteur. Au défilement, sur des
    cartes blanches : prière, écoute, mode lecture, versions de la Bible, puis requêtes de prière,
    commentaires et témoignages liés.
--}}
@php
    $fullBleed   = true; // le design couvre toute la largeur ; le titre est dans le premier écran
    $header      = null;
    $breadcrumbs = [['label' => 'Accueil', 'url' => route('home')], ['label' => 'Verset du jour']];
    $dayLabel    = $date ? \Illuminate\Support\Carbon::parse($date)->translatedFormat('l j F Y') : $verse->themeLabel();
    $text        = $current['text'] ?? $verse->verse_text;
    $shown       = '« ' . trim($text) . ' »';
    $versionName = $current ? $current['name'] : 'Texte du jour';
    $reference   = $parsed['label'] ?? $verse->reference;
    $liked       = in_array('like', $myReactions, true);
    $prayed      = in_array('pray', $myReactions, true);
    $pageUrl     = route('daily-verse.show', $date ? ['date' => $date] : []);
    $tabUrl      = fn (string $t) => route('daily-verse.show', array_filter(['date' => $date, 'onglet' => $t, 'comparer' => $compare ? 1 : null])) . '#verse-tabs';
    $versionUrl  = fn (string $code) => route('daily-verse.show', array_filter(['date' => $date, 'version' => $code, 'onglet' => $tab !== 'prieres' ? $tab : null]));
    $publishUrl  = route('publish', ['verset' => $text, 'reference' => $reference]);
    $loginUrl    = route('login');
    // Design affiché : classes complètes (verse.css et verse-designs.css), mises à jour par verse-style.js.
    $tone        = \App\Support\VerseDesigns::tone($designId);
    $surface     = "verse-design verse-design--{$designId} verse-font--{$style['font']} verse-align--{$style['text_align']} verse-tone--{$tone}";
    $fullSizes   = \App\Services\VerseStyles::sizesFor($shown, 'fullscreen');
    $shareImage  = route('daily-verse.image', ['date' => $designDate]);
@endphp
@section('title', 'Verset du jour — ' . $reference)

@push('meta')
    {{-- Aperçu des réseaux sociaux : image générée dans le design du jour --}}
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($verse->verse_text, 200) }} — {{ $verse->reference }}">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="TestiApp · ARISE &amp; SHINE Krea">
    <meta property="og:title" content="Verset du jour — {{ $verse->reference }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($verse->verse_text, 200) }}">
    <meta property="og:url" content="{{ $pageUrl }}">
    <meta property="og:image" content="{{ $shareImage }}">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1080">
    <meta property="og:image:height" content="1350">
    <meta property="og:image:alt" content="« {{ \Illuminate\Support\Str::limit($verse->verse_text, 120) }} » {{ $verse->reference }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Verset du jour — {{ $verse->reference }}">
    <meta name="twitter:description" content="{{ \Illuminate\Support\Str::limit($verse->verse_text, 200) }}">
    <meta name="twitter:image" content="{{ $shareImage }}">
@endpush

@section('content')
{{-- ── Premier écran : le verset dans son design ──────────────────────── --}}
<section class="verse-hero {{ $surface }} flex flex-col"
         style="--verse-size: {{ $fullSizes[$style['text_size']] }}px"
         data-verse-design data-verse-fit="{{ config('verse_designs.text_size_range.fullscreen.min') }}" data-verse-sizes='@json($fullSizes)'
         aria-labelledby="verse-heading">
    <span class="verse-design__bg" aria-hidden="true"></span>
    <span class="verse-design__quote" aria-hidden="true">&rdquo;</span>
    <div class="verse-design__content flex flex-1 flex-col px-5 pt-4 pb-5 sm:px-10 lg:px-16">
        <div class="flex items-start justify-between gap-3">
            <h1 id="verse-heading" class="verse-design__label flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-sm font-semibold" style="color: var(--verse-accent)">
                <i class="fa-solid fa-sun" aria-hidden="true"></i>Verset du jour
                <span class="font-normal" style="color: var(--verse-ink)">· {{ $dayLabel }} · {{ $versionName }}</span>
            </h1>
            <a href="{{ route('home') }}" class="verse-action shrink-0" aria-label="Fermer et revenir à l'accueil"><i class="fa-solid fa-xmark" aria-hidden="true"></i><span class="hidden sm:inline" aria-hidden="true">Fermer</span></a>
        </div>

        <div class="flex flex-1 items-center py-8">
            <div class="verse-design__align mx-auto w-full max-w-3xl">
                <span class="verse-design__line mb-5" aria-hidden="true"></span>
                <blockquote class="verse-design__text" data-verse-text>{{ $shown }}</blockquote>
                <p class="verse-design__ref mt-5 text-lg" data-verse-ref>{{ $reference }}</p>
                @if($current === null && $preferred !== 'ORIGINAL')
                <p class="mt-3 text-sm">Ce passage n'est pas disponible dans la version choisie : texte du jour affiché.</p>
                @endif
            </div>
        </div>

        <div class="flex items-end justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                @auth
                <form method="POST" action="{{ route('daily-verse.react', $verse->id) }}" data-loading-label="…">
                    @csrf <input type="hidden" name="type" value="like">
                    <button type="submit" class="verse-action" aria-pressed="{{ $liked ? 'true' : 'false' }}">
                        <i class="{{ $liked ? 'fa-solid' : 'fa-regular' }} fa-heart" aria-hidden="true"></i>J'aime <span class="tabular-nums">{{ $verse->like_count }}</span>
                    </button>
                </form>
                @else
                <a href="{{ $loginUrl }}" class="verse-action"><i class="fa-regular fa-heart" aria-hidden="true"></i>J'aime <span class="tabular-nums">{{ $verse->like_count }}</span></a>
                @endauth
                <a href="{{ $tabUrl('commentaires') }}" class="verse-action"><i class="fa-regular fa-comment" aria-hidden="true"></i>Commentaires <span class="tabular-nums">{{ $commentCount }}</span></a>
                <button type="button" class="verse-action" data-verse-share data-share-url="{{ $pageUrl }}">
                    <i class="fa-solid fa-share-nodes" aria-hidden="true"></i><span data-share-label>Partager</span>
                </button>
            </div>
            {{-- Vignette du design affiché : ouvre le sélecteur --}}
            <button type="button" class="{{ $surface }} verse-thumb flex w-12 shrink-0 items-center justify-center shadow-sm ring-1 ring-slate-900/15 sm:w-14"
                    data-verse-design data-verse-style-open aria-haspopup="dialog" aria-controls="verse-style-panel" title="Changer le design">
                <span class="verse-design__bg" aria-hidden="true"></span>
                <span class="text-base font-semibold" style="font-family: var(--verse-font)" aria-hidden="true">Aa</span>
                <span class="sr-only">Changer le design et le texte</span>
            </button>
        </div>
        <a href="#verse-more" class="mx-auto mt-3 flex flex-col items-center text-xs font-semibold" style="color: var(--verse-ink)">
            Faire défiler<i class="fa-solid fa-chevron-down mt-1" aria-hidden="true"></i>
        </a>
    </div>
</section>

<div id="verse-more" class="mx-auto max-w-4xl scroll-mt-20 space-y-6 px-4 py-8 sm:px-6">

    {{-- ── Méditer : prière, écoute, mode lecture ────────────────────────── --}}
    <section class="card flex flex-wrap items-center gap-2 p-5" aria-label="Méditer le verset">
        @auth
        {{-- « Je prie » : temps de prière d'abord (resources/js/prayer-focus.js, docs/fonctionnalites/temps-de-priere.md) --}}
        <form method="POST" action="{{ route('daily-verse.react', $verse->id) }}" data-loading-label="…"
              data-prayer-focus="{{ route('daily-verse.pray.start', $verse->id) }}" data-prayer-focus-kind="daily_verse" data-prayed="{{ $prayed ? '1' : '0' }}"
              data-prayer-subject="{{ $reference }}" data-prayer-excerpt="{{ \Illuminate\Support\Str::limit($shown, 600) }}">
            @csrf <input type="hidden" name="type" value="pray">
            <button type="submit" class="{{ $prayed ? 'btn-primary' : 'btn-secondary' }} btn-sm" aria-pressed="{{ $prayed ? 'true' : 'false' }}">
                <i class="fa-solid fa-hands-praying" aria-hidden="true"></i>Je prie <span class="tabular-nums">{{ $verse->prayer_count }}</span>
            </button>
        </form>
        @else
        <a href="{{ $loginUrl }}" class="btn-secondary btn-sm"><i class="fa-solid fa-hands-praying" aria-hidden="true"></i>Je prie <span class="tabular-nums">{{ $verse->prayer_count }}</span></a>
        @endauth
        <button type="button" class="btn-secondary btn-sm" data-verse-speak hidden>
            <i class="fa-solid fa-volume-high" aria-hidden="true"></i><span data-speak-label>Écouter</span>
        </button>
        <button type="button" class="btn-secondary btn-sm" data-verse-reader-open>
            <i class="fa-solid fa-expand" aria-hidden="true"></i>Mode lecture
        </button>
        <button type="button" class="btn-ghost btn-sm" data-verse-style-open aria-controls="verse-style-panel">
            <i class="fa-solid fa-palette" aria-hidden="true"></i>Design et texte
        </button>
    </section>

    {{-- ── Versions de la Bible ──────────────────────────────────────────── --}}
    <section class="card p-5" aria-labelledby="versions-title">
        <h2 id="versions-title" class="card-title"><i class="fa-solid fa-book-bible mr-1" aria-hidden="true"></i>Lire dans une autre version</h2>
        @if($versions->isEmpty())
        <p class="mt-2 text-sm text-slate-500">Aucune version de la Bible n'est installée sur le serveur : seul le texte du jour est proposé.</p>
        @elseif(!$parsed)
        <p class="mt-2 text-sm text-slate-500">La référence « {{ $verse->reference }} » n'a pas été reconnue : seul le texte du jour est proposé.</p>
        @else
        <div class="mt-3 flex flex-wrap gap-2">
            <a href="{{ $versionUrl('ORIGINAL') }}" class="{{ $current === null && !$compare ? 'chip-active' : 'chip' }}" @if($current === null && !$compare) aria-current="true" @endif>Texte du jour</a>
            @foreach($versions as $v)
                @if($v['available'])
                <a href="{{ $versionUrl($v['code']) }}" class="{{ !$compare && ($current['code'] ?? null) === $v['code'] ? 'chip-active' : 'chip' }}" title="{{ $v['name'] }}">{{ $v['code'] }}</a>
                @else
                <span class="chip cursor-not-allowed opacity-50" title="{{ $v['name'] }} : passage absent de cette version">{{ $v['code'] }}</span>
                @endif
            @endforeach
            <a href="{{ route('daily-verse.show', array_filter(['date' => $date, 'comparer' => $compare ? null : 1])) }}" class="{{ $compare ? 'chip-active' : 'chip' }}"><i class="fa-solid fa-table-columns" aria-hidden="true"></i>Comparer</a>
        </div>
        @if($compare)
        <ol class="mt-4 space-y-3">
            <li class="rounded-lg bg-slate-50 p-4">
                <p class="text-xs font-semibold text-slate-500">Texte du jour</p>
                <p class="mt-1 text-[15px] leading-relaxed break-words text-slate-900">{{ $verse->verse_text }}</p>
            </li>
            @foreach($versions as $v)
            <li class="rounded-lg bg-slate-50 p-4">
                <p class="text-xs font-semibold text-slate-500">{{ $v['code'] }} · {{ $v['name'] }}</p>
                @if($v['available'])
                <p class="mt-1 text-[15px] leading-relaxed break-words text-slate-900">
                    @foreach($v['verses'] as $line)
                        @if(count($v['verses']) > 1)<sup class="mr-0.5 font-semibold text-primary-600">{{ $line['verse'] }}</sup>@endif{{ $line['text'] }}
                    @endforeach
                </p>
                @else
                <p class="mt-1 text-sm text-slate-500 italic">Ce passage n'est pas disponible dans cette version.</p>
                @endif
            </li>
            @endforeach
        </ol>
        @endif
        <p class="mt-3 text-xs text-slate-500">Votre choix est retenu pour vos prochaines visites. <a href="{{ route('bible.reader', $parsed ? ['book' => $parsed['book'], 'chapter' => $parsed['chapter'], 'translation' => $current['code'] ?? 'LSG'] : []) }}" class="text-primary-700 hover:underline">Lire tout le chapitre</a></p>
        @endif
    </section>

    {{-- ── Participation : prière, commentaires, témoignages ─────────────── --}}
    <section class="card" id="verse-tabs" aria-label="Participer autour du verset">
        <nav class="flex gap-5 overflow-x-auto border-b border-slate-200 px-5" aria-label="Onglets">
            <a href="{{ $tabUrl('prieres') }}" class="{{ $tab === 'prieres' ? 'tab-active' : 'tab' }}" @if($tab === 'prieres') aria-current="page" @endif><i class="fa-solid fa-hands-praying" aria-hidden="true"></i>Sujets de prière <span class="tabular-nums text-xs">({{ $prayers->count() }})</span></a>
            <a href="{{ $tabUrl('commentaires') }}" class="{{ $tab === 'commentaires' ? 'tab-active' : 'tab' }}" @if($tab === 'commentaires') aria-current="page" @endif><i class="fa-regular fa-comment" aria-hidden="true"></i>Commentaires <span class="tabular-nums text-xs">({{ $commentCount }})</span></a>
            <a href="{{ $tabUrl('temoignages') }}" class="{{ $tab === 'temoignages' ? 'tab-active' : 'tab' }}" @if($tab === 'temoignages') aria-current="page" @endif><i class="fa-solid fa-star" aria-hidden="true"></i>Témoignages <span class="tabular-nums text-xs">({{ $testimonies->count() }})</span></a>
        </nav>

        <div class="space-y-4 p-5">
        @if($tab === 'prieres')
            @auth
            <form method="POST" action="{{ route('daily-verse.prayers.store', $verse->id) }}" class="space-y-3 rounded-lg bg-slate-50 p-4" data-loading-label="Publication…">
                @csrf
                <label for="verse-prayer-body" class="form-label">Confier un sujet de prière sur ce verset</label>
                <textarea id="verse-prayer-body" name="body" rows="3" minlength="10" maxlength="2000" required class="form-input" placeholder="Seigneur, à la lumière de ce verset, je te confie…">{{ old('body') }}</textarea>
                @error('body')<p class="form-error">{{ $message }}</p>@enderror
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <label for="verse-prayer-visibility" class="sr-only">Qui peut la voir ?</label>
                    <select id="verse-prayer-visibility" name="visibility" class="form-input w-auto">
                        @foreach(\App\Models\PrayerRequest::visibilityLabels() as $value => $label)
                        <option value="{{ $value }}" @selected(old('visibility', 'public') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <label class="flex items-center gap-2 text-slate-700"><input type="checkbox" name="is_anonymous" value="1" class="rounded" @checked(old('is_anonymous'))>Anonyme</label>
                    <button type="submit" class="btn-primary btn-sm ml-auto"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>Publier</button>
                </div>
            </form>
            @else
            <p class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600"><a href="{{ $loginUrl }}" class="font-semibold text-primary-700 hover:underline">Connectez-vous</a> pour confier un sujet de prière sur ce verset.</p>
            @endauth
            @forelse($prayers as $prayerRequest)
                @include('prayer.partials.feed-card', ['request' => $prayerRequest, 'compact' => true])
            @empty
                @include('components.empty-state', ['title' => 'Aucun sujet de prière', 'text' => 'Soyez le premier à confier un sujet de prière inspiré par ce verset.'])
            @endforelse

        @elseif($tab === 'commentaires')
            @auth
            <form method="POST" action="{{ route('daily-verse.comments.store', $verse->id) }}" class="space-y-2" data-loading-label="Envoi…">
                @csrf
                <label for="verse-comment-body" class="form-label">Votre commentaire</label>
                <textarea id="verse-comment-body" name="body" rows="3" minlength="2" maxlength="1000" required class="form-input" placeholder="Ce que ce verset vous dit aujourd'hui…">{{ old('body') }}</textarea>
                @error('body')<p class="form-error">{{ $message }}</p>@enderror
                <div class="flex justify-end"><button type="submit" class="btn-primary btn-sm"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>Commenter</button></div>
            </form>
            @else
            <p class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600"><a href="{{ $loginUrl }}" class="font-semibold text-primary-700 hover:underline">Connectez-vous</a> pour commenter ce verset.</p>
            @endauth
            <ul class="divide-y divide-slate-100">
            {{-- « J'aime » de la personne lus en une requête (docs/fonctionnalites/jaime-commentaires.md) --}}
            @php app(\App\Services\CommentLikes::class)->preload('verse_comment', $comments, $viewer); @endphp
            @forelse($comments as $comment)
                <li class="flex gap-3 py-3" id="commentaire-{{ $comment->id }}">
                    @include('components.avatar', ['user' => $comment->user, 'size' => 'sm'])
                    <div class="min-w-0 flex-1">
                        <p class="text-sm"><span class="font-semibold text-slate-900">{{ $comment->user?->display_name ?? 'Membre' }}</span>
                            <span class="text-xs text-slate-500">· {{ $comment->created_at?->diffForHumans() }}</span>
                            @if($comment->isHidden())<span class="badge-yellow ml-1 text-xs">Retiré</span>@endif
                        </p>
                        <p class="mt-0.5 text-[15px] leading-relaxed break-words whitespace-pre-line text-slate-800">{{ \App\Services\Mentions::html($comment->body) }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            @include('components.comment-like', ['type' => 'verse_comment', 'comment' => $comment, 'url' => route('daily-verse.comments.like', [$verse->id, $comment->id]), 'class' => '-ml-3'])
                            @if($viewer && $comment->canBeDeletedBy($viewer))
                            <form method="POST" action="{{ route('daily-verse.comments.destroy', [$verse->id, $comment->id]) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-slate-500 hover:text-error-700"><i class="fa-solid fa-trash" aria-hidden="true"></i> Supprimer</button>
                            </form>
                            @endif
                            @if($viewer && $comment->user_id !== $viewer->id)
                            <details class="text-xs">
                                <summary class="cursor-pointer text-slate-500 hover:text-slate-800"><i class="fa-regular fa-flag" aria-hidden="true"></i> Signaler</summary>
                                <form method="POST" action="{{ route('daily-verse.comments.report', [$verse->id, $comment->id]) }}" class="mt-2 flex flex-wrap items-center gap-2">
                                    @csrf
                                    <label for="reason-{{ $comment->id }}" class="sr-only">Raison</label>
                                    <select id="reason-{{ $comment->id }}" name="reason" class="form-input w-auto text-xs">
                                        @foreach($reasons as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                    </select>
                                    <button type="submit" class="btn-secondary btn-sm">Envoyer</button>
                                </form>
                            </details>
                            @endif
                        </div>
                    </div>
                </li>
            @empty
                <li>@include('components.empty-state', ['title' => 'Aucun commentaire', 'text' => 'Partagez ce que ce verset vous inspire.'])</li>
            @endforelse
            </ul>

        @else
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 p-4">
                <p class="text-sm text-slate-600">Ce verset s'est accompli dans votre vie ? Racontez-le.</p>
                @auth
                <a href="{{ $publishUrl }}" class="btn-cta btn-sm"><i class="fa-solid fa-pen" aria-hidden="true"></i>Témoigner sur ce verset</a>
                @else
                <a href="{{ $loginUrl }}" class="btn-secondary btn-sm"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>Se connecter pour témoigner</a>
                @endauth
            </div>
            @if($testimonies->isEmpty())
                @include('components.empty-state', ['title' => 'Aucun témoignage sur ce verset', 'text' => 'Les témoignages publiés qui citent ' . $reference . ' apparaîtront ici.'])
            @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach($testimonies as $testimony)
                    @include('components.testimony-card', ['testimony' => $testimony])
                @endforeach
            </div>
            @endif
        @endif
        </div>
    </section>
</div>

{{-- ── Mode lecture : plein écran, grand texte, dans le design affiché ── --}}
<dialog id="verse-reader" class="{{ $surface }} m-0 h-full max-h-none w-full max-w-none p-0 backdrop:bg-slate-900/70"
        style="--verse-size: {{ $fullSizes[$style['text_size']] }}px" data-verse-design data-verse-sizes='@json($fullSizes)' aria-labelledby="verse-reader-title">
    <span class="verse-design__bg" aria-hidden="true"></span>
    <div class="verse-design__content flex h-full flex-col">
        <div class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6">
            <h2 id="verse-reader-title" class="verse-design__label text-sm font-semibold" style="color: var(--verse-accent)"><i class="fa-solid fa-sun mr-2" aria-hidden="true"></i>Verset du jour · {{ $versionName }}</h2>
            <button type="button" class="verse-action" data-verse-reader-close aria-label="Fermer"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="flex flex-1 items-center overflow-y-auto px-6 py-8 sm:px-12">
            <div class="verse-design__align mx-auto w-full max-w-3xl">
                <p class="verse-design__text">{{ $shown }}</p>
                <p class="verse-design__ref mt-8 text-xl">{{ $reference }}</p>
            </div>
        </div>
    </div>
</dialog>

{{-- ── Sélecteur : designs et texte ──────────────────────────────────── --}}
<dialog id="verse-style-panel" class="m-auto w-[calc(100%-2rem)] max-w-xl rounded-xl bg-white p-0 shadow-card backdrop:bg-slate-900/50 max-sm:mb-0 max-sm:w-full max-sm:max-w-none max-sm:rounded-b-none"
        aria-labelledby="verse-style-title">
    <div class="flex max-h-[85svh] flex-col">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 pt-4">
            <div class="min-w-0">
                <h2 id="verse-style-title" class="card-title">Personnaliser le verset</h2>
                <nav class="mt-2 flex gap-5" role="tablist" aria-label="Réglages">
                    <button type="button" class="tab-active" role="tab" aria-selected="true" data-verse-tab="designs"><i class="fa-solid fa-palette" aria-hidden="true"></i>Designs</button>
                    <button type="button" class="tab" role="tab" aria-selected="false" data-verse-tab="text"><i class="fa-solid fa-font" aria-hidden="true"></i>Texte</button>
                </nav>
            </div>
            <button type="button" class="btn-ghost btn-sm self-start" data-verse-style-close aria-label="Fermer"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>

        <div class="overflow-y-auto p-5">
            <div data-verse-pane="designs" role="tabpanel">
                <ul class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                    @foreach($designs as $d)
                    <li>
                        <button type="button" class="group block w-full rounded-lg text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                                data-pick-design="{{ $d['id'] }}" aria-pressed="{{ $d['id'] === $designId ? 'true' : 'false' }}">
                            <span class="verse-thumb verse-design verse-design--{{ $d['id'] }} verse-font--{{ $style['font'] }} verse-align--{{ $style['text_align'] }} block p-2 ring-primary-600 ring-offset-2 group-aria-pressed:ring-2"
                                  data-verse-thumb="{{ $d['id'] }}">
                                <span class="verse-design__bg" aria-hidden="true"></span>
                                <span class="verse-design__content verse-design__align block">
                                    <span class="verse-design__line mb-1.5" aria-hidden="true"></span>
                                    <span class="verse-design__text block" aria-hidden="true">{{ \Illuminate\Support\Str::limit(trim($text), 70) }}</span>
                                </span>
                                <span class="absolute top-1.5 right-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-primary-600 text-white" data-verse-check @if($d['id'] !== $designId) hidden @endif aria-hidden="true"><i class="fa-solid fa-check text-[10px]"></i></span>
                            </span>
                            <span class="mt-1.5 block truncate text-xs font-semibold text-slate-700">{{ $d['name'] }}</span>
                        </button>
                    </li>
                    @endforeach
                </ul>
                <label class="mt-4 flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" class="rounded" data-verse-always @checked($style['design_always'])>
                    Toujours utiliser ce design
                </label>
                <p class="mt-1 text-xs text-slate-500">Sinon, votre choix vaut pour aujourd'hui ; demain, le design du jour revient.</p>
            </div>

            <div data-verse-pane="text" role="tabpanel" hidden class="space-y-5">
                <fieldset>
                    <legend class="form-label">Police</legend>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach(config('verse_designs.fonts') as $f)
                        <button type="button" class="{{ $style['font'] === $f['id'] ? 'chip-active' : 'chip' }}" data-pick-font="{{ $f['id'] }}"
                                aria-pressed="{{ $style['font'] === $f['id'] ? 'true' : 'false' }}" style="font-family: '{{ $f['family'] }}'">{{ $f['label'] }}</button>
                        @endforeach
                    </div>
                </fieldset>
                <fieldset>
                    <legend class="form-label">Taille</legend>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach(config('verse_designs.text_sizes') as $s)
                        <button type="button" class="{{ $style['text_size'] === $s['id'] ? 'chip-active' : 'chip' }}" data-pick-size="{{ $s['id'] }}"
                                aria-pressed="{{ $style['text_size'] === $s['id'] ? 'true' : 'false' }}">{{ $s['label'] }}</button>
                        @endforeach
                    </div>
                </fieldset>
                <fieldset>
                    <legend class="form-label">Alignement</legend>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach(config('verse_designs.text_aligns') as $a)
                        <button type="button" class="{{ $style['text_align'] === $a['id'] ? 'chip-active' : 'chip' }}" data-pick-align="{{ $a['id'] }}"
                                aria-pressed="{{ $style['text_align'] === $a['id'] ? 'true' : 'false' }}">
                            <i class="fa-solid {{ $a['id'] === 'center' ? 'fa-align-center' : 'fa-align-left' }}" aria-hidden="true"></i>{{ $a['label'] }}
                        </button>
                        @endforeach
                    </div>
                </fieldset>
            </div>
            @guest
            <p class="mt-5 text-xs text-slate-500">Vos choix sont gardés sur cet appareil. <a href="{{ $loginUrl }}" class="font-semibold text-primary-700 hover:underline">Connectez-vous</a> pour les retrouver partout.</p>
            @endguest
        </div>
    </div>
</dialog>
<script type="application/json" id="verse-style-config">@json($config)</script>
@endsection

@push('scripts')
<script>
(function () {
    const text = @json($text);
    const reference = @json($reference);

    // Mode lecture
    const reader = document.getElementById('verse-reader');
    document.querySelector('[data-verse-reader-open]')?.addEventListener('click', () => reader.showModal());
    reader.querySelector('[data-verse-reader-close]')?.addEventListener('click', () => reader.close());

    // Partage : feuille du système, sinon copie dans le presse-papiers.
    const shareBtn = document.querySelector('[data-verse-share]');
    shareBtn?.addEventListener('click', async () => {
        const url = shareBtn.dataset.shareUrl;
        const body = '« ' + text + ' »\n— ' + reference;
        try {
            if (navigator.share) { await navigator.share({ title: 'Verset du jour', text: body, url }); return; }
            await navigator.clipboard.writeText(body + '\n' + url);
            shareBtn.querySelector('[data-share-label]').textContent = 'Copié !';
        } catch (e) { /* partage annulé */ }
    });

    // Lecture vocale (masquée si le navigateur ne sait pas lire à voix haute).
    const speakBtn = document.querySelector('[data-verse-speak]');
    if (speakBtn && 'speechSynthesis' in window) {
        speakBtn.hidden = false;
        const label = speakBtn.querySelector('[data-speak-label]');
        speakBtn.addEventListener('click', () => {
            if (speechSynthesis.speaking) { speechSynthesis.cancel(); label.textContent = 'Écouter'; return; }
            const u = new SpeechSynthesisUtterance(text + '. ' + reference);
            u.lang = /\b(the|and|of|lord)\b/i.test(text) ? 'en-US' : 'fr-FR';
            u.onend = u.onerror = () => { label.textContent = 'Écouter'; };
            label.textContent = 'Arrêter';
            speechSynthesis.speak(u);
        });
        window.addEventListener('pagehide', () => speechSynthesis.cancel());
    }
})();
</script>
@endpush

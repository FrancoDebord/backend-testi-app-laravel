@extends('layouts.app')
{{--
    Verset du jour en plein écran (docs/fonctionnalites/verset-du-jour.md) : présentation immersive,
    versions de la Bible (choix mémorisé en session, « Comparer »), réactions, partage, lecture vocale,
    mode lecture, puis requêtes de prière, commentaires et témoignages liés.
--}}
@php
    $header      = 'Verset du jour';
    $subheader   = $date ? \Illuminate\Support\Carbon::parse($date)->translatedFormat('l j F Y') : ($verse->themeLabel());
    $breadcrumbs = [['label' => 'Accueil', 'url' => route('home')], ['label' => 'Verset du jour']];
    $text        = $current['text'] ?? $verse->verse_text;
    $versionName = $current ? $current['name'] : 'Texte du jour';
    $reference   = $parsed['label'] ?? $verse->reference;
    $liked       = in_array('like', $myReactions, true);
    $prayed      = in_array('pray', $myReactions, true);
    $pageUrl     = route('daily-verse.show', $date ? ['date' => $date] : []);
    $tabUrl      = fn (string $t) => route('daily-verse.show', array_filter(['date' => $date, 'onglet' => $t, 'comparer' => $compare ? 1 : null])) . '#verse-tabs';
    $versionUrl  = fn (string $code) => route('daily-verse.show', array_filter(['date' => $date, 'version' => $code, 'onglet' => $tab !== 'prieres' ? $tab : null]));
    $publishUrl  = route('publish', ['verset' => $text, 'reference' => $reference]);
    $loginUrl    = route('login');
@endphp
@section('title', 'Verset du jour — ' . $reference)

@section('headerActions')
    <a href="{{ route('home') }}" class="btn-ghost btn-sm" aria-label="Fermer et revenir à l'accueil"><i class="fa-solid fa-xmark" aria-hidden="true"></i><span>Fermer</span></a>
@endsection

@section('content')
<div class="mx-auto max-w-4xl space-y-6">

    {{-- ── Le verset : présentation immersive ─────────────────────────── --}}
    <section class="overflow-hidden rounded-2xl text-white shadow-card"
             style="background: linear-gradient(140deg, #184797 0%, #103675 55%, #0a2149 100%);"
             aria-labelledby="verse-heading">
        <div class="relative px-5 py-8 sm:px-10 sm:py-12">
            <div class="pointer-events-none absolute inset-x-0 top-0 h-1" style="background: linear-gradient(90deg, #F18717, #FCC11D);" aria-hidden="true"></div>
            <p id="verse-heading" class="flex flex-wrap items-center gap-2 text-sm font-semibold" style="color:#FCC11D">
                <i class="fa-solid fa-sun" aria-hidden="true"></i>Verset du jour
                <span class="font-normal text-white/70">· {{ $versionName }}</span>
            </p>
            <blockquote class="mt-5 text-2xl leading-relaxed font-medium break-words sm:text-3xl" data-verse-text>« {{ $text }} »</blockquote>
            <p class="mt-5 text-lg font-bold" style="color:#FCC11D" data-verse-ref>{{ $reference }}</p>
            @if($current === null && $preferred !== 'ORIGINAL')
            <p class="mt-3 text-sm text-white/70">Ce passage n'est pas disponible dans la version choisie : texte du jour affiché.</p>
            @endif

            <div class="mt-8 flex flex-wrap items-center gap-2">
                @auth
                <form method="POST" action="{{ route('daily-verse.react', $verse->id) }}" data-loading-label="…">
                    @csrf <input type="hidden" name="type" value="like">
                    <button type="submit" class="btn-sm inline-flex min-h-9 items-center gap-1.5 rounded-lg px-3 py-1.5 text-[13px] font-semibold {{ $liked ? 'bg-white text-primary-700' : 'bg-white/10 text-white hover:bg-white/20' }}" aria-pressed="{{ $liked ? 'true' : 'false' }}">
                        <i class="{{ $liked ? 'fa-solid' : 'fa-regular' }} fa-heart" aria-hidden="true"></i>J'aime <span class="tabular-nums">{{ $verse->like_count }}</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('daily-verse.react', $verse->id) }}" data-loading-label="…">
                    @csrf <input type="hidden" name="type" value="pray">
                    <button type="submit" class="btn-sm inline-flex min-h-9 items-center gap-1.5 rounded-lg px-3 py-1.5 text-[13px] font-semibold {{ $prayed ? 'bg-white text-primary-700' : 'bg-white/10 text-white hover:bg-white/20' }}" aria-pressed="{{ $prayed ? 'true' : 'false' }}">
                        <i class="fa-solid fa-hands-praying" aria-hidden="true"></i>Je prie <span class="tabular-nums">{{ $verse->prayer_count }}</span>
                    </button>
                </form>
                @else
                <a href="{{ $loginUrl }}" class="btn-sm inline-flex min-h-9 items-center gap-1.5 rounded-lg bg-white/10 px-3 py-1.5 text-[13px] font-semibold text-white hover:bg-white/20"><i class="fa-regular fa-heart" aria-hidden="true"></i>J'aime <span class="tabular-nums">{{ $verse->like_count }}</span></a>
                <a href="{{ $loginUrl }}" class="btn-sm inline-flex min-h-9 items-center gap-1.5 rounded-lg bg-white/10 px-3 py-1.5 text-[13px] font-semibold text-white hover:bg-white/20"><i class="fa-solid fa-hands-praying" aria-hidden="true"></i>Je prie <span class="tabular-nums">{{ $verse->prayer_count }}</span></a>
                @endauth
                <button type="button" class="btn-sm inline-flex min-h-9 items-center gap-1.5 rounded-lg bg-white/10 px-3 py-1.5 text-[13px] font-semibold text-white hover:bg-white/20" data-verse-share data-share-url="{{ $pageUrl }}">
                    <i class="fa-solid fa-share-nodes" aria-hidden="true"></i><span data-share-label>Partager</span>
                </button>
                <button type="button" class="btn-sm inline-flex min-h-9 items-center gap-1.5 rounded-lg bg-white/10 px-3 py-1.5 text-[13px] font-semibold text-white hover:bg-white/20" data-verse-speak hidden>
                    <i class="fa-solid fa-volume-high" aria-hidden="true"></i><span data-speak-label>Écouter</span>
                </button>
                <button type="button" class="btn-sm inline-flex min-h-9 items-center gap-1.5 rounded-lg px-3 py-1.5 text-[13px] font-semibold text-primary-900 hover:opacity-90" style="background:#FCC11D" data-verse-reader-open>
                    <i class="fa-solid fa-expand" aria-hidden="true"></i>Mode lecture
                </button>
            </div>
        </div>
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
                        @foreach(\App\Models\PrayerRequest::VISIBILITY_LABELS as $value => $label)
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
            @forelse($comments as $comment)
                <li class="flex gap-3 py-3" id="commentaire-{{ $comment->id }}">
                    @include('components.avatar', ['user' => $comment->user, 'size' => 'sm'])
                    <div class="min-w-0 flex-1">
                        <p class="text-sm"><span class="font-semibold text-slate-900">{{ $comment->user?->display_name ?? 'Membre' }}</span>
                            <span class="text-xs text-slate-500">· {{ $comment->created_at?->diffForHumans() }}</span>
                            @if($comment->isHidden())<span class="badge-yellow ml-1 text-xs">Retiré</span>@endif
                        </p>
                        <p class="mt-0.5 text-[15px] leading-relaxed break-words whitespace-pre-line text-slate-800">{{ \App\Services\Mentions::html($comment->body) }}</p>
                        @if($viewer)
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            @if($comment->canBeDeletedBy($viewer))
                            <form method="POST" action="{{ route('daily-verse.comments.destroy', [$verse->id, $comment->id]) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-slate-500 hover:text-error-700"><i class="fa-solid fa-trash" aria-hidden="true"></i> Supprimer</button>
                            </form>
                            @endif
                            @if($comment->user_id !== $viewer->id)
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
                        @endif
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

{{-- ── Mode lecture : plein écran, grand texte ───────────────────────── --}}
<dialog id="verse-reader" class="m-0 h-full max-h-none w-full max-w-none p-0 text-white backdrop:bg-slate-900/70"
        style="background: linear-gradient(160deg, #184797 0%, #0a2149 100%);" aria-labelledby="verse-reader-title">
    <div class="flex h-full flex-col">
        <div class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6">
            <h2 id="verse-reader-title" class="text-sm font-semibold" style="color:#FCC11D"><i class="fa-solid fa-sun mr-2" aria-hidden="true"></i>Verset du jour · {{ $versionName }}</h2>
            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full text-white/80 hover:bg-white/10" data-verse-reader-close aria-label="Fermer"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="flex flex-1 items-center overflow-y-auto px-6 py-8 sm:px-12">
            <div class="mx-auto max-w-3xl">
                <p class="text-3xl leading-relaxed font-medium break-words sm:text-4xl">« {{ $text }} »</p>
                <p class="mt-8 text-xl font-bold" style="color:#FCC11D">{{ $reference }}</p>
            </div>
        </div>
    </div>
</dialog>
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

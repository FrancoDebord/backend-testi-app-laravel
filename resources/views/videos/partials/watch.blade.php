{{--
    Page de lecture d'un témoignage (vidéo, short, audio, texte), partagée par /videos/{id} et /testimonies/{id}.
    Variables : WatchPage::data() — $testimony, $comments, $userReactions, $isSaved, $recommended, $pageRoute.
--}}
@php
    $type        = $testimony->type->value;
    $isShort     = $testimony->isShort();
    $publishedAt = $testimony->publishedAt();
    $shareUrl    = $testimony->share_url; // toujours l'adresse publique (Testimony::shareUrlFor)
    $description = $type !== 'text' ? $testimony->body_text : null;
    $longDesc    = $description && mb_strlen($testimony->body_plain) > 280;
    $reactionDefs = [
        ['like',    'fa-thumbs-up',      "J'aime"],
        ['pray',    'fa-hands-praying',  'Prière'],
        ['amen',    'fa-hands-clapping', 'Amen'],
        ['worship', 'fa-hands',          'Adorer'],
        ['fire',    'fa-fire',           'Feu'],
    ];
    // Page d'origine : /videos/{id} ou /testimonies/{id} (même présentation).
    [$backUrl, $backLabel] = $pageRoute === 'videos.show'
        ? [route('videos.index'), 'Vidéos']
        : [route('home'), 'Accueil'];
    $canReport = Auth::check() && Auth::id() !== $testimony->user_id;
    $inJournal = $testimony->isInJournal();
    // Lecture (docs/fonctionnalites/affichage-et-lecture.md) : qualités, en boucle, lecture automatique du suivant.
    $hasPlayer  = in_array($type, ['video', 'audio'], true) && $testimony->media_url;
    $renditions = $hasPlayer ? $testimony->playableRenditions() : [];
    $upNext     = $hasPlayer && !$inJournal
        ? $recommended->filter(fn ($r) => $r->type === $testimony->type && $r->media_url)
            ->map(fn ($r) => ['id' => $r->id, 'url' => route($pageRoute, $r->id), 'title' => $r->title])->values()
        : collect();
    $playerData = [
        'data-player'     => true,
        'data-view-url'   => route('videos.view', $testimony->id),
        'data-kind'       => $type,
        'data-testimony'  => $testimony->id,
        'data-original'   => $testimony->media_url,
        'data-renditions' => json_encode($renditions, JSON_UNESCAPED_SLASHES),
        'data-up-next'    => json_encode($upNext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ];
    // Preuves : auteur et équipe de modération (docs/fonctionnalites/preuves.md).
    $proofList = \App\Services\TestimonyProofs::canView(Auth::user(), $testimony) ? $testimony->proofs : collect();
    // Valeurs échappées par e() : le JSON reste valide dans l'attribut.
    $playerAttrs = collect($playerData)->map(fn ($v, $k) => $v === true ? $k : $k . '="' . e($v) . '"')->implode(' ');
@endphp
<div class="mx-auto max-w-[1680px]">
    <a href="{{ $backUrl }}" class="mb-4 inline-flex items-center gap-2 text-sm text-slate-500 hover:text-slate-900">
        <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>{{ $backLabel }}
    </a>

    <div class="grid grid-cols-1 gap-8 xl:grid-cols-[minmax(0,1fr)_22rem] 2xl:grid-cols-[minmax(0,1fr)_24rem]">

        {{-- ── Lecture ─────────────────────────────────────────────────── --}}
        <div class="min-w-0">
            @if($inJournal)
            {{-- Carnet privé (docs/fonctionnalites/carnet-prive.md) : partager (relecture par la modération) ou supprimer --}}
            <section class="card mb-4 p-4 sm:p-5" aria-labelledby="journal-entry-title">
                <div class="flex items-start gap-3">
                    <i class="fa-solid fa-lock mt-1 text-slate-400" aria-hidden="true"></i>
                    <div class="min-w-0 flex-1">
                        <h2 id="journal-entry-title" class="text-sm font-semibold text-slate-900">Dans votre carnet privé</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Vous seul pouvez voir ce témoignage. Partagez-le quand vous le souhaitez : il sera relu par la modération avant d'être publié.</p>
                    </div>
                </div>
                <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    @if(Auth::user()->canPublish())
                    <form method="POST" action="{{ route('journal.share', $testimony->id) }}" class="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-end" data-loading-label="Partage…">
                        @csrf
                        <div class="min-w-0 sm:w-64">
                            <label for="share-category" class="form-label">Catégorie</label>
                            <select id="share-category" name="category" required class="form-input" @error('category') aria-invalid="true" @enderror>
                                <option value="">Choisir une catégorie</option>
                                @foreach(\App\Models\Category::active()->get(['slug', 'name']) as $cat)
                                <option value="{{ $cat->slug }}" @selected(old('category', $testimony->category_slug !== 'autre' ? $testimony->category_slug : null) === $cat->slug)>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn-primary"><i class="fa-solid fa-share-nodes" aria-hidden="true"></i>Partager</button>
                    </form>
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('journal.index') }}" class="btn-secondary"><i class="fa-solid fa-book-open" aria-hidden="true"></i>Mon carnet</a>
                        <button type="button" class="btn-ghost text-error-700"
                                onclick="openConfirmModal('journal-delete-form', 'Cette entrée sera supprimée de votre carnet, avec son enregistrement.', 'Supprimer du carnet', 'Supprimer', 'fa-trash')">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i>Supprimer
                        </button>
                    </div>
                </div>
                @error('category')<p class="form-error">{{ $message }}</p>@enderror
                <form id="journal-delete-form" method="POST" action="{{ route('journal.destroy', $testimony->id) }}" data-loading-label="Suppression…" hidden>
                    @csrf @method('DELETE')
                </form>
            </section>
            @elseif($testimony->status->value !== 'approved')
            <div class="alert-warning mb-4" role="status">
                <i class="fa-solid fa-eye-slash mt-0.5" aria-hidden="true"></i>
                <p>Aperçu : ce témoignage est <strong>{{ Str::lower($testimony->status->label()) }}</strong> et n'est pas visible du public.</p>
            </div>
            @endif

            @if($type === 'video' && $testimony->isYouTube())
                {{-- Vidéo YouTube (docs/fonctionnalites/videos-youtube.md) : lecteur sans cookie publicitaire avant la lecture --}}
                <div class="relative aspect-video overflow-hidden rounded-xl bg-black">
                    <iframe src="{{ $testimony->youtubeEmbedUrl() }}" title="{{ $testimony->title }}" class="absolute inset-0 h-full w-full"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            referrerpolicy="strict-origin-when-cross-origin" allowfullscreen loading="lazy"></iframe>
                </div>
                <p class="mt-2 flex items-center gap-2 text-xs text-slate-500">
                    <i class="fa-brands fa-youtube text-error-500" aria-hidden="true"></i>Vidéo hébergée sur YouTube ·
                    <a href="{{ \App\Support\YouTube::watchUrl($testimony->youtube_id) }}" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:underline">Ouvrir sur YouTube</a>
                </p>
            @elseif($type === 'video')
                @if($testimony->media_url)
                <div class="relative overflow-hidden rounded-xl bg-black {{ $isShort ? 'flex justify-center' : '' }}">
                    <video {!! $playerAttrs !!}
                           controls playsinline preload="metadata"
                           @if($testimony->cover_url) poster="{{ $testimony->cover_url }}" @endif
                           aria-label="Lecture de la vidéo : {{ $testimony->title }}"
                           class="{{ $isShort ? 'aspect-[9/16] max-h-[78vh] w-full max-w-[min(100%,44vh)]' : 'aspect-video w-full' }} bg-black object-contain">
                        <source src="{{ $testimony->media_url }}">
                        Votre navigateur ne peut pas lire cette vidéo.
                    </video>
                    <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-slate-900 p-6 text-center text-sm text-slate-200" data-player-error hidden>
                        <i class="fa-solid fa-circle-exclamation text-2xl text-slate-400" aria-hidden="true"></i>
                        <p>La vidéo ne peut pas être lue pour le moment. Vérifiez votre connexion, puis rechargez la page.</p>
                    </div>
                </div>
                @else
                <div class="flex aspect-video flex-col items-center justify-center gap-2 rounded-xl bg-slate-900 p-6 text-center text-sm text-slate-300">
                    <i class="fa-solid fa-video-slash text-2xl text-slate-500" aria-hidden="true"></i>
                    <p>La vidéo de ce témoignage n'est pas disponible.</p>
                </div>
                @endif

            @elseif($type === 'audio')
                <div class="overflow-hidden rounded-xl bg-slate-900">
                    @if($testimony->cover_url)
                    <img src="{{ $testimony->cover_url }}" alt="" class="aspect-[21/9] w-full object-cover">
                    @else
                    <div class="flex aspect-[21/9] items-center justify-center text-5xl text-slate-600" aria-hidden="true"><i class="fa-solid fa-microphone"></i></div>
                    @endif
                    <div class="relative p-3 sm:p-4">
                        @if($testimony->media_url)
                        <audio {!! $playerAttrs !!} controls preload="metadata" class="w-full"
                               aria-label="Écouter le témoignage : {{ $testimony->title }}">
                            <source src="{{ $testimony->media_url }}">
                        </audio>
                        <p class="mt-2 text-center text-sm text-slate-300" data-player-error hidden>L'enregistrement ne peut pas être lu pour le moment. Rechargez la page.</p>
                        @else
                        <p class="text-center text-sm text-slate-300">L'enregistrement de ce témoignage n'est pas disponible.</p>
                        @endif
                    </div>
                </div>

            @else
                <article class="card overflow-hidden">
                    @if($testimony->cover_url)
                    <img src="{{ $testimony->cover_url }}" alt="" class="max-h-96 w-full object-cover">
                    @endif
                    {{-- Lecture à voix haute (resources/js/tts.js) : affichée seulement si le navigateur sait lire. --}}
                    <div class="border-b border-primary-100 bg-primary-50 px-5 py-3 sm:px-8" role="group" aria-label="Lecture à voix haute"
                         data-tts data-tts-source="testimony-body" data-tts-title="{{ $testimony->title }}"
                         data-tts-verse="{{ $testimony->bible_verse ? trim($testimony->bible_verse . ($testimony->bible_ref ? ' — ' . $testimony->bible_ref : '')) : '' }}" hidden>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="btn-primary btn-sm" data-tts-play aria-pressed="false">
                                <i class="fa-solid fa-volume-high" aria-hidden="true"></i><span data-tts-play-label>Écouter le témoignage</span>
                            </button>
                            <button type="button" class="btn-ghost btn-sm" data-tts-stop hidden>
                                <i class="fa-solid fa-stop" aria-hidden="true"></i>Arrêter
                            </button>
                            <label class="sr-only" for="tts-rate">Vitesse de lecture</label>
                            <select id="tts-rate" class="form-input min-h-9 w-auto py-1.5 text-xs" data-tts-rate>
                                @foreach([0.75, 1, 1.25, 1.5, 2] as $rate)
                                <option value="{{ $rate }}" @selected($rate == 1)>Vitesse {{ str_replace('.', ',', $rate) }}×</option>
                                @endforeach
                            </select>
                            <label class="sr-only" for="tts-lang">Langue de lecture</label>
                            <select id="tts-lang" class="form-input min-h-9 w-auto py-1.5 text-xs" data-tts-lang-select>
                                <option value="fr" lang="fr">Français</option>
                                <option value="en" lang="en">English</option>
                            </select>
                            <label class="sr-only" for="tts-voice">Voix</label>
                            <select id="tts-voice" class="form-input min-h-9 w-auto max-w-full py-1.5 text-xs" data-tts-voice hidden></select>
                            <span class="text-xs text-slate-500" data-tts-status aria-live="polite"></span>
                        </div>
                        <p class="mt-2 text-sm text-slate-700 italic" data-tts-current hidden></p>
                        <div class="mt-3 h-1 overflow-hidden rounded-full bg-primary-100" data-tts-progress-wrap hidden>
                            <div class="h-full rounded-full bg-primary-600" style="width: 0%" data-tts-progress></div>
                        </div>
                    </div>
                    <div id="testimony-body" class="p-5 text-[15px] leading-relaxed break-words whitespace-pre-line text-slate-800 sm:p-8 sm:text-base [&_strong]:font-semibold [&_strong]:text-slate-900">{{ $testimony->body_html }}</div>
                </article>
            @endif

            @if($hasPlayer)
            {{-- ── Réglages de lecture ──────────────────────────────────── --}}
            {{-- Sans JavaScript, seul le lecteur natif est proposé : qualité, boucle et lecture auto restent masquées. --}}
            <div class="mt-3 flex flex-wrap items-center gap-2" aria-label="Réglages de lecture" role="group">
                <label class="sr-only" for="player-speed">Vitesse de lecture</label>
                <select id="player-speed" class="form-input w-auto py-1.5 text-xs" data-player-speed>
                    @foreach([0.75, 1, 1.25, 1.5, 2] as $rate)
                    <option value="{{ $rate }}" @selected($rate == 1)>Vitesse {{ str_replace('.', ',', $rate) }}×</option>
                    @endforeach
                </select>
                @if($renditions)
                <label class="sr-only" for="player-quality">Qualité {{ $type === 'video' ? 'de la vidéo' : 'du son' }}</label>
                <select id="player-quality" class="form-input w-auto py-1.5 text-xs" data-player-quality hidden>
                    <option value="auto" data-auto-label="Qualité auto">Qualité auto</option>
                    @foreach(array_reverse($renditions) as $rendition)
                    <option value="{{ $rendition['quality'] }}">Qualité {{ $type === 'video' ? $rendition['quality'] : $rendition['bitrate'] . ' kbps' }}</option>
                    @endforeach
                    <option value="original">Qualité d'origine</option>
                </select>
                @else
                {{-- Pas encore de versions allégées : le menu reste visible et dit pourquoi. --}}
                @php
                    $renditionsNote = match ($testimony->renditionsStatus()) {
                        'pending', 'processing' => 'Autres qualités en préparation',
                        default                 => 'Seule la qualité d\'origine est disponible',
                    };
                @endphp
                <label class="sr-only" for="player-quality">Qualité {{ $type === 'video' ? 'de la vidéo' : 'du son' }}</label>
                <select id="player-quality" class="form-input w-auto py-1.5 text-xs" disabled aria-describedby="player-quality-note" title="{{ $renditionsNote }}">
                    <option>Qualité d'origine</option>
                </select>
                <span id="player-quality-note" class="text-xs text-slate-500">{{ $renditionsNote }}</span>
                @endif
                <button type="button" class="chip" data-player-loop aria-pressed="false" hidden>
                    <i class="fa-solid fa-repeat" aria-hidden="true"></i>En boucle
                </button>
                @if($upNext->isNotEmpty())
                <button type="button" class="chip" data-player-autoplay aria-pressed="false" hidden>
                    <i class="fa-solid fa-forward-step" aria-hidden="true"></i>Lecture auto
                </button>
                @endif
            </div>

            @if($upNext->isNotEmpty())
            <div class="alert-info mt-3 flex-wrap items-center" role="status" data-up-next-banner hidden>
                <i class="fa-solid fa-forward-step text-slate-400" aria-hidden="true"></i>
                <p class="min-w-0 flex-1">
                    À suivre<span aria-hidden="true"> dans <span class="tabular-nums" data-up-next-count>5</span> s</span> :
                    <strong class="font-semibold break-words text-slate-900" data-up-next-title></strong>
                </p>
                <div class="flex shrink-0 gap-2">
                    <a href="#" class="btn-secondary btn-sm" data-up-next-play><i class="fa-solid fa-play" aria-hidden="true"></i>Lire maintenant</a>
                    <button type="button" class="btn-ghost btn-sm" data-up-next-cancel>Annuler</button>
                </div>
            </div>
            @endif
            @endif

            {{-- ── Titre et informations ────────────────────────────────── --}}
            <h1 class="mt-4 text-lg leading-snug font-semibold break-words text-primary-600 sm:text-xl">{{ $testimony->title }}</h1>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-x-6 gap-y-3">
                <div class="flex min-w-0 items-center gap-3">
                    <a href="{{ route('profiles.show', $testimony->user->id) }}" class="shrink-0 rounded-full" tabindex="-1" aria-hidden="true">
                        @include('components.avatar', ['user' => $testimony->user, 'size' => 'md'])
                    </a>
                    <div class="min-w-0">
                        <p class="flex min-w-0 items-center gap-1.5">
                            <a href="{{ route('profiles.show', $testimony->user->id) }}" class="block truncate text-sm font-semibold text-slate-900 hover:underline">{{ $testimony->user->display_name }}</a>
                            @include('components.verified-badge', ['user' => $testimony->user])
                        </p>
                        <p class="text-xs text-slate-500"><span data-follower-count="{{ $testimony->user->id }}">{{ number_format($testimony->user->follower_count ?? 0, 0, ',', ' ') }}</span> abonné{{ ($testimony->user->follower_count ?? 0) > 1 ? 's' : '' }}</p>
                    </div>
                    <div class="shrink-0">
                        @include('components.follow-button', ['user' => $testimony->user, 'following' => $isFollowingAuthor ?? false, 'small' => true])
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @unless($inJournal)
                    <button type="button" class="chip" data-share="{{ $shareUrl }}" data-share-title="{{ $testimony->title }}">
                        <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>Partager
                    </button>
                    @endunless
                    @auth
                    <button type="button" class="{{ $isSaved ? 'chip-active' : 'chip' }}" data-save="{{ route('testimonies.save', $testimony->id) }}" aria-pressed="{{ $isSaved ? 'true' : 'false' }}">
                        <i class="{{ $isSaved ? 'fa-solid' : 'fa-regular' }} fa-bookmark" aria-hidden="true" data-save-icon></i><span data-save-label>{{ $isSaved ? 'Sauvegardé' : 'Sauvegarder' }}</span>
                    </button>
                    @endauth
                    @if(!$inJournal && Auth::id() === $testimony->user_id)
                    {{-- Retirer du public et ranger dans le carnet privé (docs/fonctionnalites/carnet-prive.md) --}}
                    <button type="button" class="chip" title="Ranger dans mon carnet privé"
                            onclick="openConfirmModal('journal-store-form', 'Le témoignage sera retiré du public et rangé dans votre carnet privé : vous seul pourrez le voir. Vous pourrez le partager de nouveau (nouvelle relecture).', 'Ranger dans mon carnet', 'Ranger', 'fa-lock')">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i><span class="hidden sm:inline" aria-hidden="true">Ranger dans mon carnet</span><span class="sr-only">Ranger dans mon carnet privé</span>
                    </button>
                    <form id="journal-store-form" method="POST" action="{{ route('journal.store', $testimony->id) }}" data-loading-label="Enregistrement…" hidden>@csrf</form>
                    @endif
                    @if($canReport)
                    <button type="button" class="chip" data-modal-open="report-modal" aria-label="Signaler ce témoignage" title="Signaler">
                        <i class="fa-regular fa-flag" aria-hidden="true"></i><span class="hidden sm:inline" aria-hidden="true">Signaler</span>
                    </button>
                    @endif
                </div>
            </div>

            {{-- ── Réactions ────────────────────────────────────────────── --}}
            <div class="mt-4 flex flex-wrap items-center gap-2" data-reactions="{{ route('testimonies.reactions.store', $testimony->id) }}"
                 data-login-url="{{ route('login') }}" data-authenticated="{{ Auth::check() ? '1' : '0' }}">
                @foreach($reactionDefs as [$rType, $rIcon, $rLabel])
                @php $pressed = in_array($rType, $userReactions, true); @endphp
                <button type="button" class="{{ $pressed ? 'chip-active' : 'chip' }}" data-reaction="{{ $rType }}" aria-pressed="{{ $pressed ? 'true' : 'false' }}">
                    <i class="fa-solid {{ $rIcon }}" aria-hidden="true"></i>{{ $rLabel }}
                    @if($rType === 'like')<span class="tabular-nums" data-count="like_count">{{ number_format($testimony->like_count, 0, ',', ' ') }}</span>@endif
                    @if($rType === 'pray')<span class="tabular-nums" data-count="prayer_count">{{ number_format($testimony->prayer_count, 0, ',', ' ') }}</span>@endif
                </button>
                @endforeach
            </div>

            {{-- ── Description ──────────────────────────────────────────── --}}
            <section class="mt-4 rounded-xl bg-slate-100 p-4 text-sm" aria-label="Description">
                <p class="flex flex-wrap items-center gap-x-3 gap-y-1 font-semibold text-slate-900">
                    <span data-views-label>{{ $testimony->viewsLabel() }}</span>
                    @if($publishedAt)<time datetime="{{ $publishedAt->toIso8601String() }}">{{ $publishedAt->translatedFormat('d F Y') }}</time>@endif
                    @if($testimony->category)
                    <a href="{{ route('videos.index', ['category' => $testimony->category->slug]) }}" class="font-medium text-slate-600 hover:text-slate-900 hover:underline">{{ $testimony->category->name }}</a>
                    @endif
                    @if($testimony->liveSession)
                    <span class="font-medium text-slate-600"><i class="fa-solid fa-tower-broadcast mr-1 text-slate-400" aria-hidden="true"></i>Rediffusion d'un direct</span>
                    @endif
                    @if($type === 'text')<span class="font-medium text-slate-600">{{ $testimony->readingMinutes() }} min de lecture</span>@endif
                </p>
                {{-- Témoignage officiel d'un événement (docs/fonctionnalites/evenements.md) --}}
                @if($testimony->event_id && $testimony->event?->isVisibleTo(Auth::user()))
                <p class="mt-2 text-slate-700">
                    <i class="fa-solid fa-calendar-days mr-1 text-primary-600" aria-hidden="true"></i>Témoignage de l'événement :
                    <a href="{{ route('events.show', $testimony->event_id) }}" class="font-semibold text-primary-600 hover:underline">{{ $testimony->event->title }}</a>
                </p>
                @endif

                @if($description)
                <div id="video-description" class="{{ $longDesc ? 'line-clamp-4' : '' }} mt-2 break-words whitespace-pre-line text-slate-700 [&_strong]:font-semibold">{{ $testimony->body_html }}</div>
                @if($longDesc)
                <button type="button" class="mt-1 text-sm font-semibold text-slate-900 hover:underline" data-expand="video-description" aria-expanded="false" aria-controls="video-description">Afficher plus</button>
                @endif
                @elseif($type !== 'text')
                <p class="mt-2 text-slate-500">Aucune description.</p>
                @endif

                @if($testimony->bible_verse)
                <blockquote class="mt-3 border-l-2 border-slate-300 pl-3">
                    <p class="text-slate-700 italic">« {{ $testimony->bible_verse }} »</p>
                    @if($testimony->bible_ref)<p class="mt-1 text-xs font-medium text-slate-500">{{ $testimony->bible_ref }}</p>@endif
                </blockquote>
                @endif

                @if(!empty($testimony->tags))
                <p class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs font-medium text-slate-600">
                    @foreach($testimony->tags as $tag)<span>#{{ $tag }}</span>@endforeach
                </p>
                @endif

                @if($proofList->isNotEmpty())
                @include('testimonies.partials.proofs', ['testimony' => $testimony, 'proofs' => $proofList])
                @endif
            </section>

            {{-- ── Parole prophétique accomplie (docs/fonctionnalites/paroles-prophetiques.md) ── --}}
            {{-- Montrée si l'auteur l'a rendue publique ; l'auteur la voit toujours, avec un lien vers la parole de son carnet. --}}
            @php $prophecy = $testimony->prophecy; @endphp
            @if($prophecy && ($prophecy->is_public || Auth::id() === $testimony->user_id))
            @php
                $fmtDate = fn ($d) => $d?->translatedFormat('j F Y');
                $audioLength = $prophecy->audio_duration ? sprintf('%d:%02d', intdiv($prophecy->audio_duration, 60), $prophecy->audio_duration % 60) : null;
            @endphp
            <section class="card-insight mt-4 p-4 text-sm sm:p-5" aria-labelledby="prophecy-title">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 id="prophecy-title" class="flex items-center gap-2 text-base font-bold text-primary-700">
                        <i class="fa-solid fa-scroll text-sun-500" aria-hidden="true"></i>Parole prophétique accomplie
                    </h2>
                    @if(!$prophecy->is_public)
                    <span class="badge-neutral">Visible de vous seul</span>
                    @endif
                    @if(Auth::id() === $testimony->user_id)
                    <a href="{{ route('prophecies.show', $prophecy->id) }}" class="ml-auto text-xs font-semibold text-primary-600 hover:underline">Voir dans mon carnet</a>
                    @endif
                </div>
                @if(filled($prophecy->title))
                <p class="mt-2 font-semibold break-words text-slate-900">{{ $prophecy->title }}</p>
                @endif

                <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                    @if($prophecy->received_on)
                    <div class="min-w-0"><dt class="text-xs text-slate-500">Reçue le</dt><dd class="font-medium text-slate-900">{{ $fmtDate($prophecy->received_on) }}</dd></div>
                    @endif
                    @if(filled($prophecy->given_by))
                    <div class="min-w-0"><dt class="text-xs text-slate-500">Donnée par</dt><dd class="font-medium break-words text-slate-900">{{ $prophecy->given_by }}</dd></div>
                    @endif
                    @if($prophecy->due_on)
                    <div class="min-w-0"><dt class="text-xs text-slate-500">Échéance annoncée</dt><dd class="font-medium text-slate-900">{{ $fmtDate($prophecy->due_on) }}</dd></div>
                    @endif
                    @if($prophecy->fulfilled_on)
                    <div class="min-w-0"><dt class="text-xs text-slate-500">Accomplie le</dt><dd class="font-semibold text-success-700"><i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i>{{ $fmtDate($prophecy->fulfilled_on) }}</dd></div>
                    @endif
                </dl>

                @if(filled($prophecy->body_text))
                <blockquote class="mt-3 border-l-2 border-sun-400 pl-3 break-words whitespace-pre-line text-slate-800">{{ $prophecy->body_text }}</blockquote>
                @endif
                @if($prophecy->audio_url)
                <div class="mt-3">
                    <p class="mb-1 text-xs text-slate-500">Parole enregistrée @if($audioLength)<span class="tabular-nums">({{ $audioLength }})</span>@endif</p>
                    <audio controls preload="none" src="{{ $prophecy->audio_url }}" class="w-full" aria-label="Écouter la parole prophétique"></audio>
                </div>
                @endif
            </section>
            @endif

            {{-- ── Pourquoi témoigner ? (docs/fonctionnalites/pourquoi-temoigner.md), pas dans le carnet privé ── --}}
            @if($testimony->visibility !== \App\Enums\TestimonyVisibility::Private)
            <x-why-testify collapsible class="mt-8" />
            @endif

            {{-- ── Commentaires ─────────────────────────────────────────── --}}
            <section class="mt-8" aria-labelledby="comments-title" id="commentaires">
                <h2 id="comments-title" class="text-base font-semibold text-primary-600">
                    Commentaires <span class="font-normal text-slate-500">· <span data-comment-count>{{ number_format($testimony->comment_count, 0, ',', ' ') }}</span></span>
                </h2>

                @auth
                <form method="POST" action="{{ route('videos.comments.store', $testimony->id) }}" class="mt-4 flex gap-3" data-comment-form data-no-loading>
                    @csrf
                    @include('components.avatar', ['user' => Auth::user(), 'size' => 'md'])
                    <div class="min-w-0 flex-1">
                        <label for="comment-body" class="sr-only">Ajouter un commentaire</label>
                        <textarea id="comment-body" name="body" rows="2" required maxlength="{{ \App\Http\Requests\CommentRequest::MAX_LENGTH }}"
                                  class="form-input" placeholder="Ajouter un commentaire…">{{ old('parent_id') ? '' : old('body') }}</textarea>
                        @error('body')<p class="form-error">{{ $message }}</p>@enderror
                        <div class="mt-2 flex justify-end">
                            <button type="submit" class="btn-primary btn-sm">Publier</button>
                        </div>
                    </div>
                </form>
                @else
                <div class="alert-info mt-4">
                    <i class="fa-solid fa-circle-info mt-0.5 text-slate-400" aria-hidden="true"></i>
                    <p><a href="{{ route('login') }}" class="font-medium text-slate-900 underline">Connectez-vous</a> pour commenter, répondre ou réagir.</p>
                </div>
                @endauth

                <div id="comments-list" class="mt-6 space-y-6" aria-live="polite">
                    @include('videos.partials.comments', ['comments' => $comments, 'testimony' => $testimony])
                </div>
                <p class="mt-6 text-sm text-slate-500" data-comments-empty @if($comments->isNotEmpty()) hidden @endif>
                    Aucun commentaire pour le moment. Soyez le premier à réagir.
                </p>

                @if($comments->hasMorePages())
                <div class="mt-6">
                    <a href="{{ $comments->nextPageUrl() }}#commentaires" class="btn-secondary btn-sm" data-load-more="comments-list">Afficher plus de commentaires</a>
                </div>
                @endif
            </section>
        </div>

        {{-- ── À regarder également ────────────────────────────────────── --}}
        <aside class="min-w-0" aria-labelledby="recommended-title">
            <h2 id="recommended-title" class="card-title mb-4">À regarder également</h2>
            @if($recommended->isEmpty())
                <p class="text-sm text-slate-500">Aucune autre publication pour le moment.</p>
            @else
            {{-- Format compact (liste dépliable) : docs/fonctionnalites/affichage-et-lecture.md --}}
            <div class="card divide-y divide-slate-100" data-layout="compact">
                @foreach($recommended as $item)
                    @include('videos.partials.row', ['testimony' => $item, 'url' => route($pageRoute, $item->id), 'narrow' => true])
                @endforeach
            </div>
            <a href="{{ route('videos.index') }}" class="btn-secondary mt-6 w-full">Voir toutes les vidéos</a>
            @endif
        </aside>
    </div>
</div>

{{-- ── Modale de signalement ─────────────────────────────────────────── --}}
@if($canReport)
<div id="report-modal" data-modal class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="report-modal-title" hidden>
    <div class="absolute inset-0 bg-slate-900/50" data-modal-close></div>
    <form method="POST" action="{{ route('testimonies.report', $testimony->id) }}" class="card relative w-full max-w-lg shadow-xl"
          data-loading-label="Envoi du signalement…">
        @csrf
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
            <h2 id="report-modal-title" class="text-base font-semibold text-primary-600">Signaler ce témoignage</h2>
            <button type="button" class="btn-ghost btn-sm" data-modal-close aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="space-y-4 px-5 py-4">
            <div>
                <label for="report-reason" class="form-label">Motif *</label>
                <select id="report-reason" name="reason" class="form-input" required>
                    <option value="inappropriateContent">Contenu inapproprié</option>
                    <option value="falseTestimony">Faux témoignage</option>
                    <option value="hateSpeech">Discours haineux</option>
                    <option value="spam">Spam</option>
                    <option value="other">Autre</option>
                </select>
            </div>
            <div>
                <label for="report-details" class="form-label">Précisions (facultatif)</label>
                <textarea id="report-details" name="details" rows="3" maxlength="500" class="form-input" placeholder="Expliquez ce qui pose problème…"></textarea>
            </div>
        </div>
        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <button type="button" class="btn-secondary" data-modal-close>Annuler</button>
            <button type="submit" class="btn-primary">Envoyer le signalement</button>
        </div>
    </form>
</div>
@endif

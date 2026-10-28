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
    // Comme l'application : réactions « j'aime » et un seul « Je prie » (allumé pour toute réaction de prière,
    // y compris les anciennes « Amen » et « Adorer Dieu »). docs/fonctionnalites/reactions.md
    $reactionDefs = [
        ['like',    'fa-thumbs-up',      __('reactions.like')],
        ['pray',    'fa-hands-praying',  __('reactions.i_pray')],
        ['fire',    'fa-fire',           __('reactions.fire')],
    ];
    $isPraying = array_intersect($userReactions, \App\Enums\ReactionType::valuesOfGroup('prayer')) !== [];
    // Page d'origine : /videos/{id} ou /testimonies/{id} (même présentation).
    [$backUrl, $backLabel] = $pageRoute === 'videos.show'
        ? [route('videos.index'), __('videos.index.title')]
        : [route('home'), __('videos.common.home')];
    // Vitesse de lecture : « 1,25 » en français, « 1.25 » en anglais.
    $rateLabel = fn ($rate) => rtrim(rtrim(\App\Support\Locales::number($rate, 2), '0'), ',.');
    $canReport = Auth::check() && Auth::id() !== $testimony->user_id;
    $inJournal = $testimony->isInJournal();
    $isPublic  = !$inJournal
        && $testimony->status === \App\Enums\TestimonyStatus::Approved
        && $testimony->visibility === \App\Enums\TestimonyVisibility::Public;
    // Partage interne « X a partagé » (docs/fonctionnalites/partage-interne.md) : témoignage publié et public, pas le sien.
    $canRepost = $canReport && $isPublic;
    // Sujet de prière confié depuis ce témoignage (docs/fonctionnalites/requetes-de-priere.md) : connexion demandée au clic.
    $canPrayerRequest = $isPublic;
    $myRepost  = $canRepost ? $testimony->reposts()->where('user_id', Auth::id())->first() : null;
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
        // Vidéo Bunny (HLS, docs/fonctionnalites/videos-bunny-stream.md) : lue par hls.js ; l'original de secours est le MP4.
        'data-original'   => $testimony->isHlsMedia() ? ($testimony->mp4PlaybackUrl() ?? '') : $testimony->media_url,
        'data-renditions' => json_encode($renditions, JSON_UNESCAPED_SLASHES),
        'data-up-next'    => json_encode($upNext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ];
    // Preuves : auteur et équipe de modération (docs/fonctionnalites/preuves.md).
    $proofList = \App\Services\TestimonyProofs::canView(Auth::user(), $testimony) ? $testimony->proofs : collect();
    // Valeurs échappées par e() : le JSON reste valide dans l'attribut.
    if ($testimony->isHlsMedia()) {
        $playerData['data-hls'] = $testimony->media_url;
    }
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
                        <h2 id="journal-entry-title" class="text-sm font-semibold text-slate-900">{{ __('videos.watch.journal_title') }}</h2>
                        <p class="mt-0.5 text-sm text-slate-500">{{ __('videos.watch.journal_text') }}</p>
                    </div>
                </div>
                <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    @if(Auth::user()->canPublish())
                    <form method="POST" action="{{ route('journal.share', $testimony->id) }}" class="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-end" data-loading-label="{{ __('videos.watch.sharing') }}">
                        @csrf
                        <div class="min-w-0 sm:w-64">
                            <label for="share-category" class="form-label">{{ __('testimonies.create.category') }}</label>
                            <select id="share-category" name="category" required class="form-input" @error('category') aria-invalid="true" @enderror>
                                <option value="">{{ __('testimonies.create.category_choose') }}</option>
                                @foreach(\App\Models\Category::active()->get(['slug', 'name', 'name_en']) as $cat)
                                <option value="{{ $cat->slug }}" @selected(old('category', $testimony->category_slug !== 'autre' ? $testimony->category_slug : null) === $cat->slug)>{{ $cat->displayName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn-primary"><i class="fa-solid fa-share-nodes" aria-hidden="true"></i>{{ __('videos.watch.share') }}</button>
                    </form>
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('journal.index') }}" class="btn-secondary"><i class="fa-solid fa-book-open" aria-hidden="true"></i>{{ __('videos.watch.my_journal') }}</a>
                        <button type="button" class="btn-ghost text-error-700"
                                onclick="openConfirmModal('journal-delete-form', @js(__('videos.watch.journal_delete_confirm')), @js(__('videos.watch.journal_delete_title')), @js(__('videos.watch.delete')), 'fa-trash')">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i>{{ __('videos.watch.delete') }}
                        </button>
                    </div>
                </div>
                @error('category')<p class="form-error">{{ $message }}</p>@enderror
                <form id="journal-delete-form" method="POST" action="{{ route('journal.destroy', $testimony->id) }}" data-loading-label="{{ __('videos.watch.deleting') }}" hidden>
                    @csrf @method('DELETE')
                </form>
            </section>
            @elseif($testimony->status->value !== 'approved')
            <div class="alert-warning mb-4" role="status">
                <i class="fa-solid fa-eye-slash mt-0.5" aria-hidden="true"></i>
                <p>{!! __('videos.watch.preview', ['status' => '<strong>' . e(Str::lower($testimony->status->label())) . '</strong>']) !!}</p>
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
                    <i class="fa-brands fa-youtube text-error-500" aria-hidden="true"></i>{{ __('videos.watch.youtube_hosted') }} ·
                    <a href="{{ \App\Support\YouTube::watchUrl($testimony->youtube_id) }}" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:underline">{{ __('videos.watch.youtube_open') }}</a>
                </p>
                {{-- Crédit de la vidéo d'origine : « Vidéo de {auteur} · {chaîne} » (docs/fonctionnalites/videos-youtube.md) --}}
                @if($credit = $testimony->youtubeCredit())
                @php $creditText = implode(' · ', array_filter([$credit['author'], $credit['channel']], 'filled')); @endphp
                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-600" data-youtube-credit>
                    <i class="fa-solid fa-circle-user text-slate-400" aria-hidden="true"></i>
                    @if($creditText !== '')
                        @if($credit['url'])
                        <a href="{{ $credit['url'] }}" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:underline">{{ __('youtube_credit.video_by', ['credit' => $creditText]) }}</a>
                        @else
                        <span>{{ __('youtube_credit.video_by', ['credit' => $creditText]) }}</span>
                        @endif
                    @else
                        <a href="{{ $credit['url'] }}" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:underline">{{ __('youtube_credit.open_channel') }}</a>
                    @endif
                </p>
                @endif
            @elseif($type === 'video')
                @if($testimony->media_url)
                <div class="relative overflow-hidden rounded-xl bg-black {{ $isShort ? 'flex justify-center' : '' }}">
                    <video {!! $playerAttrs !!}
                           controls playsinline preload="metadata"
                           @if($testimony->cover_url) poster="{{ $testimony->cover_url }}" @endif
                           aria-label="{{ __('videos.watch.video_label', ['title' => $testimony->title]) }}"
                           class="{{ $isShort ? 'aspect-[9/16] max-h-[78vh] w-full max-w-[min(100%,44vh)]' : 'aspect-video w-full' }} bg-black object-contain">
                        @unless($testimony->isHlsMedia())<source src="{{ $testimony->media_url }}">@endunless
                        {{ __('videos.watch.video_unsupported') }}
                    </video>
                    <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-slate-900 p-6 text-center text-sm text-slate-200" data-player-error hidden>
                        <i class="fa-solid fa-circle-exclamation text-2xl text-slate-400" aria-hidden="true"></i>
                        <p>{{ __('videos.watch.video_error') }}</p>
                    </div>
                </div>
                @elseif($testimony->isVideoProcessing())
                {{-- Vidéo Bunny envoyée, en cours de traitement (visible de l'auteur et de la modération) --}}
                <div class="flex aspect-video flex-col items-center justify-center gap-2 rounded-xl bg-slate-900 p-6 text-center text-sm text-slate-300" role="status">
                    <i class="fa-solid fa-gear fa-spin text-2xl text-slate-400" aria-hidden="true"></i>
                    <p class="font-semibold text-white">{{ __('videos.watch.video_processing') }}</p>
                    <p>{{ __('videos.watch.video_processing_text') }}</p>
                </div>
                @else
                <div class="flex aspect-video flex-col items-center justify-center gap-2 rounded-xl bg-slate-900 p-6 text-center text-sm text-slate-300">
                    <i class="fa-solid fa-video-slash text-2xl text-slate-500" aria-hidden="true"></i>
                    <p>{{ $testimony->video_status === \App\Models\Testimony::VIDEO_FAILED ? __('videos.watch.video_failed') : __('videos.watch.video_unavailable') }}</p>
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
                               aria-label="{{ __('videos.watch.audio_label', ['title' => $testimony->title]) }}">
                            <source src="{{ $testimony->media_url }}">
                        </audio>
                        <p class="mt-2 text-center text-sm text-slate-300" data-player-error hidden>{{ __('videos.watch.audio_error') }}</p>
                        @else
                        <p class="text-center text-sm text-slate-300">{{ __('videos.watch.audio_unavailable') }}</p>
                        @endif
                    </div>
                </div>

            @else
                <article class="card overflow-hidden">
                    <header class="p-5 pb-4 sm:p-8 sm:pb-6">@include('videos.partials.watch-heading')</header>
                    @if($testimony->cover_url)
                    <img src="{{ $testimony->cover_url }}" alt="" class="max-h-96 w-full object-cover">
                    @endif
                    {{-- Lecture à voix haute (resources/js/tts.js) : affichée seulement si le navigateur sait lire. --}}
                    <div class="border-y border-primary-100 bg-primary-50 px-5 py-3 sm:px-8" role="group" aria-label="{{ __('videos.watch.tts') }}"
                         data-tts data-tts-source="testimony-body" data-tts-title="{{ $testimony->title }}"
                         data-tts-verse="{{ $testimony->bible_verse ? trim($testimony->bible_verse . ($testimony->bible_ref ? ' — ' . $testimony->bible_ref : '')) : '' }}" hidden>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="btn-primary btn-sm" data-tts-play aria-pressed="false">
                                <i class="fa-solid fa-volume-high" aria-hidden="true"></i><span data-tts-play-label>{{ __('videos.watch.tts_play') }}</span>
                            </button>
                            <button type="button" class="btn-ghost btn-sm" data-tts-stop hidden>
                                <i class="fa-solid fa-stop" aria-hidden="true"></i>{{ __('videos.watch.tts_stop') }}
                            </button>
                            <label class="sr-only" for="tts-rate">{{ __('videos.watch.speed_label') }}</label>
                            <select id="tts-rate" class="form-input min-h-9 w-auto py-1.5 text-xs" data-tts-rate>
                                @foreach([0.75, 1, 1.25, 1.5, 2] as $rate)
                                <option value="{{ $rate }}" @selected($rate == 1)>{{ __('videos.watch.speed', ['rate' => $rateLabel($rate)]) }}</option>
                                @endforeach
                            </select>
                            <label class="sr-only" for="tts-lang">{{ __('videos.watch.tts_lang') }}</label>
                            <select id="tts-lang" class="form-input min-h-9 w-auto py-1.5 text-xs" data-tts-lang-select>
                                <option value="fr" lang="fr">Français</option>
                                <option value="en" lang="en">English</option>
                            </select>
                            <label class="sr-only" for="tts-voice">{{ __('videos.watch.tts_voice') }}</label>
                            <select id="tts-voice" class="form-input min-h-9 w-auto max-w-full py-1.5 text-xs" data-tts-voice hidden></select>
                            <span class="text-xs text-slate-500" data-tts-status aria-live="polite"></span>
                        </div>
                        <p class="mt-2 text-sm text-slate-700 italic" data-tts-current hidden></p>
                        <div class="mt-3 h-1 overflow-hidden rounded-full bg-primary-100" data-tts-progress-wrap hidden>
                            <div class="h-full rounded-full bg-primary-600" style="width: 0%" data-tts-progress></div>
                        </div>
                    </div>
                    <div id="testimony-body" class="rich-text p-5 text-[15px] leading-relaxed text-slate-800 sm:p-8 sm:text-base">{{ $testimony->body_html }}</div>
                    <x-link-preview :text="$testimony->body_text" class="mx-5 mb-5 sm:mx-8 sm:mb-8" />
                </article>
            @endif

            @if($hasPlayer)
            {{-- ── Réglages de lecture ──────────────────────────────────── --}}
            {{-- Sans JavaScript, seul le lecteur natif est proposé : qualité, boucle et lecture auto restent masquées. --}}
            <div class="mt-3 flex flex-wrap items-center gap-2" aria-label="{{ __('videos.watch.player_settings') }}" role="group">
                <label class="sr-only" for="player-speed">{{ __('videos.watch.speed_label') }}</label>
                <select id="player-speed" class="form-input w-auto py-1.5 text-xs" data-player-speed>
                    @foreach([0.75, 1, 1.25, 1.5, 2] as $rate)
                    <option value="{{ $rate }}" @selected($rate == 1)>{{ __('videos.watch.speed', ['rate' => $rateLabel($rate)]) }}</option>
                    @endforeach
                </select>
                @if($renditions)
                <label class="sr-only" for="player-quality">{{ $type === 'video' ? __('videos.watch.quality_video') : __('videos.watch.quality_audio') }}</label>
                <select id="player-quality" class="form-input w-auto py-1.5 text-xs" data-player-quality hidden>
                    <option value="auto" data-auto-label="{{ __('videos.watch.quality_auto') }}">{{ __('videos.watch.quality_auto') }}</option>
                    @foreach(array_reverse($renditions) as $rendition)
                    <option value="{{ $rendition['quality'] }}">{{ __('videos.watch.quality', ['quality' => $type === 'video' ? $rendition['quality'] : $rendition['bitrate'] . ' kbps']) }}</option>
                    @endforeach
                    <option value="original">{{ __('videos.watch.quality_original') }}</option>
                </select>
                @else
                {{-- Pas encore de versions allégées : le menu reste visible et dit pourquoi. --}}
                @php
                    $renditionsNote = match ($testimony->renditionsStatus()) {
                        'pending', 'processing' => __('videos.watch.quality_preparing'),
                        default                 => __('videos.watch.quality_original_only'),
                    };
                @endphp
                <label class="sr-only" for="player-quality">{{ $type === 'video' ? __('videos.watch.quality_video') : __('videos.watch.quality_audio') }}</label>
                <select id="player-quality" class="form-input w-auto py-1.5 text-xs" disabled aria-describedby="player-quality-note" title="{{ $renditionsNote }}">
                    <option>{{ __('videos.watch.quality_original') }}</option>
                </select>
                <span id="player-quality-note" class="text-xs text-slate-500">{{ $renditionsNote }}</span>
                @endif
                <button type="button" class="chip" data-player-loop aria-pressed="false" hidden>
                    <i class="fa-solid fa-repeat" aria-hidden="true"></i>{{ __('videos.watch.loop') }}
                </button>
                @if($upNext->isNotEmpty())
                <button type="button" class="chip" data-player-autoplay aria-pressed="false" hidden>
                    <i class="fa-solid fa-forward-step" aria-hidden="true"></i>{{ __('videos.watch.autoplay') }}
                </button>
                @endif
            </div>

            @if($upNext->isNotEmpty())
            <div class="alert-info mt-3 flex-wrap items-center" role="status" data-up-next-banner hidden>
                <i class="fa-solid fa-forward-step text-slate-400" aria-hidden="true"></i>
                <p class="min-w-0 flex-1">
                    {{ __('videos.watch.up_next') }}<span aria-hidden="true">{{ __('videos.watch.up_next_in') }}<span class="tabular-nums" data-up-next-count>5</span>{{ __('videos.watch.up_next_seconds') }}</span>{{ __('videos.watch.up_next_colon') }}
                    <strong class="font-semibold break-words text-slate-900" data-up-next-title></strong>
                </p>
                <div class="flex shrink-0 gap-2">
                    <a href="#" class="btn-secondary btn-sm" data-up-next-play><i class="fa-solid fa-play" aria-hidden="true"></i>{{ __('videos.watch.play_now') }}</a>
                    <button type="button" class="btn-ghost btn-sm" data-up-next-cancel>{{ __('videos.common.cancel') }}</button>
                </div>
            </div>
            @endif
            @endif

            {{-- ── Titre et informations (vidéo, audio ; pour un texte, en tête de l'article) ── --}}
            @unless($type === 'text')
            <div class="mt-5">@include('videos.partials.watch-heading')</div>
            @endunless

            {{-- ── Réactions et actions ─────────────────────────────────── --}}
            <div class="card mt-4 p-3 sm:p-4">
                <div class="flex flex-wrap items-center gap-2" data-reactions="{{ route('testimonies.reactions.store', $testimony->id) }}"
                     data-login-url="{{ route('login') }}" data-authenticated="{{ Auth::check() ? '1' : '0' }}"
                     @auth data-pray-start="{{ route('testimonies.prayer.start', $testimony->id) }}" data-pray-subject="{{ $testimony->title }}"
                     data-pray-excerpt="{{ Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $testimony->body_text))), 220) }}" @endauth>
                    @foreach($reactionDefs as [$rType, $rIcon, $rLabel])
                    @php $pressed = $rType === 'pray' ? $isPraying : in_array($rType, $userReactions, true); @endphp
                    <button type="button" class="{{ $pressed ? 'chip-active' : 'chip' }}" data-reaction="{{ $rType }}" aria-pressed="{{ $pressed ? 'true' : 'false' }}" @if($rType === 'pray') data-pray @endif>
                        <i class="fa-solid {{ $rIcon }}" aria-hidden="true"></i>{{ $rLabel }}
                        @if($rType === 'like')<span class="tabular-nums" data-count="like_count">{{ \App\Support\Locales::number($testimony->like_count) }}</span>@endif
                        @if($rType === 'pray')<span class="tabular-nums" data-count="prayer_count">{{ \App\Support\Locales::number($testimony->prayer_count) }}</span>@endif
                    </button>
                    @endforeach
                    {{-- Effectif de chaque réaction et personnes (docs/fonctionnalites/qui-a-reagi.md) --}}
                    <button type="button" class="btn-ghost btn-sm sm:ml-auto" data-reaction-people="{{ route('reactions.people', ['kind' => 'testimony', 'id' => $testimony->id]) }}" aria-haspopup="dialog">
                        <i class="fa-solid fa-users" aria-hidden="true"></i>{{ __('reactions.people.open') }}
                    </button>
                </div>
                @auth
                {{-- « Je prie » ajoute une prière à chaque fois : nombre de fois et retrait de la dernière (docs/fonctionnalites/prier-plusieurs-fois.md) --}}
                @php $myPrayerTimes = app(\App\Services\TestimonyReactions::class)->prayerTimes(Auth::user(), $testimony); @endphp
                <div class="mt-2 flex flex-wrap items-center gap-x-2 text-sm text-slate-600" data-prayer-times-row @if($myPrayerTimes === 0) hidden @endif>
                    <span data-prayer-times-label>{{ trans_choice('reactions.prayer_times.prayed', max(1, $myPrayerTimes), ['count' => max(1, $myPrayerTimes)]) }}</span>
                    <button type="button" class="btn-ghost btn-sm" data-pray-remove-last>{{ __('reactions.prayer_times.remove_last') }}</button>
                </div>
                @endauth
                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                    @unless($inJournal)
                    <button type="button" class="chip" data-share="{{ $shareUrl }}" data-share-title="{{ $testimony->title }}">
                        <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>{{ __('videos.watch.share') }}
                    </button>
                    @endunless
                    @if($canPrayerRequest)
                    {{-- Confier un sujet de prière depuis ce témoignage (docs/fonctionnalites/requetes-de-priere.md) --}}
                    <a href="{{ route('prayer.requests.create', ['temoignage' => $testimony->id]) }}" class="chip" title="{{ __('videos.watch.prayer_request_title') }}">
                        <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i>{{ __('videos.watch.prayer_request') }}
                    </a>
                    @endif
                    @if($canRepost)
                    <button type="button" class="{{ $myRepost ? 'chip-active' : 'chip' }}" data-modal-open="repost-modal" aria-pressed="{{ $myRepost ? 'true' : 'false' }}">
                        <i class="fa-solid fa-retweet" aria-hidden="true"></i>{{ $myRepost ? __('videos.watch.reposted') : __('videos.watch.repost') }}
                        @if($testimony->repost_count > 0)<span class="tabular-nums">{{ \App\Support\Locales::number($testimony->repost_count) }}</span>@endif
                    </button>
                    @endif
                    @auth
                    <button type="button" class="{{ $isSaved ? 'chip-active' : 'chip' }}" data-save="{{ route('testimonies.save', $testimony->id) }}" aria-pressed="{{ $isSaved ? 'true' : 'false' }}">
                        <i class="{{ $isSaved ? 'fa-solid' : 'fa-regular' }} fa-bookmark" aria-hidden="true" data-save-icon></i><span data-save-label>{{ $isSaved ? __('videos.watch.saved') : __('videos.watch.save') }}</span>
                    </button>
                    @endauth
                    @if(!$inJournal && Auth::id() === $testimony->user_id)
                    {{-- Retirer du public et ranger dans le carnet privé (docs/fonctionnalites/carnet-prive.md) --}}
                    <button type="button" class="chip" title="{{ __('videos.watch.journal_store_long') }}"
                            onclick="openConfirmModal('journal-store-form', @js(__('videos.watch.journal_store_confirm')), @js(__('videos.watch.journal_store')), @js(__('videos.watch.journal_store_button')), 'fa-lock')">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i><span class="hidden sm:inline" aria-hidden="true">{{ __('videos.watch.journal_store') }}</span><span class="sr-only">{{ __('videos.watch.journal_store_long') }}</span>
                    </button>
                    <form id="journal-store-form" method="POST" action="{{ route('journal.store', $testimony->id) }}" data-loading-label="{{ __('videos.watch.saving') }}" hidden>@csrf</form>
                    @endif
                    @if($testimony->canBeDeletedBy(Auth::user()))
                    {{-- Auteur ou administrateur (jamais un modérateur) --}}
                    <button type="button" class="chip text-error-600" title="{{ __('videos.watch.delete_title') }}"
                            onclick="openConfirmModal('testimony-delete-form', @js(Auth::id() === $testimony->user_id ? __('videos.watch.delete_confirm_own') : __('videos.watch.delete_confirm_other')), @js(__('videos.watch.delete_title')), @js(__('videos.watch.delete')), 'fa-trash')">
                        <i class="fa-solid fa-trash" aria-hidden="true"></i><span class="hidden sm:inline" aria-hidden="true">{{ __('videos.watch.delete') }}</span><span class="sr-only">{{ __('videos.watch.delete_title') }}</span>
                    </button>
                    <form id="testimony-delete-form" method="POST" action="{{ route('testimonies.destroy', $testimony->id) }}" data-loading-label="{{ __('videos.watch.deleting') }}" hidden>@csrf @method('DELETE')</form>
                    @endif
                    @if($canReport)
                    <button type="button" class="chip" data-modal-open="report-modal" aria-label="{{ __('videos.watch.report_title') }}" title="{{ __('videos.watch.report') }}">
                        <i class="fa-regular fa-flag" aria-hidden="true"></i><span class="hidden sm:inline" aria-hidden="true">{{ __('videos.watch.report') }}</span>
                    </button>
                    @endif
                </div>
            </div>

            {{-- ── Sur le web (auteur) : accord pour Google et anonymat, modifiables à tout moment
                 (docs/fonctionnalites/referencement.md) ── --}}
            @if(!$inJournal && Auth::id() === $testimony->user_id)
            <form method="POST" action="{{ route('testimonies.web-publication', $testimony->id) }}" class="card mt-4 space-y-3 p-4 sm:p-5" data-loading-label="{{ __('videos.watch.saving') }}">
                @csrf @method('PUT')
                <h2 class="card-title">{{ __('seo.author.title') }}</h2>
                <label class="flex items-start gap-3 text-sm text-slate-700">
                    <input type="hidden" name="publication_web" value="0">
                    <input type="checkbox" name="publication_web" value="1" class="mt-0.5 rounded" @checked($testimony->publication_web)>
                    <span><span class="font-semibold text-slate-900">{{ __('seo.form.publication_web') }}</span>
                        <span class="block text-xs text-slate-500">{{ __('seo.author.publication_web_help') }}</span></span>
                </label>
                <label class="flex items-start gap-3 text-sm text-slate-700">
                    <input type="hidden" name="is_anonymous" value="0">
                    <input type="checkbox" name="is_anonymous" value="1" class="mt-0.5 rounded" @checked($testimony->is_anonymous)>
                    <span><span class="font-semibold text-slate-900">{{ __('seo.form.anonymous') }}</span>
                        <span class="block text-xs text-slate-500">{{ __('seo.form.anonymous_help') }}</span></span>
                </label>
                <div class="flex justify-end"><button type="submit" class="btn-secondary btn-sm">{{ __('seo.author.save') }}</button></div>
            </form>
            @endif

            {{-- ── Description ──────────────────────────────────────────── --}}
            @php
                $showEvent  = $testimony->event_id && $testimony->event?->isVisibleTo(Auth::user());
                $hasDetails = $type !== 'text' || $showEvent || $testimony->bible_verse || !empty($testimony->tags) || $proofList->isNotEmpty();
            @endphp
            @if($hasDetails)
            <section class="card mt-4 space-y-3 p-4 text-sm sm:p-5" aria-label="{{ __('videos.watch.description') }}">
                {{-- Témoignage officiel d'un événement (docs/fonctionnalites/evenements.md) --}}
                @if($showEvent)
                <p class="text-slate-700">
                    <i class="fa-solid fa-calendar-days mr-1 text-primary-600" aria-hidden="true"></i>{{ __('videos.watch.event') }}
                    <a href="{{ route('events.show', $testimony->event_id) }}" class="font-semibold text-primary-600 hover:underline">{{ $testimony->event->title }}</a>
                </p>
                @endif

                @if($description)
                <div id="video-description" class="{{ $longDesc ? 'line-clamp-4' : '' }} rich-text text-[15px] leading-relaxed text-slate-700">{{ $testimony->body_html }}</div>
                @if($longDesc)
                <button type="button" class="-mt-1 text-sm font-semibold text-primary-600 hover:underline" data-expand="video-description" aria-expanded="false" aria-controls="video-description">{{ __('videos.common.show_more') }}</button>
                @endif
                <x-link-preview :text="$testimony->body_text" />
                @elseif($type !== 'text')
                <p class="text-slate-500">{{ __('videos.common.no_description') }}</p>
                @endif

                @if($testimony->bible_verse)
                <blockquote class="rounded-lg border-l-4 border-sun-400 bg-sun-50 px-4 py-3">
                    <p class="flex gap-2 text-[15px] text-slate-800"><i class="fa-solid fa-book-bible mt-1 text-sun-700" aria-hidden="true"></i><span class="italic">{{ __('videos.index.quoted', ['q' => $testimony->bible_verse]) }}</span></p>
                    @if($testimony->bible_ref)<p class="mt-1 pl-6 text-xs font-semibold text-sun-700">{{ $testimony->bible_ref }}</p>@endif
                </blockquote>
                @endif

                @if(!empty($testimony->tags))
                <p class="flex flex-wrap gap-1.5">
                    @foreach($testimony->tags as $tag)<span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">#{{ $tag }}</span>@endforeach
                </p>
                @endif

                @if($proofList->isNotEmpty())
                @include('testimonies.partials.proofs', ['testimony' => $testimony, 'proofs' => $proofList])
                @endif
            </section>
            @endif

            {{-- ── Texte écrit, verset du thème, partage (docs/fonctionnalites/referencement.md) ── --}}
            @unless($inJournal)
            @include('testimonies.partials.web-extras')
            @endunless

            {{-- ── Parole prophétique accomplie (docs/fonctionnalites/paroles-prophetiques.md) ── --}}
            {{-- Montrée si l'auteur l'a rendue publique ; l'auteur la voit toujours, avec un lien vers la parole de son carnet. --}}
            @php $prophecy = $testimony->prophecy; @endphp
            @if($prophecy && ($prophecy->is_public || Auth::id() === $testimony->user_id))
            @php
                $fmtDate = fn ($d) => $d?->translatedFormat(__('testimonies.create.date_format'));
                $audioLength = $prophecy->audio_duration ? sprintf('%d:%02d', intdiv($prophecy->audio_duration, 60), $prophecy->audio_duration % 60) : null;
            @endphp
            <section class="card-insight mt-4 p-4 text-sm sm:p-5" aria-labelledby="prophecy-title">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 id="prophecy-title" class="flex items-center gap-2 text-base font-bold text-primary-700">
                        <i class="fa-solid fa-scroll text-sun-500" aria-hidden="true"></i>{{ __('videos.watch.prophecy_title') }}
                    </h2>
                    @if(!$prophecy->is_public)
                    <span class="badge-neutral">{{ __('videos.watch.prophecy_private') }}</span>
                    @endif
                    @if(Auth::id() === $testimony->user_id)
                    <a href="{{ route('prophecies.show', $prophecy->id) }}" class="ml-auto text-xs font-semibold text-primary-600 hover:underline">{{ __('videos.watch.prophecy_view') }}</a>
                    @endif
                </div>
                @if(filled($prophecy->title))
                <p class="mt-2 font-semibold break-words text-slate-900">{{ $prophecy->title }}</p>
                @endif

                <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                    @if($prophecy->received_on)
                    <div class="min-w-0"><dt class="text-xs text-slate-500">{{ __('videos.watch.prophecy_received') }}</dt><dd class="font-medium text-slate-900">{{ $prophecy->receivedLabel() }}</dd></div>
                    @endif
                    @if(filled($prophecy->given_by))
                    <div class="min-w-0"><dt class="text-xs text-slate-500">{{ __('videos.watch.prophecy_given_by') }}</dt><dd class="font-medium break-words text-slate-900">{{ $prophecy->given_by }}</dd></div>
                    @endif
                    @if($prophecy->due_on)
                    <div class="min-w-0"><dt class="text-xs text-slate-500">{{ __('videos.watch.prophecy_due') }}</dt><dd class="font-medium text-slate-900">{{ $fmtDate($prophecy->due_on) }}</dd></div>
                    @endif
                    @if($prophecy->fulfilled_on)
                    <div class="min-w-0"><dt class="text-xs text-slate-500">{{ __('videos.watch.prophecy_fulfilled') }}</dt><dd class="font-semibold text-success-700"><i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i>{{ $fmtDate($prophecy->fulfilled_on) }}</dd></div>
                    @endif
                </dl>

                @if(filled($prophecy->body_text))
                <blockquote class="mt-3 border-l-2 border-sun-400 pl-3 break-words whitespace-pre-line text-slate-800">{{ $prophecy->body_text }}</blockquote>
                @endif
                @if($prophecy->audio_url)
                <div class="mt-3">
                    <p class="mb-1 text-xs text-slate-500">{{ __('videos.watch.prophecy_audio') }} @if($audioLength)<span class="tabular-nums">({{ $audioLength }})</span>@endif</p>
                    <audio controls preload="none" src="{{ $prophecy->audio_url }}" class="w-full" aria-label="{{ __('videos.watch.prophecy_listen') }}"></audio>
                </div>
                @endif
            </section>
            @endif

            {{-- ── Pourquoi témoigner ? (docs/fonctionnalites/pourquoi-temoigner.md), pas dans le carnet privé ── --}}
            @if($testimony->visibility !== \App\Enums\TestimonyVisibility::Private)
            <x-why-testify collapsible class="mt-8" />
            @endif

            {{-- ── Commentaires ─────────────────────────────────────────── --}}
            <section class="card mt-8 scroll-mt-20 p-4 sm:p-6" aria-labelledby="comments-title" id="commentaires">
                <h2 id="comments-title" class="card-title text-lg">
                    {{ __('videos.watch.comments') }} <span class="font-normal text-slate-500">· <span data-comment-count>{{ \App\Support\Locales::number($testimony->comment_count) }}</span></span>
                </h2>

                @auth
                <form method="POST" action="{{ route('videos.comments.store', $testimony->id) }}" class="mt-4 flex gap-3" data-comment-form data-no-loading>
                    @csrf
                    @include('components.avatar', ['user' => Auth::user(), 'size' => 'md'])
                    <div class="min-w-0 flex-1">
                        <label for="comment-body" class="sr-only">{{ __('videos.watch.comment_label') }}</label>
                        <textarea id="comment-body" name="body" rows="2" required maxlength="{{ \App\Http\Requests\CommentRequest::MAX_LENGTH }}"
                                  class="form-input" placeholder="{{ __('videos.watch.comment_placeholder') }}" data-mention-input data-mention-testimony="{{ $testimony->id }}">{{ old('parent_id') ? '' : old('body') }}</textarea>
                        @include('components.mention-input')
                        @error('body')<p class="form-error">{{ $message }}</p>@enderror
                        <div class="mt-2 flex justify-end">
                            <button type="submit" class="btn-primary btn-sm">{{ __('videos.watch.comment_submit') }}</button>
                        </div>
                    </div>
                </form>
                @else
                <div class="alert-info mt-4">
                    <i class="fa-solid fa-circle-info mt-0.5 text-slate-400" aria-hidden="true"></i>
                    <p><a href="{{ route('login') }}" class="font-medium text-slate-900 underline">{{ __('videos.watch.login') }}</a> {{ __('videos.watch.login_to_comment') }}</p>
                </div>
                @endauth

                <div id="comments-list" class="mt-6 space-y-6" aria-live="polite">
                    @include('videos.partials.comments', ['comments' => $comments, 'testimony' => $testimony])
                </div>
                <p class="mt-6 text-sm text-slate-500" data-comments-empty @if($comments->isNotEmpty()) hidden @endif>
                    {{ __('videos.watch.no_comments') }}
                </p>

                @if($comments->hasMorePages())
                <div class="mt-6">
                    <a href="{{ $comments->nextPageUrl() }}#commentaires" class="btn-secondary btn-sm" data-load-more="comments-list">{{ __('videos.watch.more_comments') }}</a>
                </div>
                @endif
            </section>
        </div>

        {{-- ── À regarder également ────────────────────────────────────── --}}
        <aside class="min-w-0" aria-labelledby="recommended-title">
            <h2 id="recommended-title" class="card-title mb-4">{{ __('videos.watch.recommended') }}</h2>
            @if($recommended->isEmpty())
                <p class="text-sm text-slate-500">{{ __('videos.watch.recommended_empty') }}</p>
            @else
            {{-- Format compact (liste dépliable) : docs/fonctionnalites/affichage-et-lecture.md --}}
            <div class="card divide-y divide-slate-100" data-layout="compact">
                @foreach($recommended as $item)
                    @include('videos.partials.row', ['testimony' => $item, 'url' => route($pageRoute, $item->id), 'narrow' => true])
                @endforeach
            </div>
            <a href="{{ route('videos.index') }}" class="btn-secondary mt-6 w-full">{{ __('videos.watch.all_videos') }}</a>
            @endif
        </aside>
    </div>
</div>

{{-- ── Modale « Partager sur My Testimony » (docs/fonctionnalites/partage-interne.md) ── --}}
@if($canRepost)
<div id="repost-modal" data-modal class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="repost-modal-title" hidden>
    <div class="absolute inset-0 bg-slate-900/50" data-modal-close></div>
    <div class="card relative w-full max-w-lg shadow-xl">
        <form method="POST" action="{{ route('testimonies.repost', $testimony->id) }}" data-loading-label="{{ __('videos.watch.sharing') }}">
            @csrf
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
                <h2 id="repost-modal-title" class="text-base font-semibold text-primary-600">{{ __('videos.watch.repost') }}</h2>
                <button type="button" class="btn-ghost btn-sm" data-modal-close aria-label="{{ __('videos.common.close') }}"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="space-y-3 px-5 py-4">
                <p class="text-sm text-slate-600">{!! __('videos.watch.repost_intro', ['name' => '<strong class="font-semibold text-slate-900">' . e($testimony->webAuthorName(Auth::user())) . '</strong>']) !!}</p>
                <div>
                    <label for="repost-comment" class="form-label">{{ __('videos.watch.repost_comment') }}</label>
                    <textarea id="repost-comment" name="comment" rows="3" maxlength="500" class="form-input" placeholder="{{ __('videos.watch.repost_placeholder') }}">{{ old('comment', $myRepost?->comment) }}</textarea>
                </div>
            </div>
            <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 px-5 py-4">
                <button type="button" class="btn-secondary" data-modal-close>{{ __('videos.common.close') }}</button>
                @if($myRepost)
                <button type="submit" form="repost-delete-form" class="btn-secondary"><i class="fa-solid fa-xmark" aria-hidden="true"></i>{{ __('videos.watch.repost_cancel') }}</button>
                @endif
                <button type="submit" class="btn-primary"><i class="fa-solid fa-retweet" aria-hidden="true"></i>{{ $myRepost ? __('videos.watch.repost_update') : __('videos.watch.share') }}</button>
            </div>
        </form>
        @if($myRepost)
        <form id="repost-delete-form" method="POST" action="{{ route('testimonies.unrepost', $testimony->id) }}" hidden>@csrf @method('DELETE')</form>
        @endif
    </div>
</div>
@endif

{{-- ── Modale de signalement ─────────────────────────────────────────── --}}
@if($canReport)
<div id="report-modal" data-modal class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="report-modal-title" hidden>
    <div class="absolute inset-0 bg-slate-900/50" data-modal-close></div>
    <form method="POST" action="{{ route('testimonies.report', $testimony->id) }}" class="card relative w-full max-w-lg shadow-xl"
          data-loading-label="{{ __('videos.watch.report_sending') }}">
        @csrf
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
            <h2 id="report-modal-title" class="text-base font-semibold text-primary-600">{{ __('videos.watch.report_title') }}</h2>
            <button type="button" class="btn-ghost btn-sm" data-modal-close aria-label="{{ __('videos.common.close') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="space-y-4 px-5 py-4">
            <div>
                <label for="report-reason" class="form-label">{{ __('videos.watch.report_reason') }} *</label>
                <select id="report-reason" name="reason" class="form-input" required>
                    <option value="inappropriateContent">{{ __('videos.watch.reason_inappropriate') }}</option>
                    <option value="falseTestimony">{{ __('videos.watch.reason_false') }}</option>
                    <option value="hateSpeech">{{ __('videos.watch.reason_hate') }}</option>
                    <option value="spam">{{ __('videos.watch.reason_spam') }}</option>
                    <option value="other">{{ __('videos.watch.reason_other') }}</option>
                </select>
            </div>
            <div>
                <label for="report-details" class="form-label">{{ __('videos.watch.report_details') }}</label>
                <textarea id="report-details" name="details" rows="3" maxlength="500" class="form-input" placeholder="{{ __('videos.watch.report_placeholder') }}"></textarea>
            </div>
        </div>
        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <button type="button" class="btn-secondary" data-modal-close>{{ __('videos.common.cancel') }}</button>
            <button type="submit" class="btn-primary">{{ __('videos.watch.report_submit') }}</button>
        </div>
    </form>
</div>
@endif

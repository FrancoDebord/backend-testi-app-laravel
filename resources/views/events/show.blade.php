@extends('layouts.app')
@section('title', $event->title)
@use('App\Http\Controllers\Web\EventController', 'EventPage')
@use('App\Models\EventParticipation')
@php
    $authUser    = Auth::user();
    $header      = $event->title;
    $subheader   = $event->type->label() . ' · ' . EventPage::dateRange($event);
    $breadcrumbs = [
        ['label' => 'Accueil', 'url' => route('home')],
        ['label' => 'Événements', 'url' => route('events.index')],
        ['label' => Str::limit($event->title, 40)],
    ];
    $images   = $event->images;
    $place    = EventPage::place($event);
    $guests   = collect($event->guests ?? [])->filter(fn ($g) => filled($g['name'] ?? null));
    $phaseBadge = match (true) {
        $event->isCancelled()           => 'badge-rejected',
        $event->phase() === 'ongoing'   => 'badge-validated',
        $event->phase() === 'past'      => 'badge-draft',
        default                         => 'badge-pending',
    };
    $liveUrl  = $live ? ($live->isHost($authUser) ? route('lives.studio', $live->id) : route('lives.show', $live->id)) : null;
    $canComment = $authUser && $event->status !== \App\Enums\EventStatus::Draft && ($event->comments_enabled || $manager);
    $sectionUrl = fn (string $s) => route('events.show', [$event->id, 'section' => $s === 'temoignages' ? null : $s]);
@endphp

@section('content')

{{-- ── Gestion (organisateur, administrateurs) ───────────────────────── --}}
@if($manager)
<div class="card mb-6 flex flex-wrap items-center gap-2 p-3 sm:p-4" role="group" aria-label="Gestion de l'événement">
    <p class="section-title mr-2 w-full sm:w-auto">Gestion</p>
    <a href="{{ route('events.edit', $event->id) }}" class="btn-secondary btn-sm"><i class="fa-solid fa-pen" aria-hidden="true"></i>Modifier</a>
    @if($activeLive && $activeLive->isHost($authUser))
        <a href="{{ route('lives.studio', $activeLive->id) }}" class="btn-secondary btn-sm"><i class="fa-solid fa-video" aria-hidden="true"></i>Reprendre le studio</a>
    @elseif($event->status === \App\Enums\EventStatus::Published && !$activeLive)
        <a href="{{ route('lives.create', ['event' => $event->id]) }}" class="btn-secondary btn-sm"><i class="fa-solid fa-video" aria-hidden="true"></i>Lancer un direct</a>
    @endif
    <a href="{{ route('publish', ['event' => $event->id]) }}" class="btn-secondary btn-sm"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>Publier un témoignage</a>
    <a href="{{ route('events.participants', $event->id) }}" class="btn-secondary btn-sm"><i class="fa-solid fa-users" aria-hidden="true"></i>Participants</a>
    <a href="#co-gestionnaires" class="btn-secondary btn-sm"><i class="fa-solid fa-user-gear" aria-hidden="true"></i>Co-gestionnaires</a>
    @if($administrator)
    <form id="event-delete-form" method="POST" action="{{ route('events.destroy', $event->id) }}" class="sm:ml-auto" data-loading-label="Suppression…">
        @csrf @method('DELETE')
        <button type="button" class="btn-ghost btn-sm text-error-500"
                onclick="openConfirmModal('event-delete-form', 'L\'événement, ses images, ses participations et ses commentaires seront supprimés. Les témoignages publiés restent en ligne.', 'Supprimer l\'événement', 'Supprimer', 'fa-trash')">
            <i class="fa-solid fa-trash" aria-hidden="true"></i>Supprimer
        </button>
    </form>
    @endif
    @if($event->status === \App\Enums\EventStatus::Draft)
    <p class="w-full text-xs text-slate-500"><i class="fa-solid fa-eye-slash mr-1" aria-hidden="true"></i>Brouillon : cette page n'est visible que des gestionnaires. Publiez-la depuis « Modifier ».</p>
    @endif
</div>
@endif

@if($event->isCancelled())
<div class="alert-error mb-6" role="status">
    <i class="fa-solid fa-ban mt-0.5"></i>
    <p><strong>Événement annulé.</strong> Les réponses de participation ne sont plus possibles.</p>
</div>
@endif

<div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">

    {{-- ── Colonne principale ─────────────────────────────────────────── --}}
    <div class="min-w-0 space-y-6">

        {{-- Images (carrousel) --}}
        @if($images->count() > 1)
        <div class="relative overflow-hidden rounded-xl bg-slate-900" data-carousel role="region" aria-roledescription="carrousel"
             aria-label="Images de l'événement" tabindex="0">
            <div class="flex aspect-video snap-x snap-mandatory overflow-x-auto scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" data-carousel-track>
                @foreach($images as $i => $image)
                <div class="h-full w-full shrink-0 snap-center" role="group" aria-roledescription="diapositive" aria-label="Image {{ $i + 1 }} sur {{ $images->count() }}" data-carousel-slide>
                    <img src="{{ $image->url }}" alt="" class="h-full w-full object-cover" @if($i > 0) loading="lazy" @endif>
                </div>
                @endforeach
            </div>
            <button type="button" class="absolute top-1/2 left-2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-primary-700 shadow-soft hover:bg-white focus-visible:outline-2 focus-visible:outline-white"
                    data-carousel-prev aria-label="Image précédente"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
            <button type="button" class="absolute top-1/2 right-2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-primary-700 shadow-soft hover:bg-white focus-visible:outline-2 focus-visible:outline-white"
                    data-carousel-next aria-label="Image suivante"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
            <div class="absolute inset-x-0 bottom-2 flex justify-center gap-1.5">
                @foreach($images as $i => $image)
                <button type="button" class="h-2.5 w-2.5 rounded-full bg-white/50 aria-[current=true]:bg-white focus-visible:outline-2 focus-visible:outline-white"
                        data-carousel-dot="{{ $i }}" aria-label="Afficher l'image {{ $i + 1 }}" aria-current="{{ $i === 0 ? 'true' : 'false' }}"></button>
                @endforeach
            </div>
        </div>
        @elseif($images->count() === 1)
        <div class="aspect-video overflow-hidden rounded-xl bg-slate-100">
            <img src="{{ $images->first()->url }}" alt="" class="h-full w-full object-cover">
        </div>
        @endif

        {{-- Présentation --}}
        <section class="card p-5 sm:p-6" aria-labelledby="event-about">
            <div class="flex flex-wrap items-center gap-2">
                <span class="badge-blue"><i class="fa-solid {{ $event->type->icon() }}" aria-hidden="true"></i>{{ $event->type->label() }}</span>
                <span class="{{ $phaseBadge }}">{{ $event->phaseLabel() }}</span>
                @if($manager && $event->status === \App\Enums\EventStatus::Draft)
                    <span class="badge-draft">Brouillon</span>
                @endif
                @if($live && $live->isOnAir())<span class="badge-live">En direct</span>@endif
            </div>
            <h2 id="event-about" class="card-title mt-4">À propos</h2>
            @if(filled($event->description))
                <p class="mt-2 text-sm leading-relaxed break-words whitespace-pre-line text-slate-700">{{ $event->description }}</p>
            @else
                <p class="mt-2 text-sm text-slate-500">Aucune présentation.</p>
            @endif

            @if($guests->isNotEmpty())
            <h3 class="card-title mt-6">Invités principaux</h3>
            <ul class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach($guests as $guest)
                <li class="flex min-w-0 items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600" aria-hidden="true"><i class="fa-solid fa-user"></i></span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-slate-900">{{ $guest['name'] }}</span>
                        @if(filled($guest['role'] ?? null))<span class="block truncate text-xs text-slate-500">{{ $guest['role'] }}</span>@endif
                    </span>
                </li>
                @endforeach
            </ul>
            @endif
        </section>

        {{-- Témoignages officiels / commentaires des participants --}}
        <section aria-label="Témoignages et commentaires">
            <nav aria-label="Sections" class="mb-4 border-b border-slate-200">
                <ul class="-mb-px flex gap-5 overflow-x-auto">
                    <li><a href="{{ $sectionUrl('temoignages') }}" class="{{ $section === 'temoignages' ? 'tab-active' : 'tab' }}" @if($section === 'temoignages') aria-current="page" @endif>Témoignages <span class="text-xs text-slate-500">{{ $testimonies->total() }}</span></a></li>
                    <li><a href="{{ $sectionUrl('commentaires') }}" class="{{ $section === 'commentaires' ? 'tab-active' : 'tab' }}" @if($section === 'commentaires') aria-current="page" @endif>Commentaires <span class="text-xs text-slate-500">{{ $comments->total() }}</span></a></li>
                </ul>
            </nav>

            @if($section === 'temoignages')
                @if($testimonies->isEmpty())
                    @include('components.empty-state', [
                        'title'       => 'Aucun témoignage pour le moment',
                        'text'        => "Les témoignages officiels de l'événement apparaîtront ici. Les participants peuvent raconter ce qu'ils ont vécu dans les commentaires.",
                        'actionUrl'   => $sectionUrl('commentaires'),
                        'actionLabel' => 'Voir les commentaires',
                    ])
                @else
                    <div class="mb-6">
                        @include('components.testimony-list', ['items' => $testimonies, 'routeName' => 'testimonies.show', 'gridClass' => 'grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3'])
                    </div>
                    {{ $testimonies->links() }}
                @endif
            @else
                {{-- Formulaire --}}
                @if($canComment)
                <form method="POST" action="{{ route('events.comments.store', $event->id) }}" class="card mb-6 space-y-3 p-4 sm:p-5" data-loading-label="Envoi…">
                    @csrf
                    <label for="event-comment" class="form-label">Racontez ce que vous avez vécu</label>
                    <textarea id="event-comment" name="body" rows="4" required minlength="2" maxlength="3000" class="form-input"
                              placeholder="Une guérison, une délivrance, une parole reçue…" @error('body') aria-invalid="true" @enderror>{{ old('body') }}</textarea>
                    @error('body')<p class="form-error">{{ $message }}</p>@enderror
                    @if(!$event->comments_enabled)
                    <p class="form-hint">Les commentaires sont fermés au public ; en tant que gestionnaire, vous pouvez encore écrire.</p>
                    @endif
                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>Publier</button>
                    </div>
                </form>
                @elseif(!$authUser)
                <div class="alert-info mb-6">
                    <i class="fa-solid fa-circle-info mt-0.5"></i>
                    <p><a href="{{ route('login') }}" class="font-semibold underline">Connectez-vous</a> pour partager ce que vous avez vécu pendant cet événement.</p>
                </div>
                @elseif(!$event->comments_enabled)
                <p class="mb-6 text-sm text-slate-500"><i class="fa-solid fa-lock mr-1" aria-hidden="true"></i>Les commentaires sont fermés pour cet événement.</p>
                @endif

                {{-- Liste --}}
                @if($comments->isEmpty())
                    @include('components.empty-state', [
                        'title' => 'Aucun commentaire',
                        'text'  => 'Soyez le premier à partager ce que Dieu a fait pendant cet événement.',
                    ])
                @else
                <ul class="card mb-6 divide-y divide-slate-100">
                    @foreach($comments as $comment)
                    @php
                        $canDelete = $authUser && ($comment->user_id === $authUser->id || $manager || $authUser->canModerate());
                    @endphp
                    <li class="flex gap-3 px-4 py-4 sm:px-5" id="commentaire-{{ $comment->id }}">
                        @include('components.avatar', ['user' => $comment->user, 'size' => 'sm'])
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                <span class="flex min-w-0 items-center gap-1.5 font-semibold text-slate-900">
                                    <span class="truncate">{{ $comment->user?->display_name ?? 'Compte supprimé' }}</span>
                                    @include('components.verified-badge', ['user' => $comment->user])
                                </span>
                                <time class="text-xs text-slate-500" datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->diffForHumans() }}</time>
                                @if($comment->testimony)
                                <a href="{{ route('testimonies.show', $comment->testimony_id) }}" class="badge-validated hover:underline">Enregistré comme témoignage</a>
                                @endif
                            </p>
                            <p class="mt-1 text-sm break-words whitespace-pre-line text-slate-700">{{ $comment->body }}</p>

                            @if($canDelete || ($manager && !$comment->testimony))
                            <div class="mt-2 flex flex-wrap gap-2">
                                @if($manager && !$comment->testimony)
                                <button type="button" class="btn-ghost btn-sm" data-modal-open="promote-modal"
                                        data-promote-url="{{ route('events.comments.promote', [$event->id, $comment->id]) }}">
                                    <i class="fa-solid fa-award" aria-hidden="true"></i>Enregistrer comme témoignage
                                </button>
                                @endif
                                @if($canDelete)
                                <form id="comment-delete-{{ $comment->id }}" method="POST" action="{{ route('events.comments.destroy', [$event->id, $comment->id]) }}" data-loading-label="Suppression…">
                                    @csrf @method('DELETE')
                                    <button type="button" class="btn-ghost btn-sm"
                                            onclick="openConfirmModal('comment-delete-{{ $comment->id }}', 'Ce commentaire sera supprimé.', 'Supprimer le commentaire', 'Supprimer', 'fa-trash')">
                                        <i class="fa-solid fa-trash" aria-hidden="true"></i>Supprimer
                                    </button>
                                </form>
                                @endif
                            </div>
                            @endif
                        </div>
                    </li>
                    @endforeach
                </ul>
                {{ $comments->links() }}
                @endif
            @endif
        </section>
    </div>

    {{-- ── Colonne latérale ───────────────────────────────────────────── --}}
    <aside class="min-w-0 space-y-6">

        @if($live)
        <section class="card p-5" aria-labelledby="event-live-title">
            <p class="{{ $live->status->badgeClass() }}">{{ $live->status->label() }}</p>
            <h2 id="event-live-title" class="card-title mt-3">{{ $live->title }}</h2>
            <p class="mt-1 text-sm text-slate-500">Direct de l'événement</p>
            <a href="{{ $liveUrl }}" class="btn-cta mt-4 w-full">
                <i class="fa-solid fa-tower-broadcast" aria-hidden="true"></i>{{ $live->isHost($authUser) ? 'Ouvrir le studio' : 'Regarder le direct' }}
            </a>
        </section>
        @endif

        {{-- Participation --}}
        <section class="card p-5" aria-labelledby="event-participate-title">
            <h2 id="event-participate-title" class="card-title">Participer</h2>
            <p class="mt-1 text-sm text-slate-600">
                <span class="font-semibold text-slate-900">{{ number_format($event->going_count, 0, ',', ' ') }}</span> participe{{ $event->going_count > 1 ? 'nt' : '' }}
                · {{ number_format($event->not_going_count, 0, ',', ' ') }} ne participe{{ $event->not_going_count > 1 ? 'nt' : '' }} pas
            </p>

            @if(!$event->acceptsParticipation())
                <p class="mt-3 text-sm text-slate-500">
                    @if($event->isCancelled()) Cet événement est annulé.
                    @elseif($event->status === \App\Enums\EventStatus::Draft) Publiez l'événement pour recevoir des réponses.
                    @else Cet événement est terminé.
                    @endif
                </p>
            @elseif(!$authUser)
                <a href="{{ route('login') }}" class="btn-secondary mt-4 w-full"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>Se connecter pour répondre</a>
            @else
                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-1">
                    @foreach([EventParticipation::GOING => ['Je participe', 'fa-circle-check'], EventParticipation::NOT_GOING => ['Je ne participe pas', 'fa-circle-xmark']] as $value => [$label, $icon])
                    @php $chosen = $participation === $value; @endphp
                    <form method="POST" action="{{ route('events.participate', $event->id) }}" data-loading-label="Enregistrement…">
                        @csrf
                        <input type="hidden" name="status" value="{{ $value }}">
                        <button type="submit" class="{{ $chosen ? 'btn-primary' : 'btn-secondary' }} w-full" aria-pressed="{{ $chosen ? 'true' : 'false' }}">
                            <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>{{ $label }}
                        </button>
                    </form>
                    @endforeach
                </div>
                @if($participation)
                <form method="POST" action="{{ route('events.participation.cancel', $event->id) }}" class="mt-2 text-center" data-loading-label="Retrait…">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-ghost btn-sm">Retirer ma réponse</button>
                </form>
                @endif
            @endif
        </section>

        {{-- Requêtes et sessions de prière (docs/fonctionnalites/requetes-de-priere.md) --}}
        @if($event->status === \App\Enums\EventStatus::Published)
        @include('prayer.partials.event-section', ['event' => $event])
        @endif

        {{-- Infos pratiques --}}
        <section class="card p-5" aria-labelledby="event-info-title">
            <h2 id="event-info-title" class="card-title">Informations</h2>
            <dl class="mt-3 space-y-3 text-sm">
                <div class="flex gap-3">
                    <dt class="w-5 shrink-0 text-center text-slate-400"><i class="fa-regular fa-calendar" aria-hidden="true"></i><span class="sr-only">Dates</span></dt>
                    <dd class="min-w-0 text-slate-700">{{ EventPage::dateRange($event) }}</dd>
                </div>
                @if($place)
                <div class="flex gap-3">
                    <dt class="w-5 shrink-0 text-center text-slate-400"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span class="sr-only">Lieu</span></dt>
                    <dd class="min-w-0 break-words text-slate-700">{{ $place }}</dd>
                </div>
                @endif
                @if(filled($event->address))
                <div class="flex gap-3">
                    <dt class="w-5 shrink-0 text-center text-slate-400"><i class="fa-solid fa-signs-post" aria-hidden="true"></i><span class="sr-only">Adresse</span></dt>
                    <dd class="min-w-0 break-words text-slate-700">{{ $event->address }}</dd>
                </div>
                @endif
                <div class="flex gap-3">
                    <dt class="w-5 shrink-0 text-center text-slate-400"><i class="fa-solid fa-user-tie" aria-hidden="true"></i><span class="sr-only">Organisateur</span></dt>
                    <dd class="flex min-w-0 items-center gap-1.5">
                        @if($event->organizer)
                        <a href="{{ route('profiles.show', $event->organizer_id) }}" class="truncate font-semibold text-primary-600 hover:underline">{{ $event->organizer->display_name }}</a>
                        @include('components.verified-badge', ['user' => $event->organizer])
                        @endif
                    </dd>
                </div>
            </dl>

            {{-- Lieu : mini-carte OpenStreetMap et itinéraire (docs/fonctionnalites/evenements.md) --}}
            @if($directionsUrl = $event->directionsUrl())
            @if($event->hasCoordinates())
            <div class="event-map mt-4 h-48 w-full overflow-hidden rounded-lg border border-slate-200 bg-slate-100"
                 data-event-map-view data-lat="{{ $event->latitude }}" data-lng="{{ $event->longitude }}"
                 role="img" aria-label="Carte du lieu de l'événement"></div>
            @endif
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ $directionsUrl }}" target="_blank" rel="noopener" class="btn-primary btn-sm">
                    <i class="fa-solid fa-diamond-turn-right" aria-hidden="true"></i>Itinéraire
                </a>
                <button type="button" class="btn-secondary btn-sm" data-copy-address="{{ $event->fullAddress() ?: \App\Models\Event::formatCoordinate($event->latitude) . ', ' . \App\Models\Event::formatCoordinate($event->longitude) }}">
                    <i class="fa-regular fa-copy" aria-hidden="true"></i><span data-copy-label>Copier l'adresse</span>
                </button>
            </div>
            @endif
        </section>

        {{-- Co-gestionnaires (2 au plus) : visibles des gestionnaires ; ajout et retrait par les responsables --}}
        @if($manager)
        @php
            $coManagers = $event->managers;
            $isFull     = $coManagers->count() >= \App\Models\Event::MAX_MANAGERS;
        @endphp
        <section class="card scroll-mt-24 p-5" id="co-gestionnaires" aria-labelledby="event-managers-title">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="event-managers-title" class="card-title">Co-gestionnaires</h2>
                <span class="text-xs text-slate-500">{{ \App\Models\Event::MAX_MANAGERS }} au plus</span>
            </div>
            <p class="mt-1 text-sm text-slate-500">Ils modifient la page, publient des témoignages et lancent le direct de l'événement. Seuls l'organisateur et les administrateurs les choisissent et peuvent supprimer l'événement.</p>

            @if($coManagers->isEmpty())
            <p class="mt-4 text-sm text-slate-500">Aucun co-gestionnaire pour le moment.</p>
            @else
            <ul class="mt-3 divide-y divide-slate-100">
                @foreach($coManagers as $coManager)
                @php $isMe = $authUser->id === $coManager->id; @endphp
                <li class="flex items-center gap-3 py-2.5">
                    @include('components.avatar', ['user' => $coManager, 'size' => 'sm'])
                    <a href="{{ route('profiles.show', $coManager->id) }}" class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-900 hover:underline">{{ $coManager->display_name }}@if($isMe) <span class="font-normal text-slate-500">(vous)</span>@endif</a>
                    @if($administrator || $isMe)
                    <form id="manager-remove-{{ $coManager->id }}" method="POST" action="{{ route('events.managers.destroy', [$event->id, $coManager->id]) }}" data-loading-label="Retrait…">
                        @csrf @method('DELETE')
                        <button type="button" class="btn-ghost btn-sm shrink-0"
                                onclick="openConfirmModal('manager-remove-{{ $coManager->id }}', {{ $isMe ? "'Vous ne pourrez plus gérer cet événement.'" : Js::from($coManager->display_name . ' ne pourra plus gérer cet événement.') }}, '{{ $isMe ? 'Ne plus gérer cet événement' : 'Retirer le co-gestionnaire' }}', '{{ $isMe ? 'Me retirer' : 'Retirer' }}', 'fa-user-minus')">
                            {{ $isMe ? 'Me retirer' : 'Retirer' }}
                        </button>
                    </form>
                    @endif
                </li>
                @endforeach
            </ul>
            @endif

            @if($administrator)
            <div class="mt-4 border-t border-slate-100 pt-4">
                @if($isFull)
                <p class="text-sm text-slate-500">Deux co-gestionnaires au plus : retirez-en un pour en ajouter un autre.</p>
                @else
                @include('components.user-picker', [
                    'id'      => 'event-managers',
                    'label'   => 'Ajouter un co-gestionnaire',
                    'addUrl'  => route('events.managers.store', $event->id),
                    'exclude' => [$event->organizer_id, $authUser->id, ...$coManagers->pluck('id')],
                    'results' => $pickerResults,
                    'anchor'  => 'co-gestionnaires',
                ])
                @endif
            </div>
            @endif
        </section>
        @endif
    </aside>
</div>

{{-- Inviter à témoigner (événement en cours ou terminé) --}}
@if($event->status === \App\Enums\EventStatus::Published && $event->phase() !== 'upcoming')
<x-encouragement class="mt-8" message="Vous avez vécu quelque chose pendant cet événement ? Témoignez : votre histoire peut fortifier quelqu'un." />
@endif

{{-- ── Modale « Enregistrer comme témoignage » (gestionnaires) ───────── --}}
@if($manager)
<div id="promote-modal" data-modal class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="promote-modal-title" hidden>
    <div class="absolute inset-0 bg-slate-900/50" data-modal-close></div>
    <form method="POST" action="" id="promote-form" class="card relative w-full max-w-lg shadow-xl" data-loading-label="Enregistrement…">
        @csrf
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
            <h2 id="promote-modal-title" class="text-base font-semibold text-primary-600">Enregistrer comme témoignage</h2>
            <button type="button" class="btn-ghost btn-sm" data-modal-close aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="space-y-4 px-5 py-4">
            <p class="text-sm text-slate-600">Le commentaire devient un témoignage officiel de l'événement. Son auteur reste l'auteur du témoignage.
                {{ $authUser->canModerate() ? 'Il sera publié tout de suite.' : 'Il sera publié après relecture par la modération.' }}</p>
            <div>
                <label for="promote-title" class="form-label">Titre (facultatif)</label>
                <input id="promote-title" type="text" name="title" maxlength="200" class="form-input" placeholder="Par défaut : titre de l'événement et début du message">
            </div>
            <div>
                <label for="promote-category" class="form-label">Catégorie</label>
                <select id="promote-category" name="category" class="form-input">
                    @foreach($categories as $cat)
                    <option value="{{ $cat->slug }}" @selected($cat->slug === 'autre')>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <button type="button" class="btn-secondary" data-modal-close>Annuler</button>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-award" aria-hidden="true"></i>Enregistrer</button>
        </div>
    </form>
</div>
@endif
@endsection

@if($event->directionsUrl())
@if($event->hasCoordinates())
@include('events.partials.leaflet')
@endif
@push('scripts')
<script>
// Page d'un événement : mini-carte non interactive du lieu et copie de l'adresse.
(function () {
    const el = document.querySelector('[data-event-map-view]');
    if (el && window.L) {
        const pos = [parseFloat(el.dataset.lat), parseFloat(el.dataset.lng)];
        const map = L.map(el, {
            dragging: false, touchZoom: false, scrollWheelZoom: false, doubleClickZoom: false,
            boxZoom: false, keyboard: false, zoomControl: false,
        }).setView(pos, 15);
        window.testiOsmLayer().addTo(map);
        L.marker(pos, { title: "Lieu de l'événement", keyboard: false }).addTo(map);
    }
    const copy = document.querySelector('[data-copy-address]');
    if (copy) {
        copy.addEventListener('click', async () => {
            const label = copy.querySelector('[data-copy-label]');
            try {
                await navigator.clipboard.writeText(copy.dataset.copyAddress);
                label.textContent = 'Adresse copiée';
            } catch (e) {
                label.textContent = 'Copie impossible';
            }
            setTimeout(() => { label.textContent = "Copier l'adresse"; }, 2500);
        });
    }
})();
</script>
@endpush
@endif

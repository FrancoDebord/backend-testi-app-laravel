@extends('layouts.app')
@section('title', $event->title)
@use('App\Http\Controllers\Web\EventController', 'EventPage')
@use('App\Models\EventParticipation')
@php
    $authUser    = Auth::user();
    $header      = $event->title;
    $subheader   = $event->type->label() . ' · ' . EventPage::dateRange($event);
    $breadcrumbs = [
        ['label' => __('layout.nav.home'), 'url' => route('home')],
        ['label' => __('layout.nav.events'), 'url' => route('events.index')],
        ['label' => Str::limit($event->title, 40)],
    ];
    $images   = $event->images;
    $place    = EventPage::place($event);
    $guests   = collect($event->guests ?? [])->filter(fn ($g) => filled($g['name'] ?? null));
    $phaseBadge = match (true) {
        $event->isCancelled()           => 'badge-rejected',
        $event->isDateTbd()             => 'badge-orange',
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
<div class="card mb-6 flex flex-wrap items-center gap-2 p-3 sm:p-4" role="group" aria-label="{{ __('events.show.manage_label') }}">
    <p class="section-title mr-2 w-full sm:w-auto">{{ __('events.show.manage') }}</p>
    <a href="{{ route('events.edit', $event->id) }}" class="btn-secondary btn-sm"><i class="fa-solid fa-pen" aria-hidden="true"></i>{{ __('events.form.breadcrumb_edit') }}</a>
    @if($activeLive && $activeLive->isHost($authUser))
        <a href="{{ route('lives.studio', $activeLive->id) }}" class="btn-secondary btn-sm"><i class="fa-solid fa-video" aria-hidden="true"></i>{{ __('events.show.resume_studio') }}</a>
    @elseif($event->status === \App\Enums\EventStatus::Published && !$activeLive)
        <a href="{{ route('lives.create', ['event' => $event->id]) }}" class="btn-secondary btn-sm"><i class="fa-solid fa-video" aria-hidden="true"></i>{{ __('layout.nav.start_live') }}</a>
    @endif
    <a href="{{ route('publish', ['event' => $event->id]) }}" class="btn-secondary btn-sm"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>{{ __('layout.nav.publish') }}</a>
    <a href="{{ route('events.participants', $event->id) }}" class="btn-secondary btn-sm"><i class="fa-solid fa-users" aria-hidden="true"></i>{{ __('events.participants.title') }}</a>
    <a href="#co-gestionnaires" class="btn-secondary btn-sm"><i class="fa-solid fa-user-gear" aria-hidden="true"></i>{{ __('events.show.co_managers') }}</a>
    @if($event->isOpen() && $event->phase() !== 'past')
    <button type="button" class="btn-secondary btn-sm" data-modal-open="postpone-modal"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i>{{ __('events.postpone_modal.submit') }}</button>
    <button type="button" class="btn-secondary btn-sm text-error-700" data-modal-open="cancel-modal"><i class="fa-solid fa-ban" aria-hidden="true"></i>{{ __('events.cancel_modal.title') }}</button>
    @endif
    @if($administrator)
    <form id="event-delete-form" method="POST" action="{{ route('events.destroy', $event->id) }}" class="sm:ml-auto" data-loading-label="{{ __('events.show.deleting') }}">
        @csrf @method('DELETE')
        <button type="button" class="btn-ghost btn-sm text-error-500"
                onclick="openConfirmModal('event-delete-form', {{ Js::from(__('events.show.delete_text')) }}, {{ Js::from(__('events.show.delete_title')) }}, {{ Js::from(__('events.show.delete')) }}, 'fa-trash')">
            <i class="fa-solid fa-trash" aria-hidden="true"></i>{{ __('events.show.delete') }}
        </button>
    </form>
    @endif
    @if($event->status === \App\Enums\EventStatus::Draft)
    <p class="w-full text-xs text-slate-500"><i class="fa-solid fa-eye-slash mr-1" aria-hidden="true"></i>{{ __('events.show.draft_notice') }}</p>
    @endif
</div>
@endif

@if($event->isHidden())
<div class="alert-warning mb-6" role="status">
    <i class="fa-solid fa-eye-slash mt-0.5"></i>
    <p><strong>{{ __('events.show.hidden_title') }}</strong> {{ __('events.show.hidden_text') }} {{ $event->hidden_reason }}</p>
</div>
@endif

@if($event->isCancelled())
<div class="alert-error mb-6" role="status">
    <i class="fa-solid fa-ban mt-0.5"></i>
    <p><strong>{{ __('events.show.cancelled_title') }}</strong> {{ __('events.show.cancelled_text') }}@if(filled($event->status_reason)) {{ __('events.show.reason', ['reason' => $event->status_reason]) }}@endif</p>
</div>
@elseif($event->isPostponed())
<div class="alert-warning mb-6" role="status">
    <i class="fa-solid fa-calendar-days mt-0.5"></i>
    <p>
        <strong>{{ $event->isDateTbd() ? __('events.show.postponed_tbd_title') : __('events.show.postponed_title') }}</strong>
        @if($event->original_starts_at){{ __('events.card.original_date') }} <del>{{ EventPage::dateRange($event, original: true) }}</del>.@endif
        @unless($event->isDateTbd()) {{ __('events.show.new_date') }} <strong>{{ EventPage::dateRange($event) }}</strong>.@endunless
        @if(filled($event->status_reason)) {{ __('events.show.reason', ['reason' => $event->status_reason]) }}@endif
        {{ __('events.show.postponed_kept') }}
    </p>
</div>
@endif

@if($event->isUnconfirmed())
<div class="alert-warning mb-6" role="status">
    <i class="fa-solid fa-circle-question mt-0.5"></i>
    <p><strong>{{ __('events.show.unconfirmed_title') }}</strong> {{ __('events.show.unconfirmed_text') }}</p>
</div>
@endif

<div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">

    {{-- ── Colonne principale ─────────────────────────────────────────── --}}
    <div class="min-w-0 space-y-6">

        {{-- Images (carrousel) --}}
        @if($images->count() > 1)
        <div class="relative overflow-hidden rounded-xl bg-slate-900" data-carousel role="region" aria-roledescription="{{ __('events.show.carousel') }}"
             aria-label="{{ __('events.show.images') }}" tabindex="0">
            <div class="flex aspect-video snap-x snap-mandatory overflow-x-auto scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" data-carousel-track>
                @foreach($images as $i => $image)
                <div class="h-full w-full shrink-0 snap-center" role="group" aria-roledescription="{{ __('events.show.slide') }}" aria-label="{{ __('events.show.slide_label', ['number' => $i + 1, 'total' => $images->count()]) }}" data-carousel-slide>
                    <img src="{{ $image->url }}" alt="" class="h-full w-full object-cover" @if($i > 0) loading="lazy" @endif>
                </div>
                @endforeach
            </div>
            <button type="button" class="absolute top-1/2 left-2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-primary-700 shadow-soft hover:bg-white focus-visible:outline-2 focus-visible:outline-white"
                    data-carousel-prev aria-label="{{ __('events.show.previous_image') }}"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
            <button type="button" class="absolute top-1/2 right-2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-primary-700 shadow-soft hover:bg-white focus-visible:outline-2 focus-visible:outline-white"
                    data-carousel-next aria-label="{{ __('events.show.next_image') }}"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
            <div class="absolute inset-x-0 bottom-2 flex justify-center gap-1.5">
                @foreach($images as $i => $image)
                <button type="button" class="h-2.5 w-2.5 rounded-full bg-white/50 aria-[current=true]:bg-white focus-visible:outline-2 focus-visible:outline-white"
                        data-carousel-dot="{{ $i }}" aria-label="{{ __('events.show.show_image', ['number' => $i + 1]) }}" aria-current="{{ $i === 0 ? 'true' : 'false' }}"></button>
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
                    <span class="badge-draft">{{ __('events.form.status.draft') }}</span>
                @endif
                @if($event->isPostponed() && !$event->isDateTbd())<span class="badge-orange">{{ __('events.badges.postponed') }}</span>@endif
                @if($event->isUnconfirmed())<span class="badge-rejected">{{ __('events.badges.unconfirmed') }}</span>@endif
                @if($live && $live->isOnAir())<span class="badge-live">{{ __('events.badges.on_air') }}</span>@endif
            </div>
            <h2 id="event-about" class="card-title mt-4">{{ __('events.show.about') }}</h2>
            @if(filled($event->description))
                <p class="mt-2 text-sm leading-relaxed break-words whitespace-pre-line text-slate-700">{{ $event->description }}</p>
            @else
                <p class="mt-2 text-sm text-slate-500">{{ __('events.show.no_description') }}</p>
            @endif

            @if($guests->isNotEmpty())
            <h3 class="card-title mt-6">{{ __('events.form.guests') }}</h3>
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

        {{-- Direct YouTube (docs/fonctionnalites/evenements.md) : lecteur sans cookie publicitaire avant la lecture --}}
        @if($youtubeLive = $event->youtubeLive())
        <section class="card p-5 sm:p-6" aria-labelledby="event-youtube-live" data-event-youtube-live>
            <h2 id="event-youtube-live" class="card-title flex items-center gap-2">
                <i class="fa-brands fa-youtube text-error-500" aria-hidden="true"></i>{{ __('events.show.youtube_live_title') }}
            </h2>
            <div class="relative mt-4 aspect-video overflow-hidden rounded-xl bg-black">
                <iframe src="{{ $youtubeLive['embedUrl'] }}" title="{{ __('events.show.youtube_live_title') }} — {{ $event->title }}" class="absolute inset-0 h-full w-full"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        referrerpolicy="strict-origin-when-cross-origin" allowfullscreen loading="lazy"></iframe>
            </div>
            <p class="mt-2 flex items-center gap-2 text-xs text-slate-500">
                <i class="fa-brands fa-youtube text-error-500" aria-hidden="true"></i>{{ __('events.show.youtube_live_hosted') }} ·
                <a href="{{ $youtubeLive['url'] }}" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:underline">{{ __('events.show.youtube_live_open') }}</a>
            </p>
        </section>
        @endif

        {{-- Témoignages officiels / commentaires des participants --}}
        <section id="temoignages" class="scroll-mt-20" aria-label="{{ __('events.show.testimonies_and_comments') }}">
            <nav aria-label="{{ __('events.show.sections') }}" class="mb-4 border-b border-slate-200">
                <ul class="-mb-px flex gap-5 overflow-x-auto">
                    <li><a href="{{ $sectionUrl('temoignages') }}" class="{{ $section === 'temoignages' ? 'tab-active' : 'tab' }}" @if($section === 'temoignages') aria-current="page" @endif>{{ __('events.show.testimonies') }} <span class="text-xs text-slate-500">{{ $testimonies->total() }}</span></a></li>
                    <li><a href="{{ $sectionUrl('commentaires') }}" class="{{ $section === 'commentaires' ? 'tab-active' : 'tab' }}" @if($section === 'commentaires') aria-current="page" @endif>{{ __('events.show.comments') }} <span class="text-xs text-slate-500">{{ $comments->total() }}</span></a></li>
                </ul>
            </nav>

            @if($section === 'temoignages')
                @if($testimonies->isEmpty())
                    @include('components.empty-state', [
                        'title'       => __('events.show.no_testimonies'),
                        'text'        => __('events.show.no_testimonies_text'),
                        'actionUrl'   => $sectionUrl('commentaires'),
                        'actionLabel' => __('events.show.view_comments'),
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
                <form method="POST" action="{{ route('events.comments.store', $event->id) }}" class="card mb-6 space-y-3 p-4 sm:p-5" data-loading-label="{{ __('events.sending') }}">
                    @csrf
                    <label for="event-comment" class="form-label">{{ __('events.show.comment_label') }}</label>
                    <textarea id="event-comment" name="body" rows="4" required minlength="2" maxlength="3000" class="form-input"
                              placeholder="{{ __('events.show.comment_placeholder') }}" data-mention-input @error('body') aria-invalid="true" @enderror>{{ old('body') }}</textarea>
                    @include('components.mention-input')
                    @error('body')<p class="form-error">{{ $message }}</p>@enderror
                    @if(!$event->comments_enabled)
                    <p class="form-hint">{{ __('events.show.comments_closed_manager') }}</p>
                    @endif
                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>{{ __('events.show.publish') }}</button>
                    </div>
                </form>
                @elseif(!$authUser)
                <div class="alert-info mb-6">
                    <i class="fa-solid fa-circle-info mt-0.5"></i>
                    <p><a href="{{ route('login') }}" class="font-semibold underline">{{ __('events.show.login') }}</a> {{ __('events.show.login_to_comment') }}</p>
                </div>
                @elseif(!$event->comments_enabled)
                <p class="mb-6 text-sm text-slate-500"><i class="fa-solid fa-lock mr-1" aria-hidden="true"></i>{{ __('events.errors.comments_closed') }}</p>
                @endif

                {{-- Liste --}}
                @if($comments->isEmpty())
                    @include('components.empty-state', [
                        'title' => __('events.show.no_comments'),
                        'text'  => __('events.show.no_comments_text'),
                    ])
                @else
                <ul class="card mb-6 divide-y divide-slate-100">
                    {{-- « J'aime » de la personne lus en une requête (docs/fonctionnalites/jaime-commentaires.md) --}}
                    @php app(\App\Services\CommentLikes::class)->preload('event_comment', $comments, $authUser); @endphp
                    @foreach($comments as $comment)
                    @php
                        $canDelete = $authUser && ($comment->user_id === $authUser->id || $manager || $authUser->isAdmin());
                    @endphp
                    <li class="flex gap-3 px-4 py-4 sm:px-5" id="commentaire-{{ $comment->id }}">
                        @include('components.avatar', ['user' => $comment->user, 'size' => 'sm'])
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                <span class="flex min-w-0 items-center gap-1.5 font-semibold text-slate-900">
                                    <span class="truncate">{{ $comment->user?->display_name ?? __('events.deleted_account') }}</span>
                                    @include('components.verified-badge', ['user' => $comment->user])
                                </span>
                                <time class="text-xs text-slate-500" datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->diffForHumans() }}</time>
                                @if($comment->testimony)
                                <a href="{{ route('testimonies.show', $comment->testimony_id) }}" class="badge-validated hover:underline">{{ __('events.show.saved_as_testimony') }}</a>
                                @endif
                            </p>
                            <p class="mt-1 text-sm break-words whitespace-pre-line text-slate-700">{{ \App\Services\Mentions::html($comment->body) }}</p>

                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                @include('components.comment-like', ['type' => 'event_comment', 'comment' => $comment, 'url' => route('events.comments.like', [$event->id, $comment->id]), 'class' => '-ml-3'])
                                @if($manager && !$comment->testimony)
                                <button type="button" class="btn-ghost btn-sm" data-modal-open="promote-modal"
                                        data-promote-url="{{ route('events.comments.promote', [$event->id, $comment->id]) }}">
                                    <i class="fa-solid fa-award" aria-hidden="true"></i>{{ __('events.show.promote') }}
                                </button>
                                @endif
                                @if($canDelete)
                                <form id="comment-delete-{{ $comment->id }}" method="POST" action="{{ route('events.comments.destroy', [$event->id, $comment->id]) }}" data-loading-label="{{ __('events.show.deleting') }}">
                                    @csrf @method('DELETE')
                                    <button type="button" class="btn-ghost btn-sm"
                                            onclick="openConfirmModal('comment-delete-{{ $comment->id }}', {{ Js::from(__('events.show.comment_delete_text')) }}, {{ Js::from(__('events.show.comment_delete_title')) }}, {{ Js::from(__('events.show.delete')) }}, 'fa-trash')">
                                        <i class="fa-solid fa-trash" aria-hidden="true"></i>{{ __('events.show.delete') }}
                                    </button>
                                </form>
                                @endif
                            </div>
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
            <p class="mt-1 text-sm text-slate-500">{{ __('events.show.event_live') }}</p>
            <a href="{{ $liveUrl }}" class="btn-cta mt-4 w-full">
                <i class="fa-solid fa-tower-broadcast" aria-hidden="true"></i>{{ $live->isHost($authUser) ? __('events.show.open_studio') : __('events.show.watch_live') }}
            </a>
        </section>
        @endif

        {{-- Participation --}}
        <section class="card p-5" aria-labelledby="event-participate-title">
            <h2 id="event-participate-title" class="card-title">{{ __('events.show.participate') }}</h2>
            <p class="mt-1 text-sm text-slate-600">
                <span class="font-semibold text-slate-900">{{ \App\Support\Locales::number($event->going_count) }}</span> {{ trans_choice('events.show.going_verb', $event->going_count) }}
                · {{ trans_choice('events.show.not_going_count', $event->not_going_count, ['count' => \App\Support\Locales::number($event->not_going_count)]) }}
            </p>

            @if(!$event->acceptsParticipation())
                <p class="mt-3 text-sm text-slate-500">
                    @if($event->isCancelled()) {{ __('events.errors.cancelled') }}
                    @elseif($event->status === \App\Enums\EventStatus::Draft) {{ __('events.show.publish_to_answer') }}
                    @else {{ __('events.errors.finished') }}
                    @endif
                </p>
            @elseif(!$authUser)
                <a href="{{ route('login') }}" class="btn-secondary mt-4 w-full"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>{{ __('events.show.login_to_answer') }}</a>
            @else
                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-1">
                    @foreach([EventParticipation::GOING => [__('events.going'), 'fa-circle-check'], EventParticipation::NOT_GOING => [__('events.not_going'), 'fa-circle-xmark']] as $value => [$label, $icon])
                    @php $chosen = $participation === $value; @endphp
                    <form method="POST" action="{{ route('events.participate', $event->id) }}" data-loading-label="{{ __('events.saving') }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ $value }}">
                        <button type="submit" class="{{ $chosen ? 'btn-primary' : 'btn-secondary' }} w-full" aria-pressed="{{ $chosen ? 'true' : 'false' }}">
                            <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>{{ $label }}
                        </button>
                    </form>
                    @endforeach
                </div>
                @if($participation)
                <form method="POST" action="{{ route('events.participation.cancel', $event->id) }}" class="mt-2 text-center" data-loading-label="{{ __('events.form.removing') }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-ghost btn-sm">{{ __('events.show.remove_answer') }}</button>
                </form>
                @endif
            @endif
        </section>

        {{-- Sondage d'après l'événement : « Votre avis » (docs/fonctionnalites/evenements.md) --}}
        @if($event->acceptsReviews() || $event->surveyTotal() > 0)
        @include('events.partials.survey', ['event' => $event, 'myReview' => $myReview, 'canReview' => $canReview, 'manager' => $manager])
        @endif

        {{-- Requêtes et sessions de prière (docs/fonctionnalites/requetes-de-priere.md) --}}
        @if($event->status === \App\Enums\EventStatus::Published)
        @include('prayer.partials.event-section', ['event' => $event])
        @endif

        {{-- Infos pratiques --}}
        <section class="card p-5" aria-labelledby="event-info-title">
            <h2 id="event-info-title" class="card-title">{{ __('events.show.info') }}</h2>
            <dl class="mt-3 space-y-3 text-sm">
                <div class="flex gap-3">
                    <dt class="w-5 shrink-0 text-center text-slate-400"><i class="fa-regular fa-calendar" aria-hidden="true"></i><span class="sr-only">{{ __('events.show.dates') }}</span></dt>
                    <dd class="min-w-0 text-slate-700">
                        {{ EventPage::dateRange($event) }}
                        @if($event->isPostponed() && $event->original_starts_at)
                        <del class="block text-xs text-slate-400"><span class="sr-only">{{ __('events.card.original_date') }} </span>{{ EventPage::dateRange($event, original: true) }}</del>
                        @endif
                    </dd>
                </div>
                @if($place)
                <div class="flex gap-3">
                    <dt class="w-5 shrink-0 text-center text-slate-400"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span class="sr-only">{{ __('events.form.location') }}</span></dt>
                    <dd class="min-w-0 break-words text-slate-700">{{ $place }}</dd>
                </div>
                @endif
                @if(filled($event->address))
                <div class="flex gap-3">
                    <dt class="w-5 shrink-0 text-center text-slate-400"><i class="fa-solid fa-signs-post" aria-hidden="true"></i><span class="sr-only">{{ __('events.form.address') }}</span></dt>
                    <dd class="min-w-0 break-words text-slate-700">{{ $event->address }}</dd>
                </div>
                @endif
                <div class="flex gap-3">
                    <dt class="w-5 shrink-0 text-center text-slate-400"><i class="fa-solid fa-user-tie" aria-hidden="true"></i><span class="sr-only">{{ __('events.form.organizer') }}</span></dt>
                    <dd class="flex min-w-0 items-center gap-1.5">
                        @if($event->organizer)
                        <a href="{{ route('profiles.show', $event->organizer_id) }}" class="truncate font-semibold text-primary-600 hover:underline">{{ $event->organizer->display_name }}</a>
                        @include('components.verified-badge', ['user' => $event->organizer])
                        @endif
                        @if($reliability['reliable'])
                        <span class="badge-validated shrink-0" title="{{ __('events.show.reliable_title', ['count' => $reliability['confirmed']]) }}"><i class="fa-solid fa-shield-heart" aria-hidden="true"></i>{{ __('events.show.reliable') }}</span>
                        @endif
                    </dd>
                </div>
            </dl>

            {{-- Lieu : mini-carte OpenStreetMap et itinéraire (docs/fonctionnalites/evenements.md) --}}
            @if($directionsUrl = $event->directionsUrl())
            @if($event->hasCoordinates())
            <div class="event-map mt-4 h-48 w-full overflow-hidden rounded-lg border border-slate-200 bg-slate-100"
                 data-event-map-view data-lat="{{ $event->latitude }}" data-lng="{{ $event->longitude }}"
                 role="img" aria-label="{{ __('events.show.map_label') }}"></div>
            @endif
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ $directionsUrl }}" target="_blank" rel="noopener" class="btn-primary btn-sm">
                    <i class="fa-solid fa-diamond-turn-right" aria-hidden="true"></i>{{ __('events.show.directions') }}
                </a>
                <button type="button" class="btn-secondary btn-sm" data-copy-address="{{ $event->fullAddress() ?: \App\Models\Event::formatCoordinate($event->latitude) . ', ' . \App\Models\Event::formatCoordinate($event->longitude) }}">
                    <i class="fa-regular fa-copy" aria-hidden="true"></i><span data-copy-label>{{ __('events.show.copy_address') }}</span>
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
                <h2 id="event-managers-title" class="card-title">{{ __('events.show.co_managers') }}</h2>
                <span class="text-xs text-slate-500">{{ __('events.show.managers_max', ['count' => \App\Models\Event::MAX_MANAGERS]) }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-500">{{ __('events.show.managers_help') }}</p>

            @if($coManagers->isEmpty())
            <p class="mt-4 text-sm text-slate-500">{{ __('events.show.no_managers') }}</p>
            @else
            <ul class="mt-3 divide-y divide-slate-100">
                @foreach($coManagers as $coManager)
                @php $isMe = $authUser->id === $coManager->id; @endphp
                <li class="flex items-center gap-3 py-2.5">
                    @include('components.avatar', ['user' => $coManager, 'size' => 'sm'])
                    <a href="{{ route('profiles.show', $coManager->id) }}" class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-900 hover:underline">{{ $coManager->display_name }}@if($isMe) <span class="font-normal text-slate-500">{{ __('events.show.you') }}</span>@endif</a>
                    @if($administrator || $isMe)
                    <form id="manager-remove-{{ $coManager->id }}" method="POST" action="{{ route('events.managers.destroy', [$event->id, $coManager->id]) }}" data-loading-label="{{ __('events.form.removing') }}">
                        @csrf @method('DELETE')
                        <button type="button" class="btn-ghost btn-sm shrink-0"
                                onclick="openConfirmModal('manager-remove-{{ $coManager->id }}', {{ Js::from($isMe ? __('events.show.manager_self_text') : __('events.show.manager_remove_text', ['name' => $coManager->display_name])) }}, {{ Js::from($isMe ? __('events.show.manager_self_title') : __('events.show.manager_remove_title')) }}, {{ Js::from($isMe ? __('events.show.manager_self_confirm') : __('events.form.remove')) }}, 'fa-user-minus')">
                            {{ $isMe ? __('events.show.manager_self_confirm') : __('events.form.remove') }}
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
                <p class="text-sm text-slate-500">{{ __('events.show.managers_full') }}</p>
                @else
                @include('components.user-picker', [
                    'id'      => 'event-managers',
                    'label'   => __('events.show.add_manager'),
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

        {{-- Modération (modérateurs et administrateurs) --}}
        @if($moderator)
        @include('events.partials.moderation-card', ['event' => $event, 'reliability' => $reliability])
        @endif

        {{-- Signaler (comptes connectés, pas les gestionnaires) --}}
        @if($authUser && !$manager && $event->status !== \App\Enums\EventStatus::Draft)
        <details class="card p-5" @if($errors->has('reason')) open @endif>
            <summary class="cursor-pointer text-sm font-medium text-slate-700"><i class="fa-regular fa-flag mr-1" aria-hidden="true"></i>{{ __('events.show.report') }}</summary>
            @if($hasReported)
            <p class="mt-3 text-sm text-slate-600">{{ __('events.show.reported') }}</p>
            @else
            <form method="POST" action="{{ route('events.report', $event->id) }}" class="mt-3 space-y-3" data-loading-label="{{ __('events.sending') }}">
                @csrf
                <label for="report-reason" class="form-label">{{ __('events.show.report_reason') }}</label>
                <select id="report-reason" name="reason" required class="form-input">
                    @foreach($reportReasons as $value => $label)
                    <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('reason')<p class="form-error">{{ $message }}</p>@enderror
                <label for="report-comment" class="sr-only">{{ __('events.show.report_details') }}</label>
                <textarea id="report-comment" name="comment" rows="2" maxlength="500" class="form-input" placeholder="{{ __('events.show.report_details_placeholder') }}">{{ old('comment') }}</textarea>
                <button type="submit" class="btn-secondary w-full">{{ __('events.show.report_submit') }}</button>
            </form>
            @endif
        </details>
        @endif
    </aside>
</div>

{{-- Avis des participants (sondage d'après l'événement) --}}
@if($reviews && $reviews->isNotEmpty())
<section class="mt-8" aria-labelledby="event-reviews-title" id="avis">
    <h2 id="event-reviews-title" class="card-title mb-3">{{ __('events.show.reviews') }}</h2>
    <ul class="card divide-y divide-slate-100">
        @foreach($reviews as $review)
        <li class="flex gap-3 px-4 py-4 sm:px-5">
            @include('components.avatar', ['user' => $review->user, 'size' => 'sm'])
            <div class="min-w-0 flex-1">
                <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                    <span class="truncate font-semibold text-slate-900">{{ $review->user?->display_name ?? __('events.deleted_account') }}</span>
                    @if($review->rating)
                    <span class="text-amber-500" role="img" aria-label="{{ __('events.show.rating_label', ['rating' => $review->rating]) }}">@for($i = 1; $i <= 5; $i++)<i class="fa-{{ $i <= $review->rating ? 'solid' : 'regular' }} fa-star text-xs" aria-hidden="true"></i>@endfor</span>
                    @endif
                    <span class="{{ $review->attended === 'yes' ? 'badge-validated' : ($review->attended === 'no' ? 'badge-rejected' : 'badge-neutral') }}">{{ \App\Models\EventReview::attendedLabels()[$review->attended] ?? $review->attended }}</span>
                    <time class="text-xs text-slate-500" datetime="{{ $review->updated_at?->toIso8601String() }}">{{ $review->updated_at?->diffForHumans() }}</time>
                </p>
                @if($review->body)<p class="mt-1 text-sm break-words whitespace-pre-line text-slate-700">{{ $review->body }}</p>@endif
            </div>
        </li>
        @endforeach
    </ul>
    <div class="mt-4">{{ $reviews->fragment('avis')->links() }}</div>
</section>
@endif

{{-- Inviter à témoigner (événement en cours ou terminé) --}}
@if($event->status === \App\Enums\EventStatus::Published && $event->phase() !== 'upcoming')
<x-encouragement class="mt-8" :message="__('events.show.encouragement')" />
@endif

{{-- ── Modales « Reporter » et « Annuler l'événement » (gestionnaires) ── --}}
@if($manager && $event->isOpen() && $event->phase() !== 'past')
@include('events.partials.status-modals', ['event' => $event])
@endif

{{-- ── Modale « Enregistrer comme témoignage » (gestionnaires) ───────── --}}
@if($manager)
<div id="promote-modal" data-modal class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="promote-modal-title" hidden>
    <div class="absolute inset-0 bg-slate-900/50" data-modal-close></div>
    <form method="POST" action="" id="promote-form" class="card relative w-full max-w-lg shadow-xl" data-loading-label="{{ __('events.saving') }}">
        @csrf
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
            <h2 id="promote-modal-title" class="text-base font-semibold text-primary-600">{{ __('events.show.promote') }}</h2>
            <button type="button" class="btn-ghost btn-sm" data-modal-close aria-label="{{ __('events.close') }}"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="space-y-4 px-5 py-4">
            <p class="text-sm text-slate-600">{{ __('events.show.promote_intro') }}
                {{ $authUser->canModerate() ? __('events.show.promote_now') : __('events.show.promote_review') }}</p>
            <div>
                <label for="promote-title" class="form-label">{{ __('events.show.promote_title') }}</label>
                <input id="promote-title" type="text" name="title" maxlength="200" class="form-input" placeholder="{{ __('events.show.promote_title_placeholder') }}">
            </div>
            <div>
                <label for="promote-category" class="form-label">{{ __('events.show.category') }}</label>
                <select id="promote-category" name="category" class="form-input">
                    @foreach($categories as $cat)
                    <option value="{{ $cat->slug }}" @selected($cat->slug === 'autre')>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <button type="button" class="btn-secondary" data-modal-close>{{ __('events.form.cancel') }}</button>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-award" aria-hidden="true"></i>{{ __('events.form.save') }}</button>
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
    const T  = @js(__('events.show.js'));
    const el = document.querySelector('[data-event-map-view]');
    if (el && window.L) {
        const pos = [parseFloat(el.dataset.lat), parseFloat(el.dataset.lng)];
        const map = L.map(el, {
            dragging: false, touchZoom: false, scrollWheelZoom: false, doubleClickZoom: false,
            boxZoom: false, keyboard: false, zoomControl: false,
        }).setView(pos, 15);
        window.testiOsmLayer().addTo(map);
        L.marker(pos, { title: T.marker, keyboard: false }).addTo(map);
    }
    const copy = document.querySelector('[data-copy-address]');
    if (copy) {
        copy.addEventListener('click', async () => {
            const label = copy.querySelector('[data-copy-label]');
            try {
                await navigator.clipboard.writeText(copy.dataset.copyAddress);
                label.textContent = T.copied;
            } catch (e) {
                label.textContent = T.copy_failed;
            }
            setTimeout(() => { label.textContent = T.copy; }, 2500);
        });
    }
})();
</script>
@endpush
@endif

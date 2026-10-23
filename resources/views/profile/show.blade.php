@extends('layouts.app')
@section('title', $profile->display_name)
@php
    // Carte d'en-tête (bandeau, avatar, identité, présentation, statistiques), « Mon espace » pour soi,
    // puis les sections. Bouton Suivre : components/follow-button (le nombre d'abonnés se met à jour sans recharger).
    // docs/fonctionnalites/profil.md
    $stats = [
        [$profile->publishedTestimonyCount(), 'testimonies', '#temoignages'],
        [$profile->follower_count,  'followers', $isOwner ? route('profile.followers') : null],
        [$profile->following_count, 'following', $isOwner ? route('profile.following') : null],
    ];
    $verification = $isOwner && !$profile->isOrganization() ? match($profile->verification_status) {
        \App\Enums\VerificationStatus::Verified => __('profile.show.verification.verified'),
        \App\Enums\VerificationStatus::Pending  => __('profile.show.verification.pending'),
        \App\Enums\VerificationStatus::Rejected => __('profile.show.verification.rejected'),
        default => __('profile.show.verification.none'),
    } : null;
    $shortcuts = $isOwner ? array_values(array_filter([
        [route('testimonies.mine'), 'fa-list', __('profile.show.shortcuts.mine')],
        [route('profile.saved'), 'fa-bookmark', __('profile.show.shortcuts.saved')],
        [route('journal.index'), 'fa-book-open', __('profile.show.shortcuts.journal')],
        $verification ? [route('profile.verification'), 'fa-circle-check', $verification] : null,
        !$conversionTestimony && \App\Services\ConversionTestimonies::canPublish($profile) ? [route('publish', ['kind' => 'conversion']), 'fa-dove', __('conversion.share_yours')] : null,
        [route('profile.settings'), 'fa-gear', __('profile.show.shortcuts.settings')],
    ])) : [];
    $meta = array_values(array_filter([
        $profile->isOrganization() ? ['fa-building', trim(($profile->organization_type?->label() ?? __('profile.show.organization')) . ($profile->organization_city ? ' · ' . $profile->organization_city : ''))] : null,
        $profile->country ? ['fa-location-dot', $profile->country] : null,
        $profile->created_at ? ['fa-calendar', __('profile.show.member_since', ['date' => $profile->created_at->translatedFormat('F Y')])] : null,
    ]));
    // La coche reste accrochée au dernier mot du nom (jamais seule sur une ligne) ; un très long mot peut se couper (390 px).
    $nameHead   = Str::contains($profile->display_name, ' ') ? Str::beforeLast($profile->display_name, ' ') . ' ' : '';
    $nameTail   = Str::afterLast($profile->display_name, ' ');
    $tailNowrap = mb_strlen($nameTail) <= 20;
@endphp

@section('content')
<section class="card mb-6 overflow-hidden p-0" aria-label="{{ __('profile.show.aria') }}">
    {{-- Bandeau : photo de couverture (docs/fonctionnalites/photo-de-couverture.md), sinon bleu de la charte. --}}
    <div class="relative h-28 overflow-hidden bg-linear-to-br from-primary-600 to-primary-700 sm:h-40 lg:h-48" aria-hidden="true">
        @if($profile->cover_url)
        <img src="{{ $profile->cover_url }}" alt="" class="h-full w-full object-cover" loading="eager" decoding="async">
        @else
        <i class="fa-solid fa-star absolute top-5 right-6 text-3xl text-sun-400 sm:top-8 sm:right-10 sm:text-4xl"></i>
        @endif
    </div>

    <div class="px-4 pb-5 sm:px-6 sm:pb-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex min-w-0 items-end gap-4">
                <div class="relative z-10 -mt-10 shrink-0 rounded-full bg-white p-1 sm:-mt-12">
                    @include('components.avatar', ['user' => $profile, 'size' => 'xl'])
                </div>
                <div class="min-w-0 pb-1">
                    <h1 class="text-xl font-bold break-words text-primary-600 sm:text-2xl">{{ $nameHead }}<span class="{{ $tailNowrap ? 'whitespace-nowrap' : '' }}">{{ $nameTail }}@if($profile->isVerified())&nbsp;@include('components.verified-badge', ['user' => $profile])@endif</span></h1>
                    @if($meta)
                    <ul class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-500">
                        @foreach($meta as [$icon, $text])
                        <li class="flex min-w-0 items-center gap-1.5"><i class="fa-solid {{ $icon }} text-xs text-slate-400" aria-hidden="true"></i><span class="break-words">{{ $text }}</span></li>
                        @endforeach
                    </ul>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap gap-2 sm:shrink-0 sm:pb-1">
                @if($isOwner)
                    <a href="{{ route('profile.edit') }}" class="btn-secondary flex-1 sm:flex-none"><i class="fa-solid fa-pen" aria-hidden="true"></i>{{ __('profile.show.edit') }}</a>
                    <a href="{{ route('publish') }}" class="btn-cta flex-1 sm:flex-none"><i class="fa-solid fa-plus" aria-hidden="true"></i>{{ __('profile.show.publish') }}</a>
                @else
                    {{-- Suivre : docs/fonctionnalites/abonnements.md (personne non connectée : lien vers la connexion) --}}
                    @include('components.follow-button', ['user' => $profile, 'following' => $isFollowing, 'primary' => true])
                @endif
            </div>
        </div>

        @if($profile->bio)
        <p class="mt-4 max-w-3xl text-[15px] leading-relaxed break-words whitespace-pre-line text-slate-700">{{ $profile->bio }}</p>
        @elseif($isOwner)
        <p class="mt-4 text-sm text-slate-500">{{ __('profile.show.no_bio') }} <a href="{{ route('profile.edit') }}" class="font-semibold text-primary-600 hover:underline">{{ __('profile.show.add_bio') }}</a></p>
        @endif

        {{-- Statistiques : abonnés et abonnements cliquables pour soi. --}}
        <dl class="mt-5 grid grid-cols-3 divide-x divide-slate-200 rounded-lg border border-slate-200">
            @foreach($stats as [$count, $kind, $url])
            <div class="min-w-0">
                {{-- Libellé avant le nombre dans le code (dt puis dd), nombre au-dessus à l'écran. --}}
                @if($url)<a href="{{ $url }}" class="flex flex-col-reverse rounded-lg px-2 py-3 text-center hover:bg-primary-50 sm:px-4">@else<div class="flex flex-col-reverse px-2 py-3 text-center sm:px-4">@endif
                    <dt class="truncate text-xs text-slate-500 sm:text-sm">{{ trans_choice('profile.show.stats.' . $kind, $count ?? 0) }}</dt>
                    <dd class="text-lg font-bold text-slate-900 tabular-nums sm:text-xl" @if($kind === 'followers') data-follower-count="{{ $profile->id }}" @endif>{{ \App\Support\Locales::number($count ?? 0) }}</dd>
                @if($url)</a>@else</div>@endif
            </div>
            @endforeach
        </dl>

        {{-- Fiabilité de l'organisateur d'événements (modération) — docs/fonctionnalites/evenements.md --}}
        @if(auth()->user()?->canModerate() && ($eventReliability = \App\Models\Event::reliabilityOf($profile->id))['total'] > 0)
        <div class="mt-5 max-w-md">
            <p class="text-xs font-semibold text-slate-500 uppercase">{{ __('profile.show.reliability') }}</p>
            @include('events.partials.reliability', ['reliability' => $eventReliability])
        </div>
        @endif
    </div>
</section>

{{-- Suivi de la vérification, visible par l'organisation elle-même uniquement. --}}
@if($isOwner && $profile->isOrganization() && $profile->verification_status === \App\Enums\VerificationStatus::Pending)
<div class="alert-info mb-6" role="status">
    <i class="fa-solid fa-hourglass-half mt-0.5 text-slate-400" aria-hidden="true"></i>
    <p class="min-w-0"><span class="font-semibold text-slate-900">{{ __('profile.show.pending_title') }}</span> {{ __('profile.show.pending_text') }}</p>
</div>
@elseif($isOwner && $profile->isOrganization() && $profile->verification_status === \App\Enums\VerificationStatus::Rejected)
<div class="alert-warning mb-6" role="status">
    <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
    <p class="min-w-0 break-words"><span class="font-semibold">{{ __('profile.show.rejected_title') }}</span>@if($profile->verification_note){{ __('profile.show.rejected_note', ['note' => rtrim($profile->verification_note, " .")]) }}@endif. {{ __('profile.show.rejected_before') }} <a href="{{ route('profile.edit') }}" class="font-semibold underline">{{ __('profile.show.edit') }}</a> {{ __('profile.show.rejected_after') }}</p>
</div>
@endif

{{-- Mon espace (propriétaire) : raccourcis et déconnexion, utiles surtout sur mobile où le menu est replié. --}}
@if($isOwner)
<section class="card mb-8 p-4 sm:p-5" aria-labelledby="my-space-title">
    <div class="mb-3 flex items-center justify-between gap-3">
        <h2 id="my-space-title" class="card-title">{{ __('profile.show.shortcuts.title') }}</h2>
        <form method="POST" action="{{ route('logout') }}" data-loading-label="{{ __('layout.logging_out') }}">
            @csrf
            <button type="submit" class="btn-ghost btn-sm text-error-700"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>{{ __('profile.show.logout') }}</button>
        </form>
    </div>
    <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3">
        @foreach($shortcuts as [$url, $icon, $label])
        <li class="min-w-0">
            <a href="{{ $url }}" class="flex h-full min-h-12 items-center gap-2.5 rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 hover:border-primary-600 hover:bg-primary-50 hover:text-primary-700">
                <i class="fa-solid {{ $icon }} w-4 shrink-0 text-center text-primary-600" aria-hidden="true"></i><span class="min-w-0 break-words">{{ $label }}</span>
            </a>
        </li>
        @endforeach
    </ul>
</section>
@endif

{{-- Témoignage de conversion mis en avant (docs/fonctionnalites/temoignage-de-conversion.md) --}}
@if($conversionTestimony)
<section class="mb-10" aria-labelledby="conversion-title">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 id="conversion-title" class="card-title text-lg"><i class="fa-solid fa-dove mr-1.5 text-sun-700" aria-hidden="true"></i>{{ __('conversion.profile_title') }}</h2>
        @if($isOwner)
        <a href="{{ route('testimonies.conversion.edit') }}" class="btn-ghost btn-sm"><i class="fa-solid fa-pen" aria-hidden="true"></i>{{ __('conversion.edit') }}</a>
        @endif
    </div>
    @include('components.conversion-card', ['testimony' => $conversionTestimony, 'showAuthor' => false, 'large' => true])
</section>
@endif

<section id="temoignages" class="mb-10 scroll-mt-20" aria-labelledby="testimonies-title">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 id="testimonies-title" class="card-title text-lg">{{ __('profile.show.testimonies') }}</h2>
        @if($testimonies->isNotEmpty())@include('components.layout-toggle')@endif
    </div>

    @if($testimonies->isEmpty())
        @include('components.empty-state', [
            'title'       => __('profile.show.empty_title'),
            'text'        => $isOwner ? __('profile.show.empty_text') : null,
            'actionUrl'   => $isOwner ? route('publish') : null,
            'actionLabel' => __('profile.show.empty_action'),
        ])
    @else
        <div class="mb-8">
            @include('components.testimony-list', ['items' => $testimonies, 'routeName' => 'testimonies.show'])
        </div>
        {{ $testimonies->links() }}
    @endif
</section>

{{-- Événements organisés : à venir / en cours, puis passés (témoignages et commentaires restent consultables). --}}
@foreach([[__('profile.show.events_upcoming'), $upcomingEvents ?? collect()], [__('profile.show.events_past'), $pastEvents ?? collect()]] as [$eventsTitle, $eventsList])
@if($eventsList->isNotEmpty())
<section class="mb-10">
    <h2 class="card-title mb-4 text-lg"><i class="fa-solid fa-calendar-days mr-1.5" aria-hidden="true"></i>{{ $eventsTitle }}</h2>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($eventsList as $event)
            @include('events.partials.card', ['event' => $event])
        @endforeach
    </div>
</section>
@endif
@endforeach

{{-- Partages internes (« X a partagé ») : la carte garde l'auteur d'origine. docs/fonctionnalites/partage-interne.md --}}
@if(($reposts ?? collect())->isNotEmpty())
<section class="mb-10">
    <h2 class="card-title mb-4 text-lg"><i class="fa-solid fa-retweet mr-1.5" aria-hidden="true"></i>{{ __('profile.show.reposts') }}</h2>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 xl:gap-5">
        @foreach($reposts as $repost)
        <div class="flex min-w-0 flex-col gap-1.5">
            @include('feed.partials.repost-header', ['repost' => $repost])
            @include('videos.partials.tile', ['testimony' => $repost->testimony, 'url' => route('testimonies.show', $repost->testimony_id)])
        </div>
        @endforeach
    </div>
</section>
@endif
@endsection

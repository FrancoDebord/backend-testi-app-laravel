@extends('layouts.app')
@section('title', $profile->display_name)
@php
    // Présentation « chaîne » : bandeau, avatar, statistiques, puis les témoignages en grille.
    // Bouton Suivre : components/follow-button (le nombre d'abonnés se met à jour sans recharger).
    $stats = [
        [$profile->publishedTestimonyCount(), 'testimonies'],
        [$profile->follower_count,  'followers'],
        [$profile->following_count, 'following'],
    ];
@endphp

@section('content')
<section class="mb-8" aria-label="{{ __('profile.show.aria') }}">
    {{-- Bandeau : photo de couverture (docs/fonctionnalites/photo-de-couverture.md), sinon dégradé neutre. --}}
    <div class="h-28 overflow-hidden rounded-xl bg-gradient-to-r from-slate-200 via-slate-100 to-slate-200 sm:h-44 lg:h-52" aria-hidden="true">
        @if($profile->cover_url)
        <img src="{{ $profile->cover_url }}" alt="" class="h-full w-full object-cover" loading="eager" decoding="async">
        @endif
    </div>

    <div class="-mt-10 flex flex-col gap-4 px-2 sm:-mt-12 sm:flex-row sm:items-start sm:px-6">
        <div class="shrink-0 self-start rounded-full ring-4 ring-slate-50">
            @include('components.avatar', ['user' => $profile, 'size' => 'xl'])
        </div>
        <div class="min-w-0 flex-1 sm:pt-14">
            {{-- La coche reste accrochée au dernier mot du nom (jamais seule sur une ligne). --}}
            @php
                $nameHead = Str::contains($profile->display_name, ' ') ? Str::beforeLast($profile->display_name, ' ') . ' ' : '';
                $nameTail = Str::afterLast($profile->display_name, ' ');
                // Un très long mot doit pouvoir se couper (390 px) : pas de nowrap dans ce cas.
                $tailNowrap = mb_strlen($nameTail) <= 20;
            @endphp
            <h1 class="text-xl font-semibold break-words text-primary-600 sm:text-2xl">{{ $nameHead }}<span class="{{ $tailNowrap ? 'whitespace-nowrap' : '' }}">{{ $nameTail }}@if($profile->isVerified())&nbsp;@include('components.verified-badge', ['user' => $profile])@endif</span></h1>
            <p class="mt-1 flex flex-wrap gap-x-2 text-sm text-slate-500">
                @if($profile->isOrganization())<span>{{ $profile->organization_type?->label() ?? __('profile.show.organization') }}@if($profile->organization_city) · {{ $profile->organization_city }}@endif</span><span aria-hidden="true">·</span>@endif
                @if($profile->country)<span>{{ $profile->country }}</span><span aria-hidden="true">·</span>@endif
                @foreach($stats as [$count, $kind])
                @if($isOwner && in_array($kind, ['following', 'followers'], true))
                <a href="{{ route($kind === 'followers' ? 'profile.followers' : 'profile.following') }}" class="hover:underline"><span class="font-semibold text-slate-900">{{ \App\Support\Locales::number($count ?? 0) }}</span> {{ trans_choice('profile.show.stats.' . $kind, $count ?? 0) }}</a>
                @else
                <span><span class="font-semibold text-slate-900" @if($kind === 'followers') data-follower-count="{{ $profile->id }}" @endif>{{ \App\Support\Locales::number($count ?? 0) }}</span> {{ trans_choice('profile.show.stats.' . $kind, $count ?? 0) }}</span>
                @endif
                @if(!$loop->last)<span aria-hidden="true">·</span>@endif
                @endforeach
            </p>
        </div>
        <div class="flex flex-wrap gap-2 sm:pt-14">
            @if($isOwner)
                <a href="{{ route('profile.edit') }}" class="btn-secondary"><i class="fa-solid fa-pen" aria-hidden="true"></i>{{ __('profile.show.edit') }}</a>
                <a href="{{ route('publish') }}" class="btn-cta"><i class="fa-solid fa-plus" aria-hidden="true"></i>{{ __('profile.show.publish') }}</a>
            @else
                {{-- Suivre : docs/fonctionnalites/abonnements.md (personne non connectée : lien vers la connexion) --}}
                @include('components.follow-button', ['user' => $profile, 'following' => $isFollowing, 'primary' => true])
            @endif
        </div>
    </div>

    {{-- Suivi de la vérification, visible par l'organisation elle-même uniquement. --}}
    @if($isOwner && $profile->isOrganization() && $profile->verification_status === \App\Enums\VerificationStatus::Pending)
    <div class="alert-info mt-4" role="status">
        <i class="fa-solid fa-hourglass-half mt-0.5 text-slate-400" aria-hidden="true"></i>
        <p class="min-w-0"><span class="font-semibold text-slate-900">{{ __('profile.show.pending_title') }}</span> {{ __('profile.show.pending_text') }}</p>
    </div>
    @elseif($isOwner && $profile->isOrganization() && $profile->verification_status === \App\Enums\VerificationStatus::Rejected)
    <div class="alert-warning mt-4" role="status">
        <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
        <p class="min-w-0 break-words"><span class="font-semibold">{{ __('profile.show.rejected_title') }}</span>@if($profile->verification_note){{ __('profile.show.rejected_note', ['note' => rtrim($profile->verification_note, " .")]) }}@endif. {{ __('profile.show.rejected_before') }} <a href="{{ route('profile.edit') }}" class="font-semibold underline">{{ __('profile.show.edit') }}</a> {{ __('profile.show.rejected_after') }}</p>
    </div>
    @elseif($isOwner && !$profile->isOrganization())
    {{-- Compte personnel : docs/fonctionnalites/verification-des-comptes.md --}}
    <p class="mt-4 px-2 text-sm sm:px-6">
        <a href="{{ route('profile.verification') }}" class="inline-flex items-center gap-1.5 font-semibold text-primary-600 hover:underline">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>{{ match($profile->verification_status) {
                \App\Enums\VerificationStatus::Verified => __('profile.show.verification.verified'),
                \App\Enums\VerificationStatus::Pending  => __('profile.show.verification.pending'),
                \App\Enums\VerificationStatus::Rejected => __('profile.show.verification.rejected'),
                default => __('profile.show.verification.none'),
            } }}
        </a>
    </p>
    @endif

    @if($profile->bio)
    <p class="mt-4 max-w-3xl px-2 text-sm break-words whitespace-pre-line text-slate-700 sm:px-6">{{ $profile->bio }}</p>
    @elseif($isOwner)
    <p class="mt-4 px-2 text-sm text-slate-500 sm:px-6">{{ __('profile.show.no_bio') }}</p>
    @endif

    {{-- Fiabilité de l'organisateur d'événements (modération) — docs/fonctionnalites/evenements.md --}}
    @if(auth()->user()?->canModerate() && ($eventReliability = \App\Models\Event::reliabilityOf($profile->id))['total'] > 0)
    <div class="mt-4 max-w-md px-2 sm:px-6">
        <p class="text-xs font-semibold text-slate-500 uppercase">{{ __('profile.show.reliability') }}</p>
        @include('events.partials.reliability', ['reliability' => $eventReliability])
    </div>
    @endif
</section>

{{-- Témoignage de conversion mis en avant (docs/fonctionnalites/temoignage-de-conversion.md) --}}
@if($conversionTestimony)
<section class="mb-8" aria-labelledby="conversion-title">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 id="conversion-title" class="card-title"><i class="fa-solid fa-dove mr-1.5 text-sun-700" aria-hidden="true"></i>{{ __('conversion.profile_title') }}</h2>
        @if($isOwner)
        <a href="{{ route('testimonies.conversion.edit') }}" class="btn-ghost btn-sm"><i class="fa-solid fa-pen" aria-hidden="true"></i>{{ __('conversion.edit') }}</a>
        @endif
    </div>
    @include('components.conversion-card', ['testimony' => $conversionTestimony, 'showAuthor' => false, 'large' => true])
</section>
@elseif($isOwner && \App\Services\ConversionTestimonies::canPublish($profile))
<p class="mb-6 text-sm text-slate-600">
    <a href="{{ route('publish', ['kind' => 'conversion']) }}" class="font-semibold text-primary-600 hover:underline"><i class="fa-solid fa-dove mr-1 text-sun-700" aria-hidden="true"></i>{{ __('conversion.share_yours') }}</a>
</p>
@endif

<div class="mb-6 flex items-center justify-between gap-3 border-b border-slate-200">
    <h2 class="tab-active">{{ __('profile.show.testimonies') }}</h2>
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

{{-- Événements organisés : à venir / en cours, puis passés (témoignages et commentaires restent consultables). --}}
@foreach([[__('profile.show.events_upcoming'), $upcomingEvents ?? collect()], [__('profile.show.events_past'), $pastEvents ?? collect()]] as [$eventsTitle, $eventsList])
@if($eventsList->isNotEmpty())
<div class="mt-10 mb-6 flex items-center gap-3 border-b border-slate-200">
    <h2 class="tab-active"><i class="fa-solid fa-calendar-days mr-1" aria-hidden="true"></i>{{ $eventsTitle }}</h2>
</div>
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach($eventsList as $event)
        @include('events.partials.card', ['event' => $event])
    @endforeach
</div>
@endif
@endforeach

{{-- Partages internes (« X a partagé ») : la carte garde l'auteur d'origine. docs/fonctionnalites/partage-interne.md --}}
@if(($reposts ?? collect())->isNotEmpty())
<div class="mt-10 mb-6 flex items-center gap-3 border-b border-slate-200">
    <h2 class="tab-active"><i class="fa-solid fa-retweet mr-1" aria-hidden="true"></i>{{ __('profile.show.reposts') }}</h2>
</div>
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 xl:gap-5">
    @foreach($reposts as $repost)
    <div class="flex min-w-0 flex-col gap-1.5">
        @include('feed.partials.repost-header', ['repost' => $repost])
        @include('videos.partials.tile', ['testimony' => $repost->testimony, 'url' => route('testimonies.show', $repost->testimony_id)])
    </div>
    @endforeach
</div>
@endif
@endsection

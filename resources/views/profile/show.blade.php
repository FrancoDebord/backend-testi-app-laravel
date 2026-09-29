@extends('layouts.app')
@section('title', $profile->display_name)
@php
    // Présentation « chaîne » : bandeau, avatar, statistiques, puis les témoignages en grille.
    // Bouton Suivre : components/follow-button (le nombre d'abonnés se met à jour sans recharger).
    $stats = [
        [$profile->publishedTestimonyCount(), 'témoignage', 'témoignages'],
        [$profile->follower_count,  'abonné',     'abonnés'],
        [$profile->following_count, 'abonnement', 'abonnements'],
    ];
@endphp

@section('content')
<section class="mb-8" aria-label="Profil">
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
                @if($profile->isOrganization())<span>{{ $profile->organization_type?->label() ?? 'Organisation' }}@if($profile->organization_city) · {{ $profile->organization_city }}@endif</span><span aria-hidden="true">·</span>@endif
                @if($profile->country)<span>{{ $profile->country }}</span><span aria-hidden="true">·</span>@endif
                @foreach($stats as [$count, $one, $many])
                @if($isOwner && $one === 'abonnement')
                <a href="{{ route('profile.following') }}" class="hover:underline"><span class="font-semibold text-slate-900">{{ number_format($count ?? 0, 0, ',', ' ') }}</span> {{ ($count ?? 0) > 1 ? $many : $one }}</a>
                @else
                <span><span class="font-semibold text-slate-900" @if($one === 'abonné') data-follower-count="{{ $profile->id }}" @endif>{{ number_format($count ?? 0, 0, ',', ' ') }}</span> {{ ($count ?? 0) > 1 ? $many : $one }}</span>
                @endif
                @if(!$loop->last)<span aria-hidden="true">·</span>@endif
                @endforeach
            </p>
        </div>
        <div class="flex flex-wrap gap-2 sm:pt-14">
            @if($isOwner)
                <a href="{{ route('profile.edit') }}" class="btn-secondary"><i class="fa-solid fa-pen" aria-hidden="true"></i>Modifier le profil</a>
                <a href="{{ route('publish') }}" class="btn-cta"><i class="fa-solid fa-plus" aria-hidden="true"></i>Publier</a>
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
        <p class="min-w-0"><span class="font-semibold text-slate-900">Vérification en cours.</span> Notre équipe vérifie votre organisation. La coche « vérifiée » apparaîtra ensuite sur votre profil et vos témoignages.</p>
    </div>
    @elseif($isOwner && $profile->isOrganization() && $profile->verification_status === \App\Enums\VerificationStatus::Rejected)
    <div class="alert-warning mt-4" role="status">
        <i class="fa-solid fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
        <p class="min-w-0 break-words"><span class="font-semibold">Vérification refusée</span>@if($profile->verification_note) : {{ rtrim($profile->verification_note, " .") }}@endif. Vous pouvez corriger les informations de votre organisation depuis <a href="{{ route('profile.edit') }}" class="font-semibold underline">Modifier le profil</a> ou l'application mobile : la demande sera alors réexaminée.</p>
    </div>
    @endif

    @if($profile->bio)
    <p class="mt-4 max-w-3xl px-2 text-sm break-words whitespace-pre-line text-slate-700 sm:px-6">{{ $profile->bio }}</p>
    @elseif($isOwner)
    <p class="mt-4 px-2 text-sm text-slate-500 sm:px-6">Ajoutez une présentation pour que l'on vous connaisse mieux.</p>
    @endif
</section>

<div class="mb-6 flex items-center justify-between gap-3 border-b border-slate-200">
    <h2 class="tab-active">Témoignages</h2>
    @if($testimonies->isNotEmpty())@include('components.layout-toggle')@endif
</div>

@if($testimonies->isEmpty())
    @include('components.empty-state', [
        'title'       => 'Aucun témoignage publié',
        'text'        => $isOwner ? 'Vos témoignages publiés apparaîtront ici.' : null,
        'actionUrl'   => $isOwner ? route('publish') : null,
        'actionLabel' => 'Publier un témoignage',
    ])
@else
    <div class="mb-8">
        @include('components.testimony-list', ['items' => $testimonies, 'routeName' => 'testimonies.show'])
    </div>
    {{ $testimonies->links() }}
@endif
@endsection

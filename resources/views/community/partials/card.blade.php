{{-- Compte de la page Communauté : @include('community.partials.card', ['account' => $user]) --}}
@php
    $isOrg    = $account->isOrganization();
    $subtitle = $isOrg
        ? collect([$account->organization_type?->label(), $account->organization_city, $account->country])->filter()->implode(' · ')
        : $account->country;
    $profileUrl = route('profiles.show', $account->id);
@endphp
<article class="card flex min-w-0 flex-col gap-3 p-4">
    <div class="flex min-w-0 items-start gap-3">
        <a href="{{ $profileUrl }}" class="shrink-0 rounded-full" tabindex="-1" aria-hidden="true">
            @include('components.avatar', ['user' => $account, 'size' => 'lg'])
        </a>
        <div class="min-w-0 flex-1">
            <h3 class="flex min-w-0 items-center gap-1.5 text-sm font-semibold text-primary-700">
                <a href="{{ $profileUrl }}" class="truncate hover:underline">{{ $account->display_name }}</a>
                @include('components.verified-badge', ['user' => $account])
            </h3>
            @if($subtitle)
            <p class="truncate text-xs text-slate-500">{{ $subtitle }}</p>
            @endif
            <p class="mt-1 text-xs text-slate-600">
                <span data-follower-count="{{ $account->id }}" class="font-semibold text-slate-900">{{ number_format($account->follower_count, 0, ',', ' ') }}</span>
                abonné{{ $account->follower_count > 1 ? 's' : '' }}
                <span aria-hidden="true">·</span>
                @php($testimonies = $account->publishedTestimonyCount())
                {{ number_format($testimonies, 0, ',', ' ') }} témoignage{{ $testimonies > 1 ? 's' : '' }}
            </p>
        </div>
    </div>
    @if($account->bio)
    <p class="line-clamp-2 text-sm break-words text-slate-600">{{ $account->bio }}</p>
    @endif
    <div class="mt-auto flex flex-wrap items-center gap-2">
        @include('components.follow-button', ['user' => $account, 'following' => (bool) ($account->is_followed ?? false), 'small' => true])
        <a href="{{ $profileUrl }}" class="btn-ghost btn-sm">Voir le profil</a>
    </div>
</article>

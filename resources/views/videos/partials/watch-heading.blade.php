{{--
    En-tête d'un témoignage (page de lecture, docs/fonctionnalites/videos.md) : badges, titre, informations, auteur.
    Inclus par videos/partials/watch : en tête de l'article pour un texte, sous le lecteur pour une vidéo ou un audio.
    Variables héritées : $testimony, $type, $publishedAt, $isFollowingAuthor.
--}}
<div class="flex flex-wrap items-center gap-2">
    @if($testimony->isConversion())
    <span class="badge-yellow"><i class="fa-solid fa-dove" aria-hidden="true"></i>{{ __('conversion.kind_conversion') }}</span>
    @endif
    @if($testimony->category)
    <a href="{{ route('videos.index', ['category' => $testimony->category->slug]) }}" class="badge-blue hover:underline">{{ $testimony->category->displayName() }}</a>
    @endif
    @if($testimony->liveSession)
    <span class="badge-neutral"><i class="fa-solid fa-tower-broadcast" aria-hidden="true"></i>{{ __('videos.watch.live_replay') }}</span>
    @endif
</div>

<h1 class="mt-3 text-2xl leading-tight font-bold break-words text-primary-600 sm:text-[28px]">{{ $testimony->title }}</h1>

<ul class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-500">
    <li class="flex items-center gap-1.5"><i class="fa-regular fa-eye text-xs text-slate-400" aria-hidden="true"></i><span data-views-label>{{ $testimony->viewsLabel() }}</span></li>
    @if($publishedAt)
    <li class="flex items-center gap-1.5"><i class="fa-regular fa-calendar text-xs text-slate-400" aria-hidden="true"></i><time datetime="{{ $publishedAt->toIso8601String() }}">{{ $publishedAt->translatedFormat(__('videos.watch.published_format')) }}</time></li>
    @endif
    @if($type === 'text')
    <li class="flex items-center gap-1.5"><i class="fa-regular fa-clock text-xs text-slate-400" aria-hidden="true"></i>{{ __('videos.common.reading', ['minutes' => $testimony->readingMinutes()]) }}</li>
    @endif
</ul>

{{-- Témoignage de conversion : année, lieu, verset ; « Modifier » pour l'auteur (docs/fonctionnalites/temoignage-de-conversion.md) --}}
@if($testimony->isConversion())
@php $conversion = $testimony->conversionDetails(); @endphp
@if($conversion['year'] || $conversion['place'] || $conversion['verse'] || auth()->id() === $testimony->user_id)
<div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg bg-sun-50 px-4 py-3 text-sm text-slate-700">
    @if($conversion['year'])<span><i class="fa-regular fa-calendar mr-1 text-sun-700" aria-hidden="true"></i>{{ __('conversion.converted_in', ['year' => $conversion['year']]) }}</span>@endif
    @if($conversion['place'])<span><i class="fa-solid fa-location-dot mr-1 text-sun-700" aria-hidden="true"></i>{{ __('conversion.converted_at', ['place' => $conversion['place']]) }}</span>@endif
    @if($conversion['verse'])<span><i class="fa-solid fa-book-bible mr-1 text-sun-700" aria-hidden="true"></i>{{ $conversion['verse'] }}</span>@endif
    @if(auth()->id() === $testimony->user_id)
    <a href="{{ route('testimonies.conversion.edit') }}" class="btn-ghost btn-sm ml-auto"><i class="fa-solid fa-pen" aria-hidden="true"></i>{{ __('conversion.edit') }}</a>
    @endif
</div>
@endif
@endif

{{-- Auteur et bouton Suivre (docs/fonctionnalites/abonnements.md) ; témoignage anonyme : rien sur l'auteur
     (docs/fonctionnalites/referencement.md) --}}
@if(!$testimony->showsAuthorOnWeb(auth()->user()))
<div class="mt-4 flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
    @include('components.avatar', ['user' => null, 'size' => 'md'])
    <p class="text-sm font-semibold text-slate-700">{{ __('seo.anonymous') }}</p>
</div>
@else
<div class="mt-4 flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
    <a href="{{ route('profiles.show', $testimony->user->id) }}" class="shrink-0 rounded-full" tabindex="-1" aria-hidden="true">
        @include('components.avatar', ['user' => $testimony->user, 'size' => 'md'])
    </a>
    <div class="min-w-0 flex-1">
        <p class="flex min-w-0 items-center gap-1.5">
            <a href="{{ route('profiles.show', $testimony->user->id) }}" class="block truncate text-sm font-semibold text-slate-900 hover:underline">{{ $testimony->user->display_name }}</a>
            @include('components.verified-badge', ['user' => $testimony->user])
        </p>
        <p class="text-xs text-slate-500"><span data-follower-count="{{ $testimony->user->id }}">{{ \App\Support\Locales::number($testimony->user->follower_count ?? 0) }}</span> {{ trans_choice('videos.watch.followers', (int) ($testimony->user->follower_count ?? 0)) }}</p>
    </div>
    <div class="shrink-0">
        @include('components.follow-button', ['user' => $testimony->user, 'following' => $isFollowingAuthor ?? false, 'small' => true])
    </div>
</div>
@endif

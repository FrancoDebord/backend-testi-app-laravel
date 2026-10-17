{{--
    Carte mise en avant d'un témoignage de conversion (section « Témoignages de conversion », profil de l'auteur).
    docs/fonctionnalites/temoignage-de-conversion.md
    @include('components.conversion-card', ['testimony' => $t, 'showAuthor' => true, 'large' => false])
    Toute la carte est cliquable (lien étiré sur le titre) ; l'auteur reste un lien distinct.
--}}
@php
    $showAuthor = ($showAuthor ?? true) === true;
    $large      = ($large ?? false) === true;
    $details    = $testimony->conversionDetails() ?? [];
    $status     = $testimony->status->value !== 'approved' ? $testimony->status : null;
@endphp
<article class="card group relative flex min-w-0 flex-col overflow-hidden ring-2 ring-sun-400 transition-shadow hover:shadow-lg">
    <div class="flex flex-wrap items-center gap-2 bg-sun-50 px-5 py-3">
        <span class="badge-yellow"><i class="fa-solid fa-dove" aria-hidden="true"></i>{{ __('conversion.badge') }}</span>
        @if($status)
        <span class="{{ $status->badgeClass() }}">{{ $status->label() }}</span>
        @endif
        @if(!empty($details['year']))
        <span class="ml-auto text-sm font-semibold text-primary-700">{{ __('conversion.converted_in', ['year' => $details['year']]) }}</span>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-3 p-5">
        <h3 class="{{ $large ? 'text-lg sm:text-xl' : 'text-base' }} line-clamp-2 leading-snug font-bold text-primary-700">
            <a href="{{ route('testimonies.show', $testimony->id) }}" class="rounded-sm after:absolute after:inset-0 after:content-[''] group-hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">{{ $testimony->title }}</a>
        </h3>
        @if($testimony->body_text)
        <p class="{{ $large ? 'line-clamp-5' : 'line-clamp-3' }} text-sm leading-relaxed text-slate-700">{{ Str::limit($testimony->body_plain, $large ? 420 : 240) }}</p>
        @endif
        @if(!empty($details['place']) || !empty($details['verse']))
        <ul class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
            @if(!empty($details['place']))
            <li class="flex min-w-0 items-center gap-1.5"><i class="fa-solid fa-location-dot text-slate-400" aria-hidden="true"></i><span class="sr-only">{{ __('conversion.place') }} : </span><span class="truncate">{{ $details['place'] }}</span></li>
            @endif
            @if(!empty($details['verse']))
            <li class="flex min-w-0 items-center gap-1.5"><i class="fa-solid fa-book-bible text-slate-400" aria-hidden="true"></i><span class="sr-only">{{ __('conversion.verse') }} : </span><span class="truncate">{{ $details['verse'] }}</span></li>
            @endif
        </ul>
        @endif
        <div class="mt-auto flex flex-wrap items-center justify-between gap-3 pt-1">
            @if($showAuthor && $testimony->user)
            <a href="{{ route('profiles.show', $testimony->user->id) }}" class="relative z-10 flex min-w-0 items-center gap-2 text-sm text-slate-700 hover:text-slate-900 hover:underline">
                @include('components.avatar', ['user' => $testimony->user, 'size' => 'sm'])
                <span class="truncate font-medium">{{ $testimony->user->display_name }}</span>
                @include('components.verified-badge', ['user' => $testimony->user])
            </a>
            @endif
            <span class="text-sm font-semibold text-primary-600" aria-hidden="true">{{ __('conversion.read') }} <i class="fa-solid fa-arrow-right text-xs"></i></span>
        </div>
    </div>
</article>

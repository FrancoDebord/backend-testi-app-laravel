{{--
    « Comptes à suivre » (docs/fonctionnalites/abonnements.md) : @include('community.partials.suggested')
    Variables facultatives :
      - $suggestions : liste déjà calculée (App\Services\AccountSuggestions::for) ; sinon chargée ici ;
      - $suggestedLimit (10), $suggestedType ('all'|'organizations'|'people'), $suggestedTitle ('Comptes à suivre') ;
      - $suggestedMore (true) : lien « Voir plus » vers la Communauté.
    Rien n'est affiché s'il n'y a aucune suggestion (ou en cas d'erreur).
--}}
@php
    $suggestedItems = $suggestions ?? null;
    if ($suggestedItems === null) {
        try {
            $suggestedItems = app(\App\Services\AccountSuggestions::class)
                ->for(Auth::user(), (int) ($suggestedLimit ?? 10), $suggestedType ?? 'all');
        } catch (\Throwable $e) {
            report($e);
            $suggestedItems = collect();
        }
    }
    $suggestedItems = collect($suggestedItems);
    $suggestedHeadingId = 'suggested-' . substr(md5(uniqid('', true)), 0, 6);
@endphp
@if($suggestedItems->isNotEmpty())
<section class="mb-8" aria-labelledby="{{ $suggestedHeadingId }}">
    <div class="mb-3 flex items-center justify-between gap-3">
        <h2 id="{{ $suggestedHeadingId }}" class="text-base font-semibold text-slate-900">
            <i class="fa-solid fa-user-plus mr-1.5 text-primary-600" aria-hidden="true"></i>{{ $suggestedTitle ?? 'Comptes à suivre' }}
        </h2>
        @if($suggestedMore ?? true)
        <a href="{{ route('community.index') }}" class="btn-ghost btn-sm">Voir plus<i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i></a>
        @endif
    </div>
    <ul class="-mx-1 flex snap-x gap-3 overflow-x-auto px-1 pb-2" role="list">
        @foreach($suggestedItems as $item)
        @php
            $suggestedUser = $item['user'];
            $suggestedUrl  = route('profiles.show', $suggestedUser->id);
        @endphp
        <li class="card flex w-40 shrink-0 snap-start flex-col items-center gap-2 p-4 text-center">
            <a href="{{ $suggestedUrl }}" class="rounded-full" tabindex="-1" aria-hidden="true">
                @include('components.avatar', ['user' => $suggestedUser, 'size' => 'lg'])
            </a>
            <h3 class="flex w-full min-w-0 items-center justify-center gap-1 text-sm font-semibold text-primary-700">
                <a href="{{ $suggestedUrl }}" class="truncate hover:underline">{{ $suggestedUser->display_name }}</a>
                @include('components.verified-badge', ['user' => $suggestedUser])
            </h3>
            <p class="line-clamp-2 text-xs text-slate-500">{{ $item['reason'] }}</p>
            <p class="text-xs text-slate-600">
                <span data-follower-count="{{ $suggestedUser->id }}" class="font-semibold text-slate-900">{{ number_format($suggestedUser->follower_count, 0, ',', ' ') }}</span>
                abonné{{ $suggestedUser->follower_count > 1 ? 's' : '' }}
            </p>
            <div class="mt-auto">
                @include('components.follow-button', ['user' => $suggestedUser, 'following' => false, 'small' => true])
            </div>
        </li>
        @endforeach
    </ul>
</section>
@endif

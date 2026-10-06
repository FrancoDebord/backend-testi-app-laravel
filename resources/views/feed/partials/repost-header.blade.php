{{--
    En-tête d'un partage interne (« X a partagé ») au-dessus de la carte du témoignage d'origine,
    qui garde son auteur : le contenu n'est jamais recopié sous le nom du partageur.
    @include('feed.partials.repost-header', ['repost' => $repost])  — App\Models\TestimonyRepost (user chargé)
    Voir docs/fonctionnalites/partage-interne.md
--}}
<div class="min-w-0 {{ ($compact ?? false) ? 'px-3 pt-3' : '' }}" data-repost="{{ $repost->id }}">
    <p class="flex min-w-0 items-center gap-1.5 text-xs font-semibold text-accent-700">
        <i class="fa-solid fa-retweet" aria-hidden="true"></i>
        <a href="{{ route('profiles.show', $repost->user_id) }}" class="truncate hover:underline">{{ $repost->user?->display_name }}</a>
        <span class="shrink-0">a partagé</span>
        @if($repost->created_at)<time class="shrink-0 font-normal text-slate-500" datetime="{{ $repost->created_at->toIso8601String() }}">· {{ $repost->created_at->diffForHumans() }}</time>@endif
    </p>
    @if(filled($repost->comment))
    <p class="mt-1 text-sm break-words text-slate-700">{{ $repost->comment }}</p>
    @endif
</div>

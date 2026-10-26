{{-- Onglet « Commentaires » du popup « Qui a réagi ? » : qui a commenté, et quoi ; « Afficher plus » charge la suite. --}}
@foreach($page->items() as $comment)
<li class="flex gap-3 py-3">
    <a href="{{ route('profiles.show', $comment['user']->id) }}" class="shrink-0" aria-hidden="true" tabindex="-1">
        @include('components.avatar', ['user' => $comment['user'], 'size' => 'md'])
    </a>
    <div class="min-w-0 flex-1">
        <p class="flex min-w-0 items-center gap-1.5">
            <a href="{{ route('profiles.show', $comment['user']->id) }}" class="truncate text-sm font-semibold text-slate-900 hover:text-primary-600">{{ $comment['user']->display_name }}</a>
            @include('components.verified-badge', ['user' => $comment['user']])
            @if($comment['createdAt'])
            <time datetime="{{ $comment['createdAt'] }}" class="shrink-0 text-xs text-slate-500">· {{ \Illuminate\Support\Carbon::parse($comment['createdAt'])->diffForHumans() }}</time>
            @endif
        </p>
        <p class="mt-0.5 text-sm break-words whitespace-pre-line text-slate-700">{{ \Illuminate\Support\Str::limit($comment['body'], 300) }}</p>
    </div>
</li>
@endforeach
@if($page->hasMorePages())
<li class="py-3 text-center" data-reaction-people-more-row>
    <a href="{{ $url('comments', $page->currentPage() + 1) }}" class="btn-ghost btn-sm" data-reaction-people-more>{{ __('reactions.people.more') }}</a>
</li>
@endif

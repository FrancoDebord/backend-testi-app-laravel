{{-- Contenu du popup « Qui a réagi ? » (App\Http\Controllers\Web\ReactionPeopleController, docs/fonctionnalites/qui-a-reagi.md).
     Onglets à la ligne (jamais de défilement horizontal) ; « Commentaires » juste après « Toutes », toujours visible. --}}
<nav class="flex flex-wrap gap-x-4 border-b border-slate-100 px-5" aria-label="{{ __('reactions.people.tabs') }}">
    <a href="{{ $url(null) }}" class="{{ $filter === null ? 'tab-active' : 'tab' }}" data-reaction-people-link @if($filter === null) aria-current="true" @endif>
        {{ __('reactions.people.all') }} <span class="tabular-nums">{{ \App\Support\Locales::number($summary['total']) }}</span>
    </a>
    @if($hasComments)
    <a href="{{ $url('comments') }}" class="{{ $filter === 'comments' ? 'tab-active' : 'tab' }}" data-reaction-people-link
       @if($filter === 'comments') aria-current="true" @endif title="{{ $commentsLabel }}">
        <i class="fa-regular fa-comment" aria-hidden="true"></i>{{ $commentsLabel }} <span class="tabular-nums">{{ \App\Support\Locales::number($summary['commentsCount']) }}</span>
    </a>
    @endif
    @foreach($summary['types'] as $type)
    <a href="{{ $url($type['type']) }}" class="{{ $filter === $type['type'] ? 'tab-active' : 'tab' }}" data-reaction-people-link
       @if($filter === $type['type']) aria-current="true" @endif title="{{ $type['label'] }}">
        <i class="fa-solid {{ $type['icon'] }}" aria-hidden="true"></i>{{ $type['label'] }} <span class="tabular-nums">{{ \App\Support\Locales::number($type['count']) }}</span>
    </a>
    @endforeach
</nav>

@if($page->total() === 0)
<p class="px-5 py-8 text-center text-sm text-slate-500">{{ match (true) {
    $filter === null       => __('reactions.people.empty'),
    $filter === 'comments' => __('reactions.people.empty_comments'),
    default                => __('reactions.people.empty_type'),
} }}</p>
@else
<ul class="divide-y divide-slate-100 px-5" data-reaction-people-list>
    @include($rows)
</ul>
@endif

{{-- Personnes d'une page du popup « Qui a réagi ? » ; « Afficher plus » charge la page suivante à la suite. --}}
@foreach($page->items() as $row)
<li class="flex items-center gap-3 py-3">
    <a href="{{ route('profiles.show', $row['user']->id) }}" class="flex min-w-0 flex-1 items-center gap-3 rounded-lg hover:text-primary-600">
        @include('components.avatar', ['user' => $row['user'], 'size' => 'md'])
        <span class="flex min-w-0 items-center gap-1.5">
            <span class="truncate text-sm font-semibold text-slate-900">{{ $row['user']->display_name }}</span>
            @include('components.verified-badge', ['user' => $row['user']])
        </span>
    </a>
    <span class="flex shrink-0 items-center gap-2 text-primary-600">
        @foreach($row['types'] as $type)
        @php($typeLabel = \App\Services\ReactionPeople::label($type))
        <i class="fa-solid {{ \App\Services\ReactionPeople::ICONS[$type->value] }}" title="{{ $typeLabel }}" aria-hidden="true"></i><span class="sr-only">{{ $typeLabel }}</span>
        @if($type->group() === 'prayer' && $row['prayerTimes'] > 1)
        {{-- Nombre de fois que la personne a prié (docs/fonctionnalites/prier-plusieurs-fois.md) --}}
        <span class="text-xs font-semibold tabular-nums text-slate-600">{{ trans_choice('reactions.prayer_times.times', $row['prayerTimes'], ['count' => $row['prayerTimes']]) }}</span>
        @endif
        @endforeach
    </span>
</li>
@endforeach
@if($page->hasMorePages())
<li class="py-3 text-center" data-reaction-people-more-row>
    <a href="{{ $url($filter, $page->currentPage() + 1) }}" class="btn-ghost btn-sm" data-reaction-people-more>{{ __('reactions.people.more') }}</a>
</li>
@endif

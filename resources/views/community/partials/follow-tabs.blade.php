{{-- Onglets « Abonnés » / « Abonnements » : @include('community.partials.follow-tabs', ['active' => 'followers'|'following']) --}}
@php($followTabsUser = auth()->user())
<nav aria-label="Abonnés et abonnements" class="mb-6 border-b border-slate-200">
    <ul class="-mb-px flex gap-5 overflow-x-auto">
        @foreach([
            'followers' => ['profile.followers', 'fa-users', 'Abonnés', $followTabsUser->follower_count],
            'following' => ['profile.following', 'fa-user-check', 'Abonnements', $followTabsUser->following_count],
        ] as $key => [$routeName, $icon, $label, $count])
        <li>
            <a href="{{ route($routeName) }}" class="{{ $active === $key ? 'tab-active' : 'tab' }}" @if($active === $key) aria-current="page" @endif>
                <i class="fa-solid {{ $icon }} text-slate-400" aria-hidden="true"></i>{{ $label }}
                <span class="text-xs text-slate-500">{{ number_format((int) $count, 0, ',', ' ') }}</span>
            </a>
        </li>
        @endforeach
    </ul>
</nav>

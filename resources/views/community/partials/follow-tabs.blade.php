{{-- Onglets « Abonnés » / « Abonnements » : @include('community.partials.follow-tabs', ['active' => 'followers'|'following']) --}}
@php($followTabsUser = auth()->user())
<nav aria-label="{{ __('community.follow_tabs.aria') }}" class="mb-6 border-b border-slate-200">
    <ul class="-mb-px flex gap-5 overflow-x-auto">
        @foreach([
            'followers' => ['profile.followers', 'fa-users', __('community.follow_tabs.followers'), $followTabsUser->follower_count],
            'following' => ['profile.following', 'fa-user-check', __('community.follow_tabs.following'), $followTabsUser->following_count],
        ] as $key => [$routeName, $icon, $label, $count])
        <li>
            <a href="{{ route($routeName) }}" class="{{ $active === $key ? 'tab-active' : 'tab' }}" @if($active === $key) aria-current="page" @endif>
                <i class="fa-solid {{ $icon }} text-slate-400" aria-hidden="true"></i>{{ $label }}
                <span class="text-xs text-slate-500">{{ \App\Support\Locales::number((int) $count) }}</span>
            </a>
        </li>
        @endforeach
    </ul>
</nav>

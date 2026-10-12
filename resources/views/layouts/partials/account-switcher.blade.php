{{--
    « Mes comptes » : le compte de la personne puis ceux qu'on lui a confiés (administrateurs de compte,
    docs/fonctionnalites/administrateurs-de-compte.md). Chaque entrée est un petit formulaire POST vers
    accounts.switch ; le compte actif est coché.
    @include('layouts.partials.account-switcher', ['accounts' => $myAccounts, 'activeId' => $activeAccountId])
--}}
<p class="section-title px-3 pb-1">Mes comptes</p>
<ul class="space-y-0.5" aria-label="Mes comptes">
    @foreach($accounts as $account)
    @php
        $isActive  = $account['id'] === $activeId;
        $isOwnAcc  = $account['role'] === 'owner';
        $avatarObj = (object) ['avatar_url' => $account['avatarUrl'], 'initials' => $account['initials']];
    @endphp
    <li>
        <form method="POST" action="{{ route('accounts.switch', $account['id']) }}" data-loading-label="Changement de compte…">
            @csrf
            <button type="submit"
                    @if($isActive) aria-current="true" @endif
                    class="{{ $isActive ? 'bg-primary-50 text-primary-700' : 'text-slate-700 hover:bg-primary-50 hover:text-primary-600' }} flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm">
                @include('components.avatar', ['user' => $avatarObj, 'size' => 'sm'])
                <span class="flex min-w-0 flex-1 items-center gap-1.5">
                    <span class="truncate font-semibold">{{ $account['displayName'] }}</span>
                    @if($account['isVerified'])
                    <i class="fa-solid fa-circle-check shrink-0 text-slate-500" title="{{ $account['isOrganization'] ? 'Organisation vérifiée' : 'Compte vérifié' }}" aria-label="{{ $account['isOrganization'] ? 'Organisation vérifiée' : 'Compte vérifié' }}" role="img"></i>
                    @endif
                    @if($isOwnAcc)
                    <span class="badge-neutral shrink-0">Vous</span>
                    @endif
                </span>
                @if($isActive)
                <i class="fa-solid fa-check shrink-0 text-primary-600" aria-hidden="true"></i>
                <span class="sr-only">(compte actif)</span>
                @endif
            </button>
        </form>
    </li>
    @endforeach
</ul>

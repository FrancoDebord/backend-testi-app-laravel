{{-- Coche « vérifié » à côté d'un nom (organisation ou personne vérifiée) :
     docs/fonctionnalites/comptes-organisation.md, docs/fonctionnalites/verification-des-comptes.md
     @include('components.verified-badge', ['user' => $user]) — n'affiche rien si le compte n'est pas vérifié. --}}
@if($user && method_exists($user, 'isVerified') && $user->isVerified())
@php($verifiedLabel = method_exists($user, 'isOrganization') && $user->isOrganization() ? 'Organisation vérifiée' : 'Compte vérifié')
<i class="fa-solid fa-circle-check shrink-0 text-slate-500" title="{{ $verifiedLabel }}" aria-label="{{ $verifiedLabel }}" role="img"></i>
@endif

{{-- Coche « organisation vérifiée » à côté d'un nom : docs/fonctionnalites/comptes-organisation.md
     @include('components.verified-badge', ['user' => $user]) — n'affiche rien si le compte n'est pas vérifié. --}}
@if($user && method_exists($user, 'isVerified') && $user->isVerified())
<i class="fa-solid fa-circle-check shrink-0 text-slate-500" title="Organisation vérifiée" aria-label="Organisation vérifiée" role="img"></i>
@endif

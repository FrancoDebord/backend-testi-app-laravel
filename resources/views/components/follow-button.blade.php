{{--
    Bouton « Suivre » (docs/fonctionnalites/abonnements.md) :
    @include('components.follow-button', ['user' => $u, 'following' => bool, 'primary' => false, 'small' => false])
    - rien pour son propre compte (on ne se suit pas soi-même) ;
    - personne non connectée : lien vers la connexion ;
    - sinon formulaire (fonctionne sans JavaScript), transformé par app.js en bascule sans rechargement
      (data-follow-form) ; les éléments [data-follower-count="{id}"] sont mis à jour.
    idleLabel : texte du bouton tant qu'on ne suit pas (« Suivre » par défaut, « Suivre en retour » dans Mes abonnés).
    primary : bouton principal (en-tête d'un profil) ; dans les listes, bouton secondaire.
--}}
@php
    $followViewer    = Auth::user();
    $followIsSelf    = $followViewer && $followViewer->id === $user->id;
    $followState     = (bool) ($following ?? false);
    $followPrimary   = ($primary ?? false) === true;
    $followSmall     = ($small ?? false) === true;
    $followLabel     = ($idleLabel ?? null) ?: __('js.app.follow.follow'); // « Suivre en retour » dans « Mes abonnés »
    $followClass     = $followState || !$followPrimary ? 'btn-secondary' : 'btn-primary';
@endphp
@if($followIsSelf)
@elseif(!$followViewer)
    <a href="{{ route('login') }}" class="{{ $followPrimary ? 'btn-primary' : 'btn-secondary' }} {{ $followSmall ? 'btn-sm' : '' }}">
        <i class="fa-solid fa-user-plus" aria-hidden="true"></i>{{ __('js.app.follow.follow') }}
    </a>
@else
    <form method="POST" action="{{ route('users.follow', $user->id) }}" data-follow-form data-follow-user="{{ $user->id }}"
          data-follow-primary="{{ $followPrimary ? '1' : '0' }}" data-follow-name="{{ $user->display_name }}" data-no-loading>
        @csrf
        @if($followState)@method('DELETE')@endif
        <button type="submit" class="{{ $followClass }} {{ $followSmall ? 'btn-sm' : '' }}" aria-pressed="{{ $followState ? 'true' : 'false' }}"
                aria-label="{{ __($followState ? 'js.app.follow.unfollow_name' : 'js.app.follow.follow_name', ['name' => $user->display_name]) }}">
            <i class="fa-solid {{ $followState ? 'fa-check' : 'fa-user-plus' }}" aria-hidden="true" data-follow-icon></i>
            <span data-follow-label>{{ $followState ? __('js.app.follow.following') : $followLabel }}</span>
        </button>
    </form>
@endif

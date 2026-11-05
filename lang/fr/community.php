<?php

// Communauté : comptes à suivre, abonnements, abonnés, suggestions.
return [
    'tabs'                 => [
        'all'           => 'Tous',
        'organizations' => 'Organisations',
        'people'        => 'Personnes',
    ],
    'follow'               => [
        'self'        => 'Vous ne pouvez pas vous suivre vous-même.',
        'unavailable' => 'Ce compte n\'est pas disponible.',
        'following'   => 'Vous suivez :name.',
        'unfollowed'  => 'Vous ne suivez plus :name.',
    ],
    'api'                  => [
        'followed'   => 'Abonnement effectué',
        'unfollowed' => 'Abonnement annulé',
    ],
    'reasons'              => [
        'followed_by'            => 'Suivi par :name que vous suivez',
        'followed_by_others'     => '{1} Suivi par :name et :count autre que vous suivez|[2,*] Suivi par :name et :count autres que vous suivez',
        'follows_you'            => 'Vous suit',
        'verified_organization'  => 'Organisation vérifiée',
        'verified_account'       => 'Compte vérifié',
        'posts_often'            => 'Publie souvent des témoignages',
        'shared_interests'       => 'Témoigne sur des sujets qui vous intéressent',
        'community_organization' => 'Organisation de la communauté',
        'shares_testimonies'     => 'Partage des témoignages',
        'community_member'       => 'Membre de la communauté',
    ],
    'notify_follow'        => ':name vous suit maintenant',
    'notify_new_testimony' => ':name a partagé un nouveau témoignage : ":title"',
    // Page Communauté (community/index)
    'index'                => [
        'title'               => 'Communauté',
        'subheader'           => 'Églises, ministères, associations et personnes : suivez-les pour être prévenu de leurs témoignages et de leurs directs.',
        'empty_people'        => 'Aucune personne pour le moment.',
        'empty_people_text'   => 'Les personnes inscrites apparaîtront ici.',
        'empty_organizations' => 'Aucune organisation pour le moment.',
        'empty_organizations_text' => 'Les églises, ministères et associations inscrits apparaîtront ici.',
        'empty_all'           => 'Aucun compte pour le moment.',
        'empty_all_text'      => 'Les personnes et organisations inscrites apparaîtront ici.',
        'search_label'        => 'Rechercher un compte',
        'placeholder_people'  => 'Nom, pays…',
        'suggested_title'     => 'Suggestions pour vous',
        'tabs_aria'           => 'Type de compte',
        'no_match'            => 'Aucun compte ne correspond à votre recherche.',
    ],
    // Recherche dans les listes de comptes (Communauté, abonnés, abonnements)
    'search'               => [
        'placeholder' => 'Nom, ville, pays…',
        'results'     => '{0} :count résultat pour|{1} :count résultat pour|[2,*] :count résultats pour',
        'quoted'      => '« :q »',
        'retry'       => 'Vérifiez l\'orthographe ou essayez un autre nom.',
        'clear'       => 'Effacer la recherche',
        'discover'    => 'Découvrir la Communauté',
    ],
    // Mes abonnés (community/followers)
    'followers_page'       => [
        'title'        => 'Mes abonnés',
        'subheader'    => 'Les comptes qui vous suivent : ils sont prévenus de vos témoignages et de vos directs.',
        'search_label' => 'Rechercher dans mes abonnés',
        'count'        => '{0} :count compte vous suit.|{1} :count compte vous suit.|[2,*] :count comptes vous suivent.',
        'no_match'     => 'Aucun abonné ne correspond à votre recherche.',
        'empty_title'  => 'Personne ne vous suit encore.',
        'empty_text'   => 'Publiez des témoignages et participez à la Communauté : ceux qui vous suivront apparaîtront ici.',
        'follow_back'  => 'Suivre en retour',
    ],
    // Mes abonnements (community/following)
    'following_page'       => [
        'title'        => 'Mes abonnements',
        'subheader'    => 'Les comptes que vous suivez : vous êtes prévenu de leurs témoignages et de leurs directs.',
        'search_label' => 'Rechercher dans mes abonnements',
        'count'        => '{0} Vous suivez :count compte.|{1} Vous suivez :count compte.|[2,*] Vous suivez :count comptes.',
        'no_match'     => 'Aucun abonnement ne correspond à votre recherche.',
        'empty_title'  => 'Vous ne suivez encore personne.',
        'empty_text'   => 'Découvrez des églises, des ministères et des personnes qui témoignent dans la Communauté.',
    ],
    // Onglets Abonnés / Abonnements (community/partials/follow-tabs)
    'follow_tabs'          => [
        'aria'      => 'Abonnés et abonnements',
        'followers' => 'Abonnés',
        'following' => 'Abonnements',
    ],
    // Carte de compte et suggestions (community/partials/card, community/partials/suggested)
    'card'                 => [
        'followers'    => '{0} abonné|{1} abonné|[2,*] abonnés',
        'testimonies'  => '{0} :count témoignage|{1} :count témoignage|[2,*] :count témoignages',
        'view_profile' => 'Voir le profil',
    ],
    'suggested'            => [
        'title' => 'Comptes à suivre',
        'more'  => 'Voir plus',
    ],
    // Choix d'une personne (components/user-picker)
    'user_picker'          => [
        'label'       => 'Ajouter une personne',
        'placeholder' => 'Nom ou adresse e-mail',
        'hint'        => 'Comptes personnels seulement. Saisissez au moins 2 lettres du nom, ou l\'adresse e-mail complète.',
    ],
];

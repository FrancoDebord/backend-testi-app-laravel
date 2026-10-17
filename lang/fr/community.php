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
];

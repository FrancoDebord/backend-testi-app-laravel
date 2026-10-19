<?php

// Directs passés : revoir le chat, témoigner après la fin (docs/fonctionnalites/lives.md)
return [
    'validation' => [
        'live_invalid'   => 'Le direct indiqué est invalide.',
        'live_not_found' => 'Ce direct est introuvable.',
        'live_not_ended' => 'Vous pourrez témoigner sur ce direct une fois qu’il sera terminé.',
    ],
    'chat' => [
        'title'    => 'Revoir la discussion',
        'count'    => '{0} Aucun message|{1} 1 message|[2,*] :count messages',
        'empty'    => "Aucun message n'a été laissé pendant ce direct.",
        'at'       => 'à :time du direct',
        'previous' => 'Messages précédents',
        'next'     => 'Messages suivants',
        'host'     => 'Diffuseur',
    ],
    'testify' => [
        'button'    => 'Témoigner sur ce direct',
        'help'      => 'Ce direct vous a touché ? Partagez ce que Dieu a fait.',
        'login'     => 'Connectez-vous pour témoigner sur ce direct.',
        'banner'    => 'Vous témoignez sur le direct',
        'remove'    => 'Ne plus lier ce témoignage au direct',
    ],
    'testimonies' => [
        'title' => 'Témoignages sur ce direct',
        'count' => '{0} Témoignages sur ce direct|{1} 1 témoignage sur ce direct|[2,*] :count témoignages sur ce direct',
        'empty' => 'Aucun témoignage publié sur ce direct pour le moment.',
        'more'  => 'Voir plus',
    ],
    'linked' => 'Témoignage sur le direct',

    // Bilan d'un direct terminé, comme l'écran de fin de l'application (LiveEndedView)
    'summary' => [
        'badge'       => 'TERMINÉ',
        'ended'       => 'Direct terminé',
        'your_ended'  => 'Votre direct est terminé',
        'numbers'     => 'En chiffres',
        'host_numbers'=> 'Bilan de votre direct',
        'reactions'   => 'Réactions',
        'stats' => [
            'duration'     => 'Durée',
            'peak_viewers' => 'Spectateurs au plus fort',
            'comments'     => 'Commentaires',
            'reactions'    => 'Réactions',
        ],
        'reasons' => [
            'host'       => 'Terminé par le diffuseur',
            'moderator'  => 'Coupé par la modération',
            'connection' => 'Interrompu (connexion perdue)',
            'abandoned'  => "Jamais passé à l'antenne",
            'default'    => "Merci d'avoir suivi ce témoignage.",
        ],
    ],
    // Liste « Récents » de /lives (comme _RecentTile de l'application)
    'recent' => [
        'viewers'  => '{0} 0 spectateur|{1} 1 spectateur|[2,*] :count spectateurs',
        'comments' => '{0} 0 commentaire|{1} 1 commentaire|[2,*] :count commentaires',
        'open'     => 'Voir le bilan du direct',
    ],
];

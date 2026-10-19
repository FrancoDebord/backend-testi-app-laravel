<?php

// Directs passés : revoir le chat, témoigner après la fin (docs/fonctionnalites/lives.md)
return [
    'validation' => [
        'live_invalid'   => 'Le direct indiqué est invalide.',
        'live_not_found' => 'Ce direct est introuvable.',
        'live_not_ended' => 'Vous pourrez témoigner sur ce direct une fois qu’il sera terminé.',
    ],
    'chat' => [
        'title'    => 'Revoir le chat',
        'count'    => '{0} Aucun message|{1} 1 message|[2,*] :count messages',
        'empty'    => 'Aucun message n’a été écrit pendant ce direct.',
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
        'empty' => 'Aucun témoignage publié sur ce direct pour le moment.',
        'more'  => 'Voir plus',
    ],
    'linked' => 'Témoignage sur le direct',
];

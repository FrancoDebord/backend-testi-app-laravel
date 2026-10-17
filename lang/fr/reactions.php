<?php

// Réactions et partages internes : témoignages, événements, requêtes et sessions de prière.
return [
    // Boutons de la page de lecture
    'like'    => 'J\'aime',
    'pray'    => 'Prière',
    'amen'    => 'Amen',
    'worship' => 'Adorer Dieu',
    'fire'    => 'Feu',

    'api' => [
        'added'   => 'Réaction ajoutée',
        'saved'   => 'Réaction enregistrée',
        'removed' => 'Réaction retirée',
    ],

    // Notifications à l'auteur d'un témoignage (App\Services\TestimonyReactions)
    'notify_like'   => ':actor a réagi à votre témoignage',
    'notify_prayer' => ':actor prie pour votre témoignage',

    // Événements, requêtes et sessions de prière (App\Services\ContentInteractions)
    'content' => [
        'not_found'     => 'Publication introuvable.',
        'use_pray'      => 'Utilisez « Je prie » pour prier pour cette requête.',
        'own'           => 'Vous ne pouvez pas partager votre propre publication.',
        'not_shareable' => 'Cette publication ne peut pas être partagée.',
        'not_reposted'  => 'Vous n\'avez pas partagé cette publication.',
        'reposted'      => 'Partagé avec vos abonnés.',
        'comment_max'   => 'Votre commentaire : 500 caractères au plus.',
    ],

    // Notification à l'auteur : <action>_<type de publication>. « prayer » : l'application préfixe le nom.
    'notify' => [
        'like_event'            => ':actor a réagi à votre événement « :title ».',
        'like_prayer_request'   => ':actor a réagi à votre requête de prière.',
        'like_prayer_session'   => ':actor a réagi à votre session de prière « :title ».',
        'share_event'           => ':actor a partagé votre événement « :title ».',
        'share_prayer_request'  => ':actor a partagé votre requête de prière.',
        'share_prayer_session'  => ':actor a partagé votre session de prière « :title ».',
        'prayer_event'          => 'prie pour votre événement « :title ».',
        'prayer_prayer_request' => 'prie pour votre requête de prière.',
        'prayer_prayer_session' => 'prie pour votre session de prière « :title ».',
    ],
];

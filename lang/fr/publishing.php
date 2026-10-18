<?php

// Types de témoignages autorisés (App\Services\TestimonyTypePolicy) — docs/fonctionnalites/types-de-temoignages.md
return [
    'type_disabled' => 'La publication de témoignages :type est désactivée.',
    'type_names' => [
        'text'    => 'texte',
        'audio'   => 'audio',
        'video'   => 'vidéo',
        'youtube' => 'YouTube',
    ],
    // Page de publication du site
    'none_allowed_title' => 'Publication indisponible',
    'none_allowed_text'  => 'Aucun type de témoignage n’est ouvert à la publication pour votre compte pour le moment.',
    // Administration
    'admin' => [
        'section'        => 'Publication',
        'profile_title'  => 'Types de témoignages autorisés',
        'profile_help'   => '« Par défaut » suit les paramètres de l’application (Administration › Paramètres › Publication). Les liens YouTube sont réservés aux administrateurs par défaut.',
        'default'        => 'Par défaut',
        'allowed'        => 'Autorisé',
        'forbidden'      => 'Interdit',
        'effective'      => 'Actuellement : :state',
        'state_allowed'  => 'autorisé',
        'state_forbidden'=> 'interdit',
        'save'           => 'Enregistrer',
        'saved'          => 'Types de témoignages du profil enregistrés.',
        'types' => [
            'text'    => 'Texte',
            'audio'   => 'Audio',
            'video'   => 'Vidéo',
            'youtube' => 'Lien YouTube',
        ],
    ],
    'api' => [
        'updated' => 'Types de témoignages du profil enregistrés.',
    ],
];

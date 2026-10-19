<?php

// Crédit de la vidéo YouTube d'origine (docs/fonctionnalites/videos-youtube.md)
return [
    'section'        => 'Crédit de la vidéo',
    'section_help'   => 'Citez la personne et la chaîne à l’origine de la vidéo.',
    'author'         => 'Auteur de la vidéo',
    'author_hint'    => 'Auteur ou intervenant.',
    'channel'        => 'Chaîne YouTube',
    'url'            => 'Lien de la chaîne',
    'url_hint'       => 'Lien de la chaîne ou de la vidéo d’origine (http ou https).',
    'video_by'       => 'Vidéo de :credit',
    'open_channel'   => 'Voir la chaîne',
    'validation' => [
        'author_max'  => 'L’auteur de la vidéo ne doit pas dépasser 150 caractères.',
        'channel_max' => 'Le nom de la chaîne ne doit pas dépasser 150 caractères.',
        'url_max'     => 'Le lien de la chaîne ne doit pas dépasser 300 caractères.',
        'url_invalid' => 'Le lien de la chaîne doit être une adresse web valide commençant par http:// ou https://.',
        'string'      => 'Ce champ doit être un texte.',
    ],
];

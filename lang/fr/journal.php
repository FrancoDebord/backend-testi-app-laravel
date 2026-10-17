<?php

// Carnet privé (Web\JournalController).
return [
    'types' => [
        'text'  => 'Texte',
        'audio' => 'Audio',
        'video' => 'Vidéo',
    ],
    'share' => [
        'category_required' => 'Choisissez une catégorie pour partager ce témoignage.',
        'category_exists'   => "Cette catégorie n'existe pas. Choisissez-en une autre.",
        'forbidden'         => "Vous n'avez pas la permission de publier.",
    ],
    'flash' => [
        'shared'  => 'Témoignage partagé : il sera publié après relecture par la modération.',
        'stored'  => 'Témoignage rangé dans votre carnet privé : vous seul pouvez le voir.',
        'deleted' => 'Entrée supprimée de votre carnet.',
    ],
];

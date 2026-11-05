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
    // Pages du carnet : resources/views/journal/index.blade.php et partials/tabs
    'index' => [
        'subheader'          => 'Gardez pour vous ce que Dieu a fait, et partagez-le quand vous le souhaitez.',
        'new_entry'          => 'Nouvelle entrée',
        'searching'          => 'Recherche…',
        'search_label'       => 'Rechercher dans mon carnet',
        'search_placeholder' => 'Titre ou texte…',
        'search'             => 'Rechercher',
        'format'             => 'Format',
        'all'                => 'Tous',
        'no_results'         => 'Aucun résultat',
        'no_results_text'    => "Essayez d'autres mots-clés ou retirez le filtre.",
        'empty_title'        => 'Votre carnet est vide',
        'empty_text'         => "Notez ici ce que Dieu fait dans votre vie, en texte, en audio ou en vidéo. Choisissez « Privé » au moment de publier : l'entrée reste dans votre carnet.",
        'clear_filters'      => 'Effacer les filtres',
        'write'              => 'Écrire dans mon carnet',
        'prophecy_question'  => 'Vous avez reçu une parole prophétique ?',
        'prophecy_link'      => 'Gardez-la dans votre carnet',
        'count'              => '{0} :count entrée|{1} :count entrée|[2,*] :count entrées',
    ],
    'tabs' => [
        'testimonies' => 'Témoignages',
        'privacy'     => "Visible de vous seul : ni les autres utilisateurs ni l'équipe de modération ne voient votre carnet. Il est enregistré en ligne et se retrouve aussi dans l'application.",
    ],
];

<?php

// Référencement et publication sur le web (docs/fonctionnalites/referencement.md)
return [
    'anonymous' => 'Témoignage anonyme',

    'form' => [
        'resume'               => 'Résumé écrit (recommandé)',
        'resume_hint'          => 'En quelques phrases : ce que Dieu a fait. Ce texte permet de trouver votre témoignage dans Google.',
        'transcription'        => 'Transcription (facultatif)',
        'transcription_hint'   => 'Le texte de ce que vous dites dans l\'audio ou la vidéo.',
        'publication_web'      => 'Publier aussi sur le web',
        'publication_web_help' => 'Une fois approuvé, votre témoignage pourra être lu sur le site public et trouvé dans Google. Vous pourrez retirer cet accord à tout moment.',
        'anonymous'            => 'Témoigner de façon anonyme sur le site',
        'anonymous_help'       => 'Sur le site, votre nom, votre photo et votre profil ne sont pas affichés : « Témoignage anonyme ».',
    ],

    'author' => [
        'title'                => 'Sur le web',
        'publication_web_help' => 'Décoché : la page n\'est plus publique et Google la retire de ses résultats.',
        'save'                 => 'Enregistrer',
        'saved'                => 'Vos choix de publication sur le web sont enregistrés.',
        'nothing_to_change'    => 'Indiquez publicationWeb ou isAnonymous.',
    ],

    'validation' => [
        'resume_max'        => 'Résumé : 5 000 caractères au plus.',
        'transcription_max' => 'Transcription : 100 000 caractères au plus.',
    ],

    'breadcrumb' => [
        'home'        => 'Accueil',
        'testimonies' => 'Témoignages',
        'daily_verse' => 'Verset du jour',
    ],

    'testimony' => [
        'kind'             => 'Témoignage',
        'description'      => 'Témoignage chrétien (:category) : ce que Dieu a fait, raconté sur My Testimony.',
        'default_category' => 'témoignage',
        'written'          => 'Le témoignage par écrit',
        'transcription'    => 'Transcription complète',
        'theme_verse'      => 'Un verset pour ce témoignage',
        'theme_link'       => 'Thème : :theme',
        'share'            => 'Partager',
    ],

    'category' => [
        'title'       => ':category',
        'count'       => '{0} Aucun témoignage|{1} 1 témoignage|[2,*] :count témoignages',
        'description' => 'Témoignages chrétiens de :category : ce que Dieu a fait dans la vie de ceux qui témoignent.',
        'empty'       => 'Aucun témoignage pour le moment',
        'empty_text'  => 'Les témoignages de cette catégorie apparaîtront ici une fois publiés.',
        'others'      => 'Autres témoignages',
    ],

    'theme' => [
        'kind'        => 'Verset du jour',
        'week'        => 'Semaine du :start au :end',
        'description' => 'Thème « :theme » du verset du jour, verset clé :verse : les versets de la semaine et des témoignages.',
        'key_verse'   => 'Verset clé :',
        'verses'      => 'Les versets de la semaine',
        'testimonies' => 'Derniers témoignages',
    ],

    'app' => [
        'title'   => "Télécharger l'application",
        'text'    => 'Témoignages, prière et verset du jour sur votre téléphone.',
        'android' => 'Android',
        'ios'     => 'iPhone',
    ],

    'admin' => [
        'intro'      => 'Introduction de la page publique',
        'intro_hint' => 'Quelques phrases affichées en haut de la page de la catégorie et lues par Google.',
    ],

    'footer' => [
        'categories' => 'Témoignages par thème',
        'lives'      => 'Directs et replays',
    ],
];

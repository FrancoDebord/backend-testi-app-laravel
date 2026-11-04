<?php

// Recherche : suggestions, recherche de comptes.
return [
    'api' => [
        'recorded' => 'Recherche enregistrée.',
    ],
    'sub' => [
        'organization'        => 'Organisation',
        'organization_in'     => 'Organisation · :city',
        'account'             => 'Compte',
        'testimony'           => 'Témoignage',
        'testimony_by'        => 'Témoignage · :name',
        'date_tbd'            => 'Date à confirmer',
        'event_date_format'   => 'j M Y',
        'event'               => 'Événement',
        'prayer_session'      => 'Session de prière · :date',
        'session_date_format' => 'j M Y, H\\hi',
        'category'            => 'Catégorie',
        'bible'               => '{0} Bible · :count chapitre|{1} Bible · :count chapitre|[2,*] Bible · :count chapitres',
    ],
    // Page de résultats /recherche (docs/fonctionnalites/recherche.md)
    'page' => [
        'title'       => 'Recherche',
        'title_for'   => 'Résultats pour « :q »',
        'subtitle'    => 'Témoignages, comptes, requêtes de prière, événements et sessions de prière.',
        'label'       => 'Rechercher dans tout le site',
        'placeholder' => 'Témoignage, compte, prière, événement…',
        'submit'      => 'Rechercher',
        'searching'   => 'Recherche…',
        'too_short'   => 'Saisissez au moins :min caractères.',
        'start'       => 'Que cherchez-vous ?',
        'start_text'  => 'Un mot du titre ou du texte d\'un témoignage, le nom d\'un compte, d\'un événement, d\'une session de prière.',
        'empty'       => 'Aucun résultat dans cette rubrique.',
        'empty_text'  => 'Essayez un autre mot ou une autre rubrique.',
        'tabs_label'  => 'Rubriques de résultats',
        'tabs' => [
            'temoignages' => 'Témoignages',
            'comptes'     => 'Comptes',
            'prieres'     => 'Requêtes de prière',
            'evenements'  => 'Événements',
            'sessions'    => 'Sessions de prière',
        ],
    ],
];

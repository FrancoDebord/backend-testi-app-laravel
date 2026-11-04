<?php

// Pages d'erreur du site et messages d'erreur de l'API (docs/fonctionnalites/pages-erreur.md).
return [
    'code'          => 'Erreur :code',
    'back_home'     => "Retour à l'accueil",
    'go_back'       => 'Page précédente',
    'reload'        => 'Recharger la page',
    'login'         => 'Se connecter',
    'search_label'  => 'Rechercher un témoignage, un compte, une prière…',
    'search_button' => 'Rechercher',
    'suggestions'   => 'Vous cherchiez peut-être :',
    'links' => [
        'explore'     => 'Explorer les témoignages',
        'prayers'     => 'Requêtes de prière',
        'events'      => 'Événements',
        'lives'       => 'Directs',
    ],

    401 => ['title' => 'Connexion requise',           'text' => 'Connectez-vous pour accéder à cette page.'],
    402 => ['title' => 'Paiement requis',             'text' => "Cette page n'est pas accessible pour le moment."],
    403 => ['title' => 'Accès refusé',                'text' => "Vous n'avez pas l'autorisation d'ouvrir cette page."],
    404 => ['title' => 'Page introuvable',            'text' => "La page demandée n'existe pas ou n'est plus disponible. Le lien est peut-être incomplet, ou le contenu a été retiré."],
    405 => ['title' => 'Action non autorisée',        'text' => "Cette adresse ne peut pas être utilisée de cette façon."],
    410 => ['title' => 'Contenu retiré',              'text' => "Ce contenu a été supprimé et n'est plus disponible."],
    413 => ['title' => 'Fichier trop volumineux',     'text' => 'Le fichier envoyé dépasse la taille autorisée. Réduisez-le, puis réessayez.'],
    419 => ['title' => 'Session expirée',             'text' => 'La page est restée ouverte trop longtemps. Rechargez-la, puis recommencez votre action.'],
    422 => ['title' => 'Action impossible',           'text' => "Cette action ne peut pas être effectuée."],
    429 => ['title' => 'Trop de requêtes',            'text' => 'Vous avez fait beaucoup de demandes en peu de temps. Patientez un instant, puis réessayez.'],
    500 => ['title' => 'Erreur du serveur',           'text' => "Un problème inattendu est survenu de notre côté. L'équipe en est informée ; réessayez dans quelques instants."],
    502 => ['title' => 'Service momentanément indisponible', 'text' => 'Le serveur ne répond pas correctement. Réessayez dans quelques instants.'],
    503 => ['title' => 'Maintenance en cours',        'text' => 'Le site est en cours de mise à jour. Il sera de retour dans quelques minutes.'],
    504 => ['title' => 'Délai dépassé',               'text' => 'Le serveur a mis trop de temps à répondre. Réessayez dans quelques instants.'],
    '4xx' => ['title' => 'Requête impossible',        'text' => "La demande n'a pas pu être traitée."],
    '5xx' => ['title' => 'Erreur du serveur',         'text' => "Un problème inattendu est survenu de notre côté. Réessayez dans quelques instants."],
];

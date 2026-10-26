<?php

// Messages génériques de l'API (App\Traits\ApiResponse, bootstrap/app.php, middlewares).
return [
    'created'                => 'Créé avec succès',
    'unauthorized'           => 'Non autorisé',
    'unauthenticated'        => 'Non authentifié',
    'forbidden'              => 'Accès refusé',
    'not_found'              => 'Ressource introuvable',
    'invalid_data'           => 'Données invalides',
    'acting_as_forbidden'    => "Vous n'administrez plus ce compte.",
    'request_in_progress'    => "Cette action est déjà en cours d'envoi. Réessayez dans un instant.",
    'owner_only'             => 'Réservé au titulaire du compte. Revenez sur votre propre compte pour cette action.',
    'preferences_saved'      => 'Préférences enregistrées.',
    'verse_style' => [
        'unknown_design' => 'Design inconnu.',
        'unknown_font'   => 'Police inconnue.',
        'unknown_size'   => 'Taille de texte inconnue.',
        'unknown_align'  => 'Alignement inconnu.',
        'date_format'    => 'Date au format AAAA-MM-JJ attendue.',
    ],
];

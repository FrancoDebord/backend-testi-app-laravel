<?php

// Utilitaires (app/Support) : affichage des listes, numéro de téléphone.
return [
    'feed_layout' => [
        'cards'   => 'Grandes cartes',
        'compact' => 'Liste compacte',
    ],
    'phone' => [
        'invalid'          => "Numéro de téléphone invalide pour l'indicatif choisi.",
        'taken'            => 'Ce numéro est déjà associé à un autre compte.',
        'required'         => 'Le numéro de téléphone est obligatoire.',
        'required_if'      => 'Le numéro de téléphone est obligatoire pour une organisation.',
        'country_required' => "Choisissez l'indicatif du pays.",
        'country_unknown'  => 'Indicatif inconnu.',
    ],
];

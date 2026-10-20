<?php

/*
|--------------------------------------------------------------------------
| Classement des fils « Pour vous » et « Mon fil » (zones puis goûts)
|--------------------------------------------------------------------------
| Score d'un témoignage = proximité + goûts + fraîcheur + popularité (+ comptes suivis − déjà vu).
| Rien n'est filtré : ces poids réordonnent seulement. Voir docs/fonctionnalites/recommandations.md
| et docs/fonctionnalites/localisation.md
*/

return [
    // Proximité : poids × niveau (0 à 5). Niveaux : 5 même village/quartier ou < near_km ;
    // 4 même ville ou < city_km ; 3 même région ou < region_km ; 2 même pays ; 1 même continent ; 0 monde.
    'weights' => [
        'proximity'  => 1.6,   // un niveau de zone ≈ un point et demi : la zone passe avant les goûts
        'followed'   => 4.5,   // compte suivi (« Pour vous » ; dans Mon fil les suivis sont déjà en tête)
        'category'   => 3.0,   // × intérêt de la catégorie (0 à 1, Recommendations::interests)
        'author'     => 1.0,   // × goût pour l'auteur (0 à 1 : témoignages aimés, priés, enregistrés, commentés)
        'type'       => 1.0,   // × goût pour le type de contenu (vidéo, audio, texte ; 0 à 1)
        'freshness'  => 1.5,   // × fraîcheur (2 : moins de 7 jours, 1 : moins de 30 jours)
        'popularity' => 1.0,   // × popularité (échelle logarithmique des vues, 0 à ~4)
        'seen'       => -3.0,  // déjà vu
    ],

    // Distances (km) quand les deux lieux ont des coordonnées.
    'distance_km' => [
        'near'   => 5,
        'city'   => 30,
        'region' => 150,
    ],

    // Présélection par zone (pas de calcul sur toute la table) : candidats ajoutés au classement.
    'zone_pool' => [
        'city'      => 150,
        'region'    => 150,
        'country'   => 200,
        'continent' => 100,
    ],

    // Longueur de la liste classée ; au-delà, ordre chronologique.
    'ranked_max' => 200,

    // Personne non connectée sans pays dans Accept-Language (« fr-BJ ») : pays par défaut (code ISO) ou null.
    'guest_default_country' => env('RECO_GUEST_COUNTRY'),

    // Géocodage inverse (coordonnées → village/quartier, ville, région, pays) côté serveur.
    // Politique d'usage Nominatim : 1 requête/s au plus, User-Agent identifiable, résultats en cache.
    'geocoder' => [
        'enabled'    => env('GEOCODER_ENABLED', true),
        'url'        => env('GEOCODER_URL', 'https://nominatim.openstreetmap.org/reverse'),
        'user_agent' => env('GEOCODER_USER_AGENT', 'MyTestimony/1.0 (+' . env('APP_URL', 'https://testiapp.org') . ')'),
        'cache_days' => 30,
    ],
];

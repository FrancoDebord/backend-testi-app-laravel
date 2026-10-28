<?php

/*
|--------------------------------------------------------------------------
| Référencement (docs/fonctionnalites/referencement.md)
|--------------------------------------------------------------------------
*/

return [
    // Nom affiché dans les titres des pages : « Guérie d'un cancer – Témoignage | My Testimony ».
    'site_name' => 'My Testimony',

    // Balise de vérification Google Search Console (contenu seul de la balise « google-site-verification »).
    'google_site_verification' => env('GOOGLE_SITE_VERIFICATION'),

    // Bannière « Télécharger l'application » et meta apple-itunes-app (vides : rien n'est affiché).
    'play_store_url'   => env('APP_PLAY_STORE_URL'),
    'app_store_url'    => env('APP_STORE_URL'),
    'ios_app_store_id' => env('IOS_APP_STORE_ID'),

    // Témoignages par page de catégorie et dans les sitemaps (50 000 adresses au plus par fichier).
    'per_page'         => 24,
    'sitemap_max_urls' => 45000,
];

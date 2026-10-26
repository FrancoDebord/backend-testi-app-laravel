<?php

/*
|--------------------------------------------------------------------------
| App Links (Android) / Universal Links (iOS)
|--------------------------------------------------------------------------
|
| Permet à l'application mobile d'ouvrir directement les liens de partage
| (share_url = APP_URL/testimonies/{id}). Servi par les routes
| /.well-known/assetlinks.json et /.well-known/apple-app-site-association.
|
*/

return [

    'android' => [
        // Identifiant de l'application (applicationId) : com.askrea.temoignage depuis le
        // changement de paquet (l'ancien com.airid.testi_app empêchait l'ouverture dans l'app).
        'package' => env('ANDROID_APP_PACKAGE', 'com.askrea.temoignage'),

        // Empreintes SHA-256 des certificats de signature, séparées par des virgules :
        // clé d'envoi (release, alias « upload ») puis clé de debug. Avec Play App Signing,
        // ajouter dans le .env l'empreinte du certificat de signature de Google Play.
        'sha256_fingerprints' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('ANDROID_APP_SHA256_FINGERPRINTS',
                '41:E3:6F:7E:9C:2B:CC:F1:78:5E:0F:32:D6:77:DC:DC:74:2A:14:FF:B5:2A:39:14:07:D3:AD:C4:4C:F5:2D:0A,'
                . '41:2F:F0:5E:D5:EA:CC:32:71:BE:4B:44:1E:3C:54:29:91:0F:36:FA:21:7B:FE:0D:61:8C:6E:61:91:2A:B8:7D'
            ))
        ))),
    ],

    'ios' => [
        // Format : <TEAM_ID>.<bundle id>, ex. ABCDE12345.com.askrea.temoignage
        'app_id' => env('IOS_APP_ID'),
    ],

    // Liens partagés ouverts dans l'application (docs/fonctionnalites/app-links.md).
    'paths' => [
        '/testimonies/*',
        '/videos/*',
        '/lives/*',
        '/evenements/*',
        '/priere/requetes/*',
        '/priere/sessions/*',
        '/verset-du-jour',
        '/verset-du-jour/*',
        '/temoignages-de-conversion',
        '/bible/*', // passages : /bible/LSG/jean/3/16 (docs/fonctionnalites/liens-bible.md)
    ],

];

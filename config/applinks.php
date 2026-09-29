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
        'package' => env('ANDROID_APP_PACKAGE', 'com.airid.testi_app'),

        // Empreintes SHA-256 des certificats de signature, séparées par des
        // virgules. Ajouter celle de la clé release / Play App Signing.
        'sha256_fingerprints' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('ANDROID_APP_SHA256_FINGERPRINTS',
                // Clé de debug actuellement utilisée pour signer l'APK.
                '41:2F:F0:5E:D5:EA:CC:32:71:BE:4B:44:1E:3C:54:29:91:0F:36:FA:21:7B:FE:0D:61:8C:6E:61:91:2A:B8:7D'
            ))
        ))),
    ],

    'ios' => [
        // Format : <TEAM_ID>.<bundle id>, ex. ABCDE12345.com.airid.testiApp
        'app_id' => env('IOS_APP_ID'),
    ],

    'paths' => ['/testimonies/*', '/lives/*'],

];

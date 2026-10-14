<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        // Connexion Google (docs/fonctionnalites/connexion-google.md) : identifiant client Web passé en
        // `serverClientId` par l'application ; audience attendue du jeton. Vide (et client_id vide) = tout jeton refusé.
        'web_client_id' => env('GOOGLE_WEB_CLIENT_ID'),
        // Audiences supplémentaires acceptées (anciennes versions de l'application). Identifiant(s) client Web acceptés comme audience du jeton Google, séparés par des virgules :
        // projet Firebase de com.askrea.temoignage (566166419345-…) et ancien projet (78564751626-…).
        'client_id' => env('GOOGLE_CLIENT_ID', '566166419345-b78faa624rdlcqeigmublhhbseqvhofr.apps.googleusercontent.com,78564751626-m3e7ll7olaj58oce4id71tmrc2q6lkus.apps.googleusercontent.com'),
    ],

    // Notifications push Firebase Cloud Messaging (API HTTP v1) — docs/fonctionnalites/notifications-push.md
    'fcm' => [
        // Chemin du fichier JSON du compte de service Firebase (absolu, ou relatif à la racine du projet).
        'credentials'     => env('FIREBASE_CREDENTIALS'),
        // Facultatif : sinon lu dans le fichier JSON (project_id).
        'project_id'      => env('FIREBASE_PROJECT_ID'),
        // null = activé dès que le fichier de compte de service est lisible.
        'enabled'         => env('FCM_ENABLED'),
        // Canal Android créé par l'application (lib/services/fcm_service.dart).
        'android_channel' => env('FCM_ANDROID_CHANNEL', 'testi_notifications'),
        'timeout'         => (int) env('FCM_TIMEOUT', 10),
        // queue (défaut) : file d'attente, traitée par un queue:work (tâche cron chaque minute sur LWS) ;
        // after_response : envoi juste après la réponse HTTP, sans file ni nouvel essai (solution de repli).
        'delivery'        => env('PUSH_DELIVERY', 'queue'),
        // File des push, séparée des conversions vidéo : queue:work --queue=push,default
        'queue'           => env('PUSH_QUEUE', 'push'),
    ],

    // Vidéos des témoignages hébergées par Bunny Stream (docs/fonctionnalites/videos-bunny-stream.md).
    // Les fichiers vont directement de l'application à Bunny (TUS) : ils ne passent jamais par ce serveur.
    'bunny_stream' => [
        'library_id' => env('BUNNY_STREAM_LIBRARY_ID'),
        'api_key'    => env('BUNNY_STREAM_API_KEY'),
        // Nom d'hôte du CDN de la bibliothèque, sans https:// (ex. vz-xxxxxxxx-xxx.b-cdn.net).
        // BUNNY_STREAM_CDN_HOSTNAME accepté aussi (nom affiché par le tableau de bord Bunny).
        'cdn_host'   => env('BUNNY_STREAM_CDN_HOST', env('BUNNY_STREAM_CDN_HOSTNAME')),
        // Option « MP4 Fallback » activée dans la bibliothèque : liens play_XXXp.mp4 (mp4_url, aperçus du site).
        'mp4_fallback' => (bool) env('BUNNY_STREAM_MP4_FALLBACK', true),
    ],

];

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
        'client_id' => env('GOOGLE_CLIENT_ID', '78564751626-m3e7ll7olaj58oce4id71tmrc2q6lkus.apps.googleusercontent.com'),
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
    ],

];

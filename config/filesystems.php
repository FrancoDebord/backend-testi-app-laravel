<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            // false : Laravel enregistrerait sinon sa propre route storage/{path}
            // (disque privé), qui masquerait celle qui sert les fichiers envoyés
            // (disque public) quand le lien public/storage manque sur le serveur.
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // Enregistrements des directs (docs/fonctionnalites/lives.md), déposés par LiveKit Egress.
        // Utilisé pour produire leurs versions allégées (docs/fonctionnalites/qualites-media.md) :
        // demande le paquet league/flysystem-aws-s3-v3. Les URL publiques sont construites avec
        // LIVEKIT_RECORDING_PUBLIC_URL (MediaFile::urlFor).
        'recordings' => [
            'driver' => 's3',
            'key' => env('LIVEKIT_RECORDING_S3_KEY'),
            'secret' => env('LIVEKIT_RECORDING_S3_SECRET'),
            'region' => env('LIVEKIT_RECORDING_S3_REGION', 'auto'),
            'bucket' => env('LIVEKIT_RECORDING_S3_BUCKET'),
            'url' => env('LIVEKIT_RECORDING_PUBLIC_URL'),
            'endpoint' => env('LIVEKIT_RECORDING_S3_ENDPOINT') ?: null,
            'use_path_style_endpoint' => (bool) env('LIVEKIT_RECORDING_S3_PATH_STYLE', false),
            // « public » si la lecture publique du bucket repose sur les ACL (AWS) ; vide pour R2 / B2.
            'visibility' => env('LIVEKIT_RECORDING_S3_VISIBILITY') ?: null,
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];

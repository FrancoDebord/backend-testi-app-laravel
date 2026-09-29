<?php

/*
|--------------------------------------------------------------------------
| LiveKit — témoignages en direct
|--------------------------------------------------------------------------
|
| Service vidéo temps réel (WebRTC). Laravel délivre les jetons d'accès,
| enregistre commentaires et réactions, et pilote les salles via l'API
| serveur de LiveKit. Voir docs/fonctionnalites/lives.md
|
*/

return [

    // Adresse WebSocket du serveur LiveKit (ex. wss://testiapp-xxxx.livekit.cloud)
    'url' => env('LIVEKIT_URL',"wss://testiapp-kmbvpizi.livekit.cloud"),

    // Clés du projet LiveKit (Cloud : Settings → Keys)
    'api_key'    => env('LIVEKIT_API_KEY',"APIXW7THBUkyHeZ"),
    'api_secret' => env('LIVEKIT_API_SECRET',"U3KC5K6GiI2WCX3jJCNF1pjejGKPVgzeA4WhIrl26j3"),

    // Durée de validité des jetons (secondes)
    'host_token_ttl'   => (int) env('LIVEKIT_HOST_TOKEN_TTL', 4 * 3600),
    'viewer_token_ttl' => (int) env('LIVEKIT_VIEWER_TOKEN_TTL', 3 * 3600),

    // Salle conservée ce délai si le diffuseur perd la connexion (secondes)
    'empty_timeout' => (int) env('LIVEKIT_EMPTY_TIMEOUT', 300),

    // Nombre maximal de participants par salle (0 = illimité)
    'max_participants' => (int) env('LIVEKIT_MAX_PARTICIPANTS', 0),

    // Limites anti-abus
    'comment_max_length'   => 500,
    'comments_per_window'  => 5,    // commentaires…
    'comments_window'      => 30,   // …par tranche de N secondes, par personne
    'reactions_per_window' => 20,
    'reactions_window'     => 10,

    // Un direct resté « en préparation » plus longtemps est clôturé (minutes)
    'preparing_timeout' => 30,

    // Intervenants (docs/fonctionnalites/lives-intervenants.md) : un seul à l'antenne à la fois.
    'stage_invite_timeout'      => 60,  // secondes pour accepter une invitation
    'stage_message_length'      => 200, // sujet annoncé avec la demande
    'stage_requests_per_window' => 3,   // demandes…
    'stage_requests_window'     => 300, // …par tranche de N secondes, par personne

    /*
    | Enregistrement des directs (LiveKit Egress) → témoignage vidéo à relire.
    | LiveKit dépose le MP4 dans un stockage compatible S3 (Cloudflare R2, AWS S3,
    | Backblaze B2…). La vidéo est ensuite lue depuis « public_url ».
    */
    'recording' => [
        'enabled'      => (bool) env('LIVEKIT_RECORDING_ENABLED', true),
        'layout'       => env('LIVEKIT_RECORDING_LAYOUT', 'speaker'),
        'preset'       => env('LIVEKIT_RECORDING_PRESET', 'H264_720P_30'),
        'path_prefix'  => env('LIVEKIT_RECORDING_PATH_PREFIX', 'lives'),
        'min_duration' => (int) env('LIVEKIT_RECORDING_MIN_DURATION', 20), // secondes : en dessous, pas de témoignage
        'public_url'   => env('LIVEKIT_RECORDING_PUBLIC_URL'),              // ex. https://videos.exemple.org
        's3' => [
            'access_key'       => env('LIVEKIT_RECORDING_S3_KEY'),
            'secret'           => env('LIVEKIT_RECORDING_S3_SECRET'),
            'bucket'           => env('LIVEKIT_RECORDING_S3_BUCKET'),
            'region'           => env('LIVEKIT_RECORDING_S3_REGION', 'auto'),
            'endpoint'         => env('LIVEKIT_RECORDING_S3_ENDPOINT'),  // vide pour AWS S3
            'force_path_style' => (bool) env('LIVEKIT_RECORDING_S3_PATH_STYLE', false),
        ],
    ],

];

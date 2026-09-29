<?php

/*
|--------------------------------------------------------------------------
| Qualités des médias (docs/fonctionnalites/qualites-media.md)
|--------------------------------------------------------------------------
|
| Après l'envoi d'un fichier audio ou vidéo, une tâche en file d'attente
| (App\Jobs\TranscodeMediaJob) produit des versions plus légères avec ffmpeg.
| Le fichier original reste toujours lisible (mediaUrl).
|
*/

return [

    // Exécutables (chemin complet si non présents dans le PATH du serveur).
    'ffmpeg_binary'  => env('FFMPEG_BINARY', 'ffmpeg'),
    'ffprobe_binary' => env('FFPROBE_BINARY', 'ffprobe'),

    // Désactiver pour ne plus lancer de conversion à l'envoi (ex. hébergement sans ffmpeg).
    'transcoding_enabled' => (bool) env('MEDIA_TRANSCODING_ENABLED', true),

    // Durée maximale d'une conversion (et de la tâche), en secondes.
    // DB_QUEUE_RETRY_AFTER doit rester supérieur à cette valeur.
    'timeout' => (int) env('MEDIA_TRANSCODE_TIMEOUT', 1800),

    // Dossier de travail des fichiers d'un disque non local (enregistrements de directs sur S3) :
    // l'original y est téléchargé, converti, puis les versions sont envoyées sur le disque d'origine.
    // Prévoir l'espace libre d'un original et d'une version. Vidé après chaque conversion.
    'temp_directory' => env('MEDIA_TEMP_DIR') ?: storage_path('app/transcode'),

    // Échelle vidéo : hauteur (petit côté de l'image) => débit vidéo maximal (kbit/s).
    // Seules les hauteurs inférieures ou égales à la source sont produites ; la plus basse l'est toujours.
    'video_ladder' => [
        240 => 400,
        360 => 800,
        480 => 1200,
        720 => 2500,
    ],

    // Débit audio de la piste son des versions vidéo (kbit/s).
    'video_audio_bitrate' => 96,

    // Débits audio (kbit/s). Les débits non inférieurs à celui de la source sont ignorés.
    'audio_bitrates' => [32, 64, 128],

];

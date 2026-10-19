<?php

// Paramètres globaux (App\Services\SettingsSchema) — mêmes groupes, libellés et descriptions sur le site
// (Administration › Paramètres) et dans l'application (GET /api/v1/admin/settings/schema).
// Voir docs/fonctionnalites/parametres-globaux.md
return [
    'groups' => [
        'app'          => 'Général',
        'publishing'   => 'Publication',
        'moderation'   => 'Modération et limites',
        'features'     => 'Fonctionnalités',
        'notifications' => 'Notifications',
        'registration' => 'Inscriptions',
    ],
    'items' => [
        'app_name' => [
            'label'       => 'Nom de l’application',
        ],
        'app_tagline' => [
            'label'       => 'Slogan',
        ],
        'app_version' => [
            'label'       => 'Version',
            'description' => 'Version affichée aux utilisateurs.',
        ],
        'maintenance_mode' => [
            'label'       => 'Mode maintenance',
            'description' => 'Signale une maintenance en cours.',
        ],
        'testimony_type_text_enabled' => [
            'label'       => 'Témoignages écrits (texte)',
            'description' => 'Autorise la publication de témoignages écrits. Une exception peut être définie sur chaque profil.',
        ],
        'testimony_type_audio_enabled' => [
            'label'       => 'Témoignages audio',
            'description' => 'Autorise la publication de témoignages audio. Une exception peut être définie sur chaque profil.',
        ],
        'testimony_type_video_enabled' => [
            'label'       => 'Témoignages vidéo',
            'description' => 'Autorise la publication de témoignages vidéo. Une exception peut être définie sur chaque profil.',
        ],
        'testimony_type_youtube_enabled' => [
            'label'       => 'Liens YouTube',
            'description' => 'Réservés aux administrateurs, et aux profils autorisés.',
        ],
        'auto_approve_testimonies' => [
            'label'       => 'Approuver automatiquement les témoignages',
        ],
        'max_testimony_length' => [
            'label'       => 'Longueur maximale du texte',
            'description' => 'En caractères.',
        ],
        'max_audio_duration_sec' => [
            'label'       => 'Durée audio maximale',
            'description' => 'En secondes.',
        ],
        'max_video_duration_sec' => [
            'label'       => 'Durée vidéo maximale',
            'description' => 'En secondes.',
        ],
        'max_file_size_mb' => [
            'label'       => 'Taille maximale d’un fichier',
            'description' => 'En mégaoctets (Mo).',
        ],
        'allow_comments' => [
            'label'       => 'Autoriser les commentaires',
        ],
        'allow_reactions' => [
            'label'       => 'Autoriser les réactions',
        ],
        'allow_sharing' => [
            'label'       => 'Autoriser le partage',
        ],
        'require_consent_form' => [
            'label'       => 'Formulaire de consentement obligatoire',
        ],
        'push_notifications_enabled' => [
            'label'       => 'Notifications push',
            'description' => 'Désactivé : plus aucune notification push n’est envoyée (les notifications restent visibles dans l’application et sur le site).',
        ],
        'allow_registration' => [
            'label'       => 'Autoriser les inscriptions',
        ],
        'default_role' => [
            'label'       => 'Rôle par défaut',
            'description' => 'Rôle donné aux nouveaux comptes.',
        ],
        'require_email_verification' => [
            'label'       => 'Vérification de l’adresse e-mail obligatoire',
        ],
    ],
    'validation' => [
        'unknown'  => 'Paramètre inconnu : :key.',
        'boolean'  => 'Le paramètre :key doit valoir vrai ou faux.',
        'integer'  => 'Le paramètre :key doit être un nombre entier.',
        'string'   => 'Le paramètre :key doit être un texte.',
        'json'     => 'Le paramètre :key doit être un JSON valide.',
        'too_long' => 'Le paramètre :key est trop long.',
        'empty'    => 'Aucun paramètre à enregistrer.',
    ],
];

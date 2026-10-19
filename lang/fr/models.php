<?php

// Libellés portés par les modèles (app/Models) : motifs, phases, états.
return [
    'daily_verse_theme' => [
        'faithfulness' => 'Fidélité de Dieu',
        'testimonies'  => 'Importance des témoignages',
        'promises'     => 'Promesses de Dieu',
        'obedience'    => "Vie d'obéissance",
        'default'      => 'Méditation du jour',
    ],
    'event_report_reasons' => [
        'not_christian'         => 'Pas un événement chrétien',
        'fake'                  => "Événement faux / n'existe pas",
        'inappropriate_content' => 'Contenu inapproprié',
        'scam'                  => "Arnaque / demande d'argent",
        'other'                 => 'Autre',
    ],
    'event_phase' => [
        'cancelled' => 'Annulé',
        'tbd'       => 'Reporté — date à confirmer',
        'past'      => 'Terminé',
        'ongoing'   => 'En cours',
        'upcoming'  => 'À venir',
    ],
    'event_attended' => [
        'yes'    => 'Oui, il a eu lieu',
        'no'     => "Non, il n'a pas eu lieu",
        'absent' => "Je n'y étais pas",
    ],
    'live_sources' => [
        'browser' => 'Caméra de cet appareil',
        'rtmp'    => 'Caméra IP ou encodeur (RTMP)',
        'url'     => 'Adresse du flux de la caméra',
        'youtube' => 'Lien YouTube',
    ],
    'live_recording' => [
        'recording'      => 'Enregistrement en cours',
        'processing'     => 'Vidéo en cours de finalisation',
        'ready'          => 'Rediffusion disponible',
        'ready_pending'  => 'Vidéo en attente de relecture',
        'too_short'      => 'Direct trop court : pas de vidéo créée',
        'failed'         => "Échec de l'enregistrement",
        'default'        => "Sera enregistré au passage à l'antenne",
    ],
    'prayer_request_report_reasons' => [
        'inappropriate_content' => 'Contenu inapproprié',
        'hate_speech'           => 'Propos haineux',
        'spam'                  => 'Publicité ou spam',
        'other'                 => 'Autre raison',
    ],
    'prayer_request_visibility' => [
        'public'    => 'Tout le monde',
        'followers' => 'Mes abonnés',
        'private'   => 'Moi seul',
    ],
    'prayer_session_visibility' => [
        'public'    => 'Tout le monde',
        'followers' => 'Mes abonnés',
    ],
    'prayer_session_phase' => [
        'upcoming'  => 'À venir',
        'live'      => 'En cours',
        'ended'     => 'Terminée',
        'cancelled' => 'Annulée',
    ],
    'verification_request_status' => [
        'pending'  => 'En attente',
        'approved' => 'Approuvée',
        'rejected' => 'Refusée',
        'revoked'  => 'Vérification retirée',
    ],
    'prophecy_received_at' => ':date à :time',
];

<?php

// Paroles prophétiques du carnet (Prophecies, ProphecyController, SyncProphecyReminderJob).
return [
    'not_found'       => 'Parole introuvable.',
    'stays_fulfilled' => 'Un témoignage est déjà publié pour cette parole : elle reste accomplie.',
    'reminder_title'  => 'Parole du :date',
    'reminder_date_format' => 'j/n/Y',
    'weekdays' => [
        '1' => 'Lundi',
        '2' => 'Mardi',
        '3' => 'Mercredi',
        '4' => 'Jeudi',
        '5' => 'Vendredi',
        '6' => 'Samedi',
        '7' => 'Dimanche',
    ],
    'rules' => [
        'body_text_required'        => 'Écrivez la parole ou enregistrez-la en audio.',
        'reminder_time_required'    => "Choisissez l'heure du rappel.",
        'reminder_time_format'      => 'Heure du rappel au format HH:MM.',
        'received_time_format'      => 'Heure de la parole au format HH:MM.',
        'reminder_weekday_required' => 'Choisissez le jour du rappel.',
        'due_on_after_received'     => "L'échéance ne peut pas précéder la date de la parole.",
        'fulfilled_on_future'       => "La date d'accomplissement ne peut pas être dans le futur.",
        'audio_max'                 => "L'enregistrement ne doit pas dépasser 20 Mo.",
        'audio_mimetypes'           => 'Le fichier doit être un enregistrement audio (MP3, M4A, WAV…).',
    ],
    'flash' => [
        'created'        => 'Parole enregistrée dans votre carnet.',
        'updated'        => 'Parole mise à jour.',
        'deleted'        => 'Parole supprimée de votre carnet.',
        'fulfilled'      => 'Gloire à Dieu ! La parole est marquée accomplie.',
        'reopened'       => 'La parole est de nouveau en attente.',
        'prayed'         => "Prière enregistrée. Dieu veille sur sa parole pour l'accomplir.",
        'prayer_deleted' => 'Prière retirée du journal.',
    ],
    'api' => [
        'deleted' => 'Parole supprimée.',
    ],
];

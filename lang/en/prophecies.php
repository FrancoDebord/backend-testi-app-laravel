<?php

// Prophetic words of the journal (Prophecies, ProphecyController, SyncProphecyReminderJob).
return [
    'not_found'       => 'Prophetic word not found.',
    'stays_fulfilled' => 'A testimony is already published for this word: it stays fulfilled.',
    'reminder_title'  => 'Word of :date',
    'reminder_date_format' => 'n/j/Y',
    'weekdays' => [
        '1' => 'Monday',
        '2' => 'Tuesday',
        '3' => 'Wednesday',
        '4' => 'Thursday',
        '5' => 'Friday',
        '6' => 'Saturday',
        '7' => 'Sunday',
    ],
    'rules' => [
        'body_text_required'        => 'Write the word or record it as audio.',
        'reminder_time_required'    => 'Choose the reminder time.',
        'reminder_time_format'      => 'Reminder time in HH:MM format.',
        'received_time_format'      => 'Time of the word in HH:MM format.',
        'reminder_weekday_required' => 'Choose the reminder day.',
        'due_on_after_received'     => 'The due date cannot be before the date of the word.',
        'fulfilled_on_future'       => 'The fulfilment date cannot be in the future.',
        'audio_max'                 => 'The recording must not exceed 20 MB.',
        'audio_mimetypes'           => 'The file must be an audio recording (MP3, M4A, WAV…).',
    ],
    'flash' => [
        'created'        => 'Word saved in your journal.',
        'updated'        => 'Word updated.',
        'deleted'        => 'Word removed from your journal.',
        'fulfilled'      => 'Glory to God! The word is marked as fulfilled.',
        'reopened'       => 'The word is pending again.',
        'prayed'         => 'Prayer saved. God watches over his word to perform it.',
        'prayer_deleted' => 'Prayer removed from the journal.',
    ],
    'api' => [
        'deleted' => 'Word deleted.',
    ],
];

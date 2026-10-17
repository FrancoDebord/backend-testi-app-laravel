<?php

// Labels carried by the models (app/Models): reasons, phases, states.
return [
    'daily_verse_theme' => [
        'faithfulness' => "God's faithfulness",
        'testimonies'  => 'The importance of testimonies',
        'promises'     => "God's promises",
        'obedience'    => 'A life of obedience',
        'default'      => "Today's meditation",
    ],
    'event_report_reasons' => [
        'not_christian'         => 'Not a Christian event',
        'fake'                  => 'Fake event / does not exist',
        'inappropriate_content' => 'Inappropriate content',
        'scam'                  => 'Scam / request for money',
        'other'                 => 'Other',
    ],
    'event_phase' => [
        'cancelled' => 'Cancelled',
        'tbd'       => 'Postponed — date to be confirmed',
        'past'      => 'Ended',
        'ongoing'   => 'In progress',
        'upcoming'  => 'Upcoming',
    ],
    'event_attended' => [
        'yes'    => 'Yes, it took place',
        'no'     => 'No, it did not take place',
        'absent' => 'I was not there',
    ],
    'live_sources' => [
        'browser' => "This device's camera",
        'rtmp'    => 'IP camera or encoder (RTMP)',
        'url'     => 'Camera stream address',
    ],
    'live_recording' => [
        'recording'      => 'Recording in progress',
        'processing'     => 'Video being finalized',
        'ready'          => 'Replay available',
        'ready_pending'  => 'Video awaiting review',
        'too_short'      => 'Live too short: no video created',
        'failed'         => 'Recording failed',
        'default'        => 'Will be recorded once on air',
    ],
    'prayer_request_report_reasons' => [
        'inappropriate_content' => 'Inappropriate content',
        'hate_speech'           => 'Hate speech',
        'spam'                  => 'Advertising or spam',
        'other'                 => 'Other reason',
    ],
    'prayer_request_visibility' => [
        'public'    => 'Everyone',
        'followers' => 'My followers',
        'private'   => 'Only me',
    ],
    'prayer_session_visibility' => [
        'public'    => 'Everyone',
        'followers' => 'My followers',
    ],
    'prayer_session_phase' => [
        'upcoming'  => 'Upcoming',
        'live'      => 'In progress',
        'ended'     => 'Ended',
        'cancelled' => 'Cancelled',
    ],
    'verification_request_status' => [
        'pending'  => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Declined',
        'revoked'  => 'Verification withdrawn',
    ],
    'prophecy_received_at' => ':date at :time',
];

<?php

// Prayer time (App\Services\PrayerFocus, components/prayer-focus-modal component). See docs/fonctionnalites/temps-de-priere.md
return [
    // Errors (422)
    'required'     => 'Take time to pray before confirming your prayer.',
    'not_started'  => 'Start the prayer time ("I pray") before confirming your prayer.',
    'too_early'    => 'Your prayer time is not over yet: :time left.',
    'time_min_sec' => ':min min :sec s',
    'time_sec'     => ':sec s',
    'started'      => 'Prayer time started.',
    'not_found'    => 'Testimony not found.',

    // Website "Prayer time" screen
    'modal' => [
        'title'         => 'Prayer time',
        'subtitle'      => 'Take a few minutes to truly pray.',
        'for_request'   => 'You are praying for this request',
        'for_testimony' => 'You are praying for this testimony',
        'for_verse'     => 'You are praying with the verse of the day',
        'for_prophecy'  => 'You are praying over this word',
        'for_event'     => 'You are praying for this event',
        'for_session'   => 'You are praying for this prayer session',
        'remaining'     => 'remaining',
        'paused'        => 'Paused: come back to this tab to continue.',
        'done'          => 'Time is up. Amen!',
        'verse_text'    => '"Pray for one another, that you may be healed. The effective, fervent prayer of a righteous man avails much."',
        'verse_ref'     => 'James 5:16',
        'confirm_request'   => 'I prayed for this request',
        'confirm_testimony' => 'I prayed for this testimony',
        'confirm_verse'     => 'I prayed with this verse',
        'confirm_prophecy'  => 'I prayed over this word',
        'confirm_event'     => 'I prayed for this event',
        'confirm_session'   => 'I prayed for this session',
        'validate'      => 'Confirm my prayer',
        'validating'    => 'Saving…',
        'close'         => 'Leave without saving',
        'starting'      => 'Getting ready…',
        'wait_hint'     => 'The button is enabled when the countdown ends.',
    ],
];

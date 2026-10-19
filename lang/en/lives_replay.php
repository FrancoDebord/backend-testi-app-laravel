<?php

// Past lives: replay the chat, testify after the end (docs/fonctionnalites/lives.md)
return [
    'validation' => [
        'live_invalid'   => 'The live is invalid.',
        'live_not_found' => 'This live could not be found.',
        'live_not_ended' => 'You can testify about this live once it has ended.',
    ],
    'chat' => [
        'title'    => 'Replay the chat',
        'count'    => '{0} No messages|{1} 1 message|[2,*] :count messages',
        'empty'    => 'No messages were left during this live.',
        'at'       => 'at :time into the live',
        'previous' => 'Earlier messages',
        'next'     => 'Later messages',
        'host'     => 'Host',
    ],
    'testify' => [
        'button'    => 'Share a testimony about this live',
        'help'      => 'Did this live touch you? Share what God has done.',
        'login'     => 'Sign in to testify about this live.',
        'banner'    => 'You are testifying about the live',
        'remove'    => 'Do not link this testimony to the live',
    ],
    'testimonies' => [
        'title' => 'Testimonies about this live',
        'count' => '{0} Testimonies about this live|{1} 1 testimony about this live|[2,*] :count testimonies about this live',
        'empty' => 'No testimonies published about this live yet.',
        'more'  => 'See more',
    ],
    'linked' => 'Testimony about the live',

    // Summary of an ended live, like the app's end screen (LiveEndedView)
    'summary' => [
        'badge'       => 'ENDED',
        'ended'       => 'Live ended',
        'your_ended'  => 'Your live has ended',
        'numbers'     => 'In numbers',
        'host_numbers'=> 'Your live summary',
        'reactions'   => 'Reactions',
        'stats' => [
            'duration'     => 'Duration',
            'peak_viewers' => 'Peak viewers',
            'comments'     => 'Comments',
            'reactions'    => 'Reactions',
        ],
        'reasons' => [
            'host'       => 'Ended by the host',
            'moderator'  => 'Stopped by moderation',
            'connection' => 'Interrupted (connection lost)',
            'abandoned'  => 'Never went on air',
            'default'    => 'Thank you for watching this testimony.',
        ],
    ],
    // "Recent" list on /lives (like the app's _RecentTile)
    'recent' => [
        'viewers'  => '{1} 1 viewer|[0,*] :count viewers',
        'comments' => '{1} 1 comment|[0,*] :count comments',
        'open'     => 'See the live summary',
    ],
];

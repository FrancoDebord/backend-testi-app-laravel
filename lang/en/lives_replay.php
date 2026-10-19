<?php

// Past lives: replay the chat, testify after the end (docs/fonctionnalites/lives.md)
return [
    'validation' => [
        'live_invalid'   => 'The live is invalid.',
        'live_not_found' => 'This live could not be found.',
        'live_not_ended' => 'You can testify about this live once it has ended.',
    ],
    'chat' => [
        'title'    => 'Chat replay',
        'count'    => '{0} No messages|{1} 1 message|[2,*] :count messages',
        'empty'    => 'No messages were written during this live.',
        'at'       => 'at :time into the live',
        'previous' => 'Earlier messages',
        'next'     => 'Later messages',
        'host'     => 'Host',
    ],
    'testify' => [
        'button'    => 'Testify about this live',
        'help'      => 'Did this live touch you? Share what God has done.',
        'login'     => 'Sign in to testify about this live.',
        'banner'    => 'You are testifying about the live',
        'remove'    => 'Do not link this testimony to the live',
    ],
    'testimonies' => [
        'title' => 'Testimonies about this live',
        'empty' => 'No testimonies published about this live yet.',
        'more'  => 'See more',
    ],
    'linked' => 'Testimony about the live',
];

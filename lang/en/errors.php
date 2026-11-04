<?php

// Site error pages and API error messages (docs/fonctionnalites/pages-erreur.md).
return [
    'code'          => 'Error :code',
    'back_home'     => 'Back to home',
    'go_back'       => 'Previous page',
    'reload'        => 'Reload the page',
    'login'         => 'Sign in',
    'search_label'  => 'Search a testimony, an account, a prayer…',
    'search_button' => 'Search',
    'suggestions'   => 'You may be looking for:',
    'links' => [
        'explore'     => 'Explore testimonies',
        'prayers'     => 'Prayer requests',
        'events'      => 'Events',
        'lives'       => 'Lives',
    ],

    401 => ['title' => 'Sign-in required',            'text' => 'Sign in to access this page.'],
    402 => ['title' => 'Payment required',            'text' => 'This page is not available at the moment.'],
    403 => ['title' => 'Access denied',               'text' => 'You are not allowed to open this page.'],
    404 => ['title' => 'Page not found',              'text' => 'The page you requested does not exist or is no longer available. The link may be incomplete, or the content was removed.'],
    405 => ['title' => 'Action not allowed',          'text' => 'This address cannot be used this way.'],
    410 => ['title' => 'Content removed',             'text' => 'This content was deleted and is no longer available.'],
    413 => ['title' => 'File too large',              'text' => 'The file you sent is larger than allowed. Make it smaller, then try again.'],
    419 => ['title' => 'Session expired',             'text' => 'The page stayed open too long. Reload it, then try your action again.'],
    422 => ['title' => 'Action not possible',         'text' => 'This action cannot be performed.'],
    429 => ['title' => 'Too many requests',           'text' => 'You made many requests in a short time. Wait a moment, then try again.'],
    500 => ['title' => 'Server error',                'text' => 'An unexpected problem occurred on our side. The team has been notified; try again in a few moments.'],
    502 => ['title' => 'Service temporarily unavailable', 'text' => 'The server is not responding properly. Try again in a few moments.'],
    503 => ['title' => 'Maintenance in progress',     'text' => 'The site is being updated. It will be back in a few minutes.'],
    504 => ['title' => 'Timeout',                     'text' => 'The server took too long to respond. Try again in a few moments.'],
    '4xx' => ['title' => 'Request not possible',      'text' => 'The request could not be processed.'],
    '5xx' => ['title' => 'Server error',              'text' => 'An unexpected problem occurred on our side. Try again in a few moments.'],
];

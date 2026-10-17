<?php

// Generic API messages (App\Traits\ApiResponse, bootstrap/app.php, middlewares).
return [
    'created'                => 'Created successfully',
    'unauthorized'           => 'Unauthorized',
    'unauthenticated'        => 'Unauthenticated',
    'forbidden'              => 'Access denied',
    'not_found'              => 'Resource not found',
    'invalid_data'           => 'Invalid data',
    'acting_as_forbidden'    => 'You no longer manage this account.',
    'owner_only'             => 'Reserved for the account holder. Switch back to your own account for this action.',
    'preferences_saved'      => 'Preferences saved.',
    'verse_style' => [
        'unknown_design' => 'Unknown design.',
        'unknown_font'   => 'Unknown font.',
        'unknown_size'   => 'Unknown text size.',
        'unknown_align'  => 'Unknown alignment.',
        'date_format'    => 'Date expected in YYYY-MM-DD format.',
    ],
];

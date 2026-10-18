<?php

// Allowed testimony types (App\Services\TestimonyTypePolicy) — docs/fonctionnalites/types-de-temoignages.md
return [
    'type_disabled' => 'Publishing :type testimonies is disabled.',
    'type_names' => [
        'text'    => 'text',
        'audio'   => 'audio',
        'video'   => 'video',
        'youtube' => 'YouTube',
    ],
    // Website publish page
    'none_allowed_title' => 'Publishing unavailable',
    'none_allowed_text'  => 'No testimony type is open for publishing on your account at the moment.',
    // Administration
    'admin' => [
        'section'        => 'Publishing',
        'profile_title'  => 'Allowed testimony types',
        'profile_help'   => '“Default” follows the application settings (Administration › Settings › Publishing). YouTube links are reserved to administrators by default.',
        'default'        => 'Default',
        'allowed'        => 'Allowed',
        'forbidden'      => 'Forbidden',
        'effective'      => 'Currently: :state',
        'state_allowed'  => 'allowed',
        'state_forbidden'=> 'forbidden',
        'save'           => 'Save',
        'saved'          => 'Profile testimony types saved.',
        'types' => [
            'text'    => 'Text',
            'audio'   => 'Audio',
            'video'   => 'Video',
            'youtube' => 'YouTube link',
        ],
    ],
    'api' => [
        'updated' => 'Profile testimony types saved.',
    ],
];

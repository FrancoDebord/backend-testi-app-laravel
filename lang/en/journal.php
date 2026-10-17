<?php

// Private journal (Web\JournalController).
return [
    'types' => [
        'text'  => 'Text',
        'audio' => 'Audio',
        'video' => 'Video',
    ],
    'share' => [
        'category_required' => 'Choose a category to share this testimony.',
        'category_exists'   => 'This category does not exist. Choose another one.',
        'forbidden'         => 'You are not allowed to publish.',
    ],
    'flash' => [
        'shared'  => 'Testimony shared: it will be published after review by the moderation team.',
        'stored'  => 'Testimony moved to your private journal: only you can see it.',
        'deleted' => 'Entry removed from your journal.',
    ],
];

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
    // Journal pages: resources/views/journal/index.blade.php and partials/tabs
    'index' => [
        'subheader'          => 'Keep what God has done to yourself, and share it whenever you wish.',
        'new_entry'          => 'New entry',
        'searching'          => 'Searching…',
        'search_label'       => 'Search my journal',
        'search_placeholder' => 'Title or text…',
        'search'             => 'Search',
        'format'             => 'Format',
        'all'                => 'All',
        'no_results'         => 'No results',
        'no_results_text'    => 'Try other keywords or remove the filter.',
        'empty_title'        => 'Your journal is empty',
        'empty_text'         => 'Write down here what God is doing in your life, as text, audio or video. Choose "Private" when publishing: the entry stays in your journal.',
        'clear_filters'      => 'Clear filters',
        'write'              => 'Write in my journal',
        'prophecy_question'  => 'Have you received a prophetic word?',
        'prophecy_link'      => 'Keep it in your journal',
        'count'              => '{0} :count entries|{1} :count entry|[2,*] :count entries',
    ],
    'tabs' => [
        'testimonies' => 'Testimonies',
        'privacy'     => 'Visible only to you: neither other users nor the moderation team can see your journal. It is saved online and is also available in the app.',
    ],
];

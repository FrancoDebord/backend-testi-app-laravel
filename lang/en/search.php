<?php

// Search: suggestions, account search.
return [
    'api' => [
        'recorded' => 'Search saved.',
    ],
    'sub' => [
        'organization'        => 'Organization',
        'organization_in'     => 'Organization · :city',
        'account'             => 'Account',
        'testimony'           => 'Testimony',
        'testimony_by'        => 'Testimony · :name',
        'date_tbd'            => 'Date to be confirmed',
        'event_date_format'   => 'M j, Y',
        'event'               => 'Event',
        'prayer_session'      => 'Prayer session · :date',
        'session_date_format' => 'M j, Y, g:i A',
        'category'            => 'Category',
        'bible'               => '{0} Bible · :count chapters|{1} Bible · :count chapter|[2,*] Bible · :count chapters',
    ],
    // Results page /recherche (docs/fonctionnalites/recherche.md)
    'page' => [
        'title'       => 'Search',
        'title_for'   => 'Results for “:q”',
        'subtitle'    => 'Testimonies, accounts, prayer requests, events and prayer sessions.',
        'label'       => 'Search the whole site',
        'placeholder' => 'Testimony, account, prayer, event…',
        'submit'      => 'Search',
        'searching'   => 'Searching…',
        'too_short'   => 'Type at least :min characters.',
        'start'       => 'What are you looking for?',
        'start_text'  => 'A word from the title or text of a testimony, the name of an account, an event or a prayer session.',
        'empty'       => 'No results in this section.',
        'empty_text'  => 'Try another word or another section.',
        'tabs_label'  => 'Result sections',
        'tabs' => [
            'temoignages' => 'Testimonies',
            'comptes'     => 'Accounts',
            'prieres'     => 'Prayer requests',
            'evenements'  => 'Events',
            'sessions'    => 'Prayer sessions',
        ],
    ],
];

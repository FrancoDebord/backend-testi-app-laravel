<?php

// Search engine optimisation and web publication (docs/fonctionnalites/referencement.md)
return [
    'anonymous' => 'Anonymous testimony',

    'form' => [
        'resume'               => 'Written summary (recommended)',
        'resume_hint'          => 'In a few sentences: what God has done. This text helps people find your testimony on Google.',
        'transcription'        => 'Transcript (optional)',
        'transcription_hint'   => 'The text of what you say in the audio or video.',
        'publication_web'      => 'Also publish on the web',
        'publication_web_help' => 'Once approved, your testimony can be read on the public website and found on Google. You can withdraw this consent at any time.',
        'anonymous'            => 'Testify anonymously on the website',
        'anonymous_help'       => 'On the website, your name, photo and profile are not shown: "Anonymous testimony".',
    ],

    'author' => [
        'title'                => 'On the web',
        'publication_web_help' => 'Unchecked: the page is no longer public and Google removes it from its results.',
        'save'                 => 'Save',
        'saved'                => 'Your web publication choices have been saved.',
    ],

    'validation' => [
        'resume_max'        => 'Summary: 5,000 characters at most.',
        'transcription_max' => 'Transcript: 100,000 characters at most.',
    ],

    'breadcrumb' => [
        'home'        => 'Home',
        'testimonies' => 'Testimonies',
        'daily_verse' => 'Verse of the day',
    ],

    'testimony' => [
        'kind'             => 'Testimony',
        'description'      => 'Christian testimony (:category): what God has done, shared on My Testimony.',
        'default_category' => 'testimony',
        'written'          => 'The written testimony',
        'transcription'    => 'Full transcript',
        'theme_verse'      => 'A verse for this testimony',
        'theme_link'       => 'Theme: :theme',
        'share'            => 'Share',
    ],

    'category' => [
        'title'       => ':category testimonies',
        'count'       => '{0} No testimonies|{1} 1 testimony|[2,*] :count testimonies',
        'description' => 'Christian testimonies about :category: what God has done in the lives of those who testify.',
        'empty'       => 'No testimonies yet',
        'empty_text'  => 'Testimonies in this category will appear here once published.',
        'others'      => 'Other testimonies',
    ],

    'theme' => [
        'kind'        => 'Verse of the day',
        'week'        => 'Week of :start to :end',
        'description' => 'Verse of the day theme ":theme", key verse :verse: the verses of the week and testimonies.',
        'key_verse'   => 'Key verse:',
        'verses'      => 'The verses of the week',
        'testimonies' => 'Latest testimonies',
    ],

    'app' => [
        'title'   => 'Download the app',
        'text'    => 'Testimonies, prayer and the verse of the day on your phone.',
        'android' => 'Android',
        'ios'     => 'iPhone',
    ],

    'admin' => [
        'intro'      => 'Introduction of the public page',
        'intro_hint' => 'A few sentences shown at the top of the category page and read by Google.',
    ],

    'footer' => [
        'categories' => 'Testimonies by theme',
        'lives'      => 'Live and replays',
    ],
];

<?php

// Credit for the original YouTube video (docs/fonctionnalites/videos-youtube.md)
return [
    'section'        => 'Video credit',
    'section_help'   => 'Credit the person and the channel behind the video.',
    'author'         => 'Video author',
    'author_hint'    => 'Author or speaker.',
    'channel'        => 'YouTube channel',
    'url'            => 'Channel link',
    'url_hint'       => 'Link to the channel or the original video (http or https).',
    'video_by'       => 'Video by :credit',
    'open_channel'   => 'View the channel',
    'validation' => [
        'author_max'  => 'The video author may not be longer than 150 characters.',
        'channel_max' => 'The channel name may not be longer than 150 characters.',
        'url_max'     => 'The channel link may not be longer than 300 characters.',
        'url_invalid' => 'The channel link must be a valid web address starting with http:// or https://.',
        'string'      => 'This field must be text.',
    ],
];

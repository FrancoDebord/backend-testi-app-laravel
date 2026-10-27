<?php

// Reactions and internal shares: testimonies, events, prayer requests and prayer sessions.
return [
    // Watch page buttons
    'like'    => 'Like',
    'i_pray'  => 'Praying',
    'pray'    => 'Prayer',
    'amen'    => 'Amen',
    'worship' => 'Worship God',
    'fire'    => 'Fire',

    // "Who reacted?" popup (App\Services\ReactionPeople, docs/fonctionnalites/qui-a-reagi.md)
    'people' => [
        'open'         => 'Who reacted?',
        'open_prayers' => 'See who is praying',
        'title'        => 'Reactions',
        'all'          => 'All',
        'tabs'         => 'Reactions by type',
        'empty'        => 'No one has reacted yet.',
        'empty_type'   => 'No one for this reaction.',
        'comments'     => 'Comments',
        'empty_comments' => 'No comments yet.',
        'encouragements' => 'Encouragements',
        'more'         => 'Show more',
        'close'        => 'Close',
        'invalid_type' => 'Unknown reaction type.',
    ],

    // Praying several times (docs/fonctionnalites/prier-plusieurs-fois.md)
    'prayer_times' => [
        'prayed'      => '{1} You prayed once|[2,*] You prayed :count times',
        'remove_last' => 'Remove my last prayer',
        'pray_again'  => 'Pray again',
        'times'       => '{1} once|[2,*] :count times',
    ],

    'api' => [
        'added'   => 'Reaction added',
        'saved'   => 'Reaction saved',
        'removed' => 'Reaction removed',
    ],

    // Notifications to a testimony's author (App\Services\TestimonyReactions)
    'notify_like'   => ':actor reacted to your testimony',
    'notify_prayer' => ':actor is praying for your testimony',

    // Events, prayer requests and prayer sessions (App\Services\ContentInteractions)
    'content' => [
        'not_found'     => 'Post not found.',
        'use_pray'      => 'Use "I\'m praying" to pray for this request.',
        'own'           => 'You cannot share your own post.',
        'not_shareable' => 'This post cannot be shared.',
        'not_reposted'  => 'You have not shared this post.',
        'reposted'      => 'Shared with your followers.',
        'comment_max'   => 'Your comment: 500 characters at most.',
    ],

    // Notification to the author: <action>_<post type>. "prayer": the app prefixes the name.
    'notify' => [
        'like_event'            => ':actor reacted to your event ":title".',
        'like_prayer_request'   => ':actor reacted to your prayer request.',
        'like_prayer_session'   => ':actor reacted to your prayer session ":title".',
        'share_event'           => ':actor shared your event ":title".',
        'share_prayer_request'  => ':actor shared your prayer request.',
        'share_prayer_session'  => ':actor shared your prayer session ":title".',
        'prayer_event'          => 'is praying for your event ":title".',
        'prayer_prayer_request' => 'is praying for your prayer request.',
        'prayer_prayer_session' => 'is praying for your prayer session ":title".',
    ],
];

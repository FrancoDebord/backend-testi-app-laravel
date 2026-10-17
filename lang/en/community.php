<?php

// Community: accounts to follow, following, followers, suggestions.
return [
    'tabs'                 => [
        'all'           => 'All',
        'organizations' => 'Organizations',
        'people'        => 'People',
    ],
    'follow'               => [
        'self'        => 'You cannot follow yourself.',
        'unavailable' => 'This account is not available.',
        'following'   => 'You are following :name.',
        'unfollowed'  => 'You no longer follow :name.',
    ],
    'api'                  => [
        'followed'   => 'Followed',
        'unfollowed' => 'Unfollowed',
    ],
    'reasons'              => [
        'followed_by'            => 'Followed by :name, whom you follow',
        'followed_by_others'     => '{1} Followed by :name and :count other you follow|[2,*] Followed by :name and :count others you follow',
        'follows_you'            => 'Follows you',
        'verified_organization'  => 'Verified organization',
        'verified_account'       => 'Verified account',
        'posts_often'            => 'Often shares testimonies',
        'shared_interests'       => 'Shares testimonies on topics you care about',
        'community_organization' => 'Community organization',
        'shares_testimonies'     => 'Shares testimonies',
        'community_member'       => 'Community member',
    ],
    'notify_follow'        => ':name is now following you',
    'notify_new_testimony' => ':name shared a new testimony: ":title"',
];

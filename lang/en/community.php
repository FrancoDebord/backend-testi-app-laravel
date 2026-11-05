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
    // Community page (community/index)
    'index'                => [
        'title'               => 'Community',
        'subheader'           => 'Churches, ministries, associations and people: follow them to be notified of their testimonies and live streams.',
        'empty_people'        => 'No people yet.',
        'empty_people_text'   => 'Registered people will appear here.',
        'empty_organizations' => 'No organizations yet.',
        'empty_organizations_text' => 'Registered churches, ministries and associations will appear here.',
        'empty_all'           => 'No accounts yet.',
        'empty_all_text'      => 'Registered people and organizations will appear here.',
        'search_label'        => 'Search for an account',
        'placeholder_people'  => 'Name, country…',
        'suggested_title'     => 'Suggestions for you',
        'tabs_aria'           => 'Account type',
        'no_match'            => 'No account matches your search.',
    ],
    // Search in account lists (Community, followers, following)
    'search'               => [
        'placeholder' => 'Name, city, country…',
        'results'     => '{0} :count results for|{1} :count result for|[2,*] :count results for',
        'quoted'      => '“:q”',
        'retry'       => 'Check the spelling or try another name.',
        'clear'       => 'Clear search',
        'discover'    => 'Discover the Community',
    ],
    // My followers (community/followers)
    'followers_page'       => [
        'title'        => 'My followers',
        'subheader'    => 'The accounts that follow you: they are notified of your testimonies and live streams.',
        'search_label' => 'Search my followers',
        'count'        => '{0} :count accounts follow you.|{1} :count account follows you.|[2,*] :count accounts follow you.',
        'no_match'     => 'No follower matches your search.',
        'empty_title'  => 'Nobody follows you yet.',
        'empty_text'   => 'Publish testimonies and take part in the Community: those who follow you will appear here.',
        'follow_back'  => 'Follow back',
    ],
    // Following (community/following)
    'following_page'       => [
        'title'        => 'Following',
        'subheader'    => 'The accounts you follow: you are notified of their testimonies and live streams.',
        'search_label' => 'Search accounts I follow',
        'count'        => '{0} You follow :count accounts.|{1} You follow :count account.|[2,*] You follow :count accounts.',
        'no_match'     => 'No followed account matches your search.',
        'empty_title'  => 'You are not following anyone yet.',
        'empty_text'   => 'Discover churches, ministries and people who share testimonies in the Community.',
    ],
    // Followers / Following tabs (community/partials/follow-tabs)
    'follow_tabs'          => [
        'aria'      => 'Followers and following',
        'followers' => 'Followers',
        'following' => 'Following',
    ],
    // Account card and suggestions (community/partials/card, community/partials/suggested)
    'card'                 => [
        'followers'    => '{0} followers|{1} follower|[2,*] followers',
        'testimonies'  => '{0} :count testimonies|{1} :count testimony|[2,*] :count testimonies',
        'view_profile' => 'View profile',
    ],
    'suggested'            => [
        'title' => 'Accounts to follow',
        'more'  => 'See more',
    ],
    // Person picker (components/user-picker)
    'user_picker'          => [
        'label'       => 'Add a person',
        'placeholder' => 'Name or email address',
        'hint'        => 'Personal accounts only. Type at least 2 letters of the name, or the full email address.',
    ],
];

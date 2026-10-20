<?php

// Privacy policy and account deletion (docs/fonctionnalites/confidentialite-et-suppression.md).
// The policy text itself lives in resources/legal/privacy.en.md.
return [
    'link'          => 'Privacy policy',
    'updated'       => 'Last updated: :date',
    'other_language' => 'Lire en français',
    'breadcrumb'    => 'Privacy',
    'delete_link'   => 'Delete my account',

    'consent' => [
        'label'    => 'I accept the :link',
        'link'     => 'privacy policy',
        'required' => 'You must accept the privacy policy to create an account.',
        'google'   => 'By continuing with Google, you accept the :link.',
    ],

    'deletion' => [
        'title'          => 'Delete your My Testimony account',
        'breadcrumb'     => 'Account deletion',
        'other_language' => 'Lire en français',
        'intro'          => 'You can delete your My Testimony account and its data at any time, from the app (Settings → Account → Delete my account) or from this page.',
        'deleted_title'  => 'What is deleted',
        'deleted' => [
            'Your profile: name, e-mail, phone, profile and cover photos, verification information.',
            'Your testimonies, including the videos, audio, images and proofs you uploaded.',
            'Your prayer requests, your prayer sessions and your saved prophetic words.',
            'Your preferences (verse of the day, display, notifications) and your drafts.',
            'Your reactions, comments and follows.',
            'The notification identifiers of your devices and your open sessions.',
            'The events published on behalf of your account.',
        ],
        'delays_title'   => 'Timeframes',
        'delays' => [
            'From the app, or from this page while signed in: immediate and permanent deletion.',
            'Through the form below: your request is processed within 30 days at most.',
            'Backup copies are erased within 90 days.',
            'Technical server logs (with no content) are kept for 12 months at most, unless required by law.',
        ],
        'irreversible'   => 'Deletion is permanent: your content cannot be recovered.',

        'connected_title' => 'Delete my account now',
        'connected_as'   => 'You are signed in as :name.',
        'password'       => 'Password',
        'password_hint'  => 'Enter your password to confirm.',
        'submit'         => 'Permanently delete my account',
        'confirm_title'  => 'Delete your account?',
        'confirm_text'   => 'Your profile, your testimonies, your prayer requests and all your data will be permanently deleted. This cannot be undone.',
        'confirm_label'  => 'Delete',
        'wrong_password' => 'Incorrect password.',
        'done'           => 'Your account and your data have been deleted.',
        'super_admin_forbidden' => 'A super administrator account cannot be deleted this way. Remove that status first (php artisan users:super-admin).',

        'request_title'  => 'Request deletion without signing in',
        'request_intro'  => 'Enter the e-mail address or phone number of your account. We will check the request before deleting the account.',
        'contact'        => 'Account e-mail or phone',
        'contact_placeholder' => 'you@example.com or +229 01 90 00 00 00',
        'contact_invalid' => 'Enter a valid e-mail address or phone number.',
        'message'        => 'Message (optional)',
        'request_submit' => 'Send the request',
        'request_sent'   => 'Your request has been recorded. It will be processed within 30 days at most; we may contact you to check that the account is yours.',
        'or_login'       => 'Can you still access your account?',
        'login'          => 'Sign in',
        'login_suffix'   => 'to delete it immediately.',

        'notify_admin_known'   => 'Deletion request for the account linked to :contact.',
        'notify_admin_unknown' => 'Deletion request for :contact (no matching account).',

        'api' => [
            'deleted' => 'Account and data deleted.',
        ],
    ],

    'admin' => [
        'title'       => 'Deletion requests',
        'subheader'   => 'Requests made without signing in on the public account deletion page.',
        'empty'       => 'No requests.',
        'contact'     => 'Contact',
        'account'     => 'Matching account',
        'no_account'  => 'No account',
        'received'    => 'Received',
        'status'      => 'Status',
        'statuses'    => ['pending' => 'Pending', 'done' => 'Processed', 'rejected' => 'Rejected'],
        'delete'      => 'Delete the account',
        'reject'      => 'Close without action',
        'confirm_delete' => 'The account “:name” and all its data will be permanently deleted.',
        'deleted'     => 'Account deleted.',
        'rejected'    => 'Request closed without action.',
        'super_admin_only' => 'Only a super administrator can delete an account.',
    ],
];

<?php

// Account verification and "Approved" badge: services, controllers and admin/verifications views.
return [
    'date_time_format'           => 'F j, Y \a\t g:i A',
    'account_deleted'            => 'This account has been deleted.',
    'already_processed'          => 'This request has already been processed.',
    'use_organization_review'    => 'Use organization verification for this account.',
    'not_verified'               => 'This account is not verified.',
    'document_n'                 => 'Document :n',

    'approval' => [
        'grant_reason_required'  => 'Enter the reason for granting the badge.',
        'already_approved'       => 'This account already has the "Approved" badge.',
        'revoke_reason_required' => 'Enter the reason for removing the badge.',
        'not_approved'           => 'This account does not have the "Approved" badge.',
    ],

    'rules' => [
        'full_name_required'     => 'Enter your full name.',
        'full_name_min'          => 'Enter your full name.',
        'role_required'          => 'Choose your role.',
        'role_other_required_if' => 'Specify your role.',
        'church_required'        => 'Enter your church or ministry.',
        'links_max'              => 'Three links at most.',
        'links_url'              => 'Each link must be a web address (https://…).',
        'documents_required'     => 'Add at least one supporting document.',
        'documents_min'          => 'Add at least one supporting document.',
        'documents_max'          => 'Three supporting documents at most.',
        'documents_mimes'        => 'Supporting document: photo (JPG, PNG, WebP) or PDF.',
        'documents_file_max'     => 'Each supporting document must be 8 MB or less.',
        'documents_file'         => 'Invalid supporting document.',
    ],

    'block' => [
        'organization'     => 'Organizations are verified based on their profile information.',
        'already_verified' => 'Your account is already verified.',
        'pending'          => 'Your request is being reviewed.',
        'retry_at'         => 'You can send a new request from :date.',
    ],

    'notify_organization_approved' => 'Your organization has received the "Approved" badge. It now appears on your profile and your posts.',
    'notify_account_approved'      => 'Your account has received the "Approved" badge. It now appears on your profile and your posts.',
    'notify_approval_revoked'      => 'The "Approved" badge has been removed from your account: :reason.',
    'notify_verified'              => 'Your account is verified. The "Verified account" badge now appears on your profile and your posts.',
    'notify_rejected'              => 'Your verification request was declined: :reason. You can send a new request in :hours hours.',
    'notify_revoked'               => 'Your account verification has been removed: :reason.',

    'reason_required'        => 'Give the reason.',
    'reject_reason_required' => 'Give the reason for declining.',
    'flash' => [
        'badge_granted' => '“Approved” badge granted.',
        'badge_revoked' => '“Approved” badge removed.',
        'submitted'     => 'Request sent. Our team will review it and you will be notified in your notifications.',
        'approved'      => 'Account verified. The person has been notified.',
        'rejected'      => 'Request declined. The person has been notified of the reason.',
        'revoked'       => 'Verification withdrawn. The person has been notified.',
    ],
    'api' => [
        'submitted' => 'Request sent. Our team will review it and you will be notified in the app.',
        'approved'  => 'Account verified',
        'rejected'  => 'Request declined',
        'revoked'   => 'Verification withdrawn',
    ],
];

<?php

// Administration: Admin controllers, audit log, Bunny videos, resources/views/admin/* views.
return [
    'flash' => [
        'organization_only_verify' => 'Only an organization account can be verified.',
        'organization_verified'    => 'Organization verified. The account has been notified.',
        'organization_only_review' => 'Only an organization account can be reviewed for verification.',
        'organization_rejected'    => 'Verification declined. The account has been notified.',
        'own_role'                 => 'You cannot change your own role.',
        'user_updated'             => 'User updated.',
        'category_created'         => 'Category created.',
        'category_updated'         => 'Category updated.',
        'category_deleted'         => 'Category deleted.',
        'settings_updated'         => 'Settings updated.',
    ],

    'api' => [
        'organization_only_verify' => 'Only an organization account can be verified',
        'organization_verified'    => 'Organization verified',
        'organization_only_review' => 'Only an organization account can be reviewed for verification',
        'organization_rejected'    => 'Verification declined',
        'cannot_ban_admin'         => 'An administrator cannot be banned',
        'user_banned'              => 'User banned',
        'user_suspended'           => 'User suspended',
        'user_activated'           => 'User activated',
        'own_role'                 => 'You cannot change your own role',
        'role_updated'             => 'Role updated',
        'slug_taken'               => 'The slug \':slug\' is already in use. Provide a custom slug.',
        'category_created'         => 'Category created',
        'category_updated'         => 'Category updated',
        'category_in_use'          => 'Cannot delete: :count testimony(ies) are linked to this category. Deactivate it instead.',
        'category_deleted'         => 'Category deleted',
        'settings_updated'         => 'Settings updated',
    ],

    // Audit log (AdminAudit::label).
    'audit' => [
        'testimony' => [
            'approved'      => 'Testimony approved (published)',
            'rejected'      => 'Testimony declined',
            'resubmitted'   => 'Testimony edited by the author, sent back to moderation',
            'verified'      => '"Approved testimony" label granted',
            'unverified'    => '"Approved testimony" label removed',
            'featured'      => 'Featured',
            'unfeatured'    => 'Removed from featured',
            'deleted'       => 'Moved to trash',
            'restored'      => 'Restored from trash',
            'force_deleted' => 'Permanently deleted',
        ],
        'user' => [
            'badge_granted'       => '"Approved" badge granted',
            'badge_revoked'       => '"Approved" badge removed',
            'super_admin_granted' => 'Super administrator status granted',
            'super_admin_revoked' => 'Super administrator status removed',
            'testimony_types'     => 'Profile testimony types changed',
        ],
    ],

    // Bunny Stream videos (BunnyVideoSync).
    'bunny' => [
        'notify_stuck'      => 'The video ":title" has been processing at Bunny for more than :minutes minutes (:guid).',
        'push_ready_title'  => 'Video ready',
        'push_ready_body'   => 'Your video ":title" is ready.',
        'push_failed_title' => 'Video not processed',
        'push_failed_body'  => 'Your video ":title" could not be processed. Please upload it again.',
    ],

    // Pagination (resources/views/vendor/pagination/tailwind.blade.php).
    'pagination' => [
        'label'    => 'Pagination',
        'range'    => ':first–:last of :total',
        'items'    => ':count item(s)',
        'previous' => 'Previous page',
        'next'     => 'Next page',
        'page'     => 'Page :page',
        'page_of'  => 'Page :page / :last',
    ],

    // Online documentation (Web\DocumentationController).
    'documentation' => [
        'updated_format' => 'm/d/Y \a\t H:i',
        'groups' => [
            'general'  => 'General',
            'features' => 'Features',
            'tracking' => 'Tracking',
        ],
    ],
];

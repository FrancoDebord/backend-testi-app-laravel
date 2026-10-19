<?php

// Global settings (App\Services\SettingsSchema) — same groups, labels and descriptions on the website
// (Administration › Settings) and in the app (GET /api/v1/admin/settings/schema).
// See docs/fonctionnalites/parametres-globaux.md
return [
    'groups' => [
        'app'          => 'General',
        'publishing'   => 'Publishing',
        'moderation'   => 'Moderation and limits',
        'features'     => 'Features',
        'notifications' => 'Notifications',
        'registration' => 'Sign-ups',
    ],
    'items' => [
        'app_name' => [
            'label'       => 'App name',
        ],
        'app_tagline' => [
            'label'       => 'Tagline',
        ],
        'app_version' => [
            'label'       => 'Version',
            'description' => 'Version shown to users.',
        ],
        'maintenance_mode' => [
            'label'       => 'Maintenance mode',
            'description' => 'Indicates that maintenance is in progress.',
        ],
        'testimony_type_text_enabled' => [
            'label'       => 'Written testimonies (text)',
            'description' => 'Allows publishing written testimonies. An exception can be set on each profile.',
        ],
        'testimony_type_audio_enabled' => [
            'label'       => 'Audio testimonies',
            'description' => 'Allows publishing audio testimonies. An exception can be set on each profile.',
        ],
        'testimony_type_video_enabled' => [
            'label'       => 'Video testimonies',
            'description' => 'Allows publishing video testimonies. An exception can be set on each profile.',
        ],
        'testimony_type_youtube_enabled' => [
            'label'       => 'YouTube links',
            'description' => 'Reserved for administrators, and for authorized profiles.',
        ],
        'auto_approve_testimonies' => [
            'label'       => 'Approve testimonies automatically',
        ],
        'max_testimony_length' => [
            'label'       => 'Maximum text length',
            'description' => 'In characters.',
        ],
        'max_audio_duration_sec' => [
            'label'       => 'Maximum audio duration',
            'description' => 'In seconds.',
        ],
        'max_video_duration_sec' => [
            'label'       => 'Maximum video duration',
            'description' => 'In seconds.',
        ],
        'max_file_size_mb' => [
            'label'       => 'Maximum file size',
            'description' => 'In megabytes (MB).',
        ],
        'allow_comments' => [
            'label'       => 'Allow comments',
        ],
        'allow_reactions' => [
            'label'       => 'Allow reactions',
        ],
        'allow_sharing' => [
            'label'       => 'Allow sharing',
        ],
        'require_consent_form' => [
            'label'       => 'Consent form required',
        ],
        'push_notifications_enabled' => [
            'label'       => 'Push notifications',
            'description' => 'Off: no push notification is sent at all (notifications remain visible in the app and on the website).',
        ],
        'allow_registration' => [
            'label'       => 'Allow sign-ups',
        ],
        'default_role' => [
            'label'       => 'Default role',
            'description' => 'Role given to new accounts.',
        ],
        'require_email_verification' => [
            'label'       => 'Email address verification required',
        ],
    ],
    'validation' => [
        'unknown'  => 'Unknown setting: :key.',
        'boolean'  => 'The :key setting must be true or false.',
        'integer'  => 'The :key setting must be a whole number.',
        'string'   => 'The :key setting must be text.',
        'json'     => 'The :key setting must be valid JSON.',
        'too_long' => 'The :key setting is too long.',
        'empty'    => 'No settings to save.',
    ],
];

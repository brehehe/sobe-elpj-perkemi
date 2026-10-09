<?php

return [
    'application' => [
        'purpose' => 'Mengelola akuntabilitas kegiatan PB PERKEMI dari mandat, pelaksanaan, LPJ, verifikasi, hingga arsip digital.',
    ],

    'environment_label' => env('APP_ENV_LABEL', env('APP_ENV', 'production')),

    'release' => [
        'backend_version' => env('APP_BACKEND_VERSION', '0.1.0'),
        'frontend_version' => env('APP_FRONTEND_VERSION', '0.1.0'),
        'build_id' => env('APP_BUILD_ID', 'development'),
        'released_at' => env('APP_RELEASED_AT'),
        'notes' => [
            'Workflow LPJ berjenjang dengan snapshot immutable dan decision pack.',
            'Action Center berbasis task, SLA, capability, dan scope unit.',
            'Pemantauan integrasi, audit trail, serta kesehatan backup operasional.',
        ],
    ],

    'backup' => [
        'last_restore_drill_at' => env('BACKUP_LAST_RESTORE_DRILL_AT'),
        'restore_drill_maximum_age_days' => (int) env('BACKUP_RESTORE_DRILL_MAX_AGE_DAYS', 90),
    ],

    'governance' => [
        'classification' => 'Internal organisasi; akses dibatasi berdasarkan unit, peran, dan kewenangan.',
        'privacy' => 'Data pribadi dan evidence hanya digunakan untuk akuntabilitas kegiatan serta tidak dipublikasikan tanpa dasar kewenangan.',
        'accessibility' => 'Antarmuka menargetkan WCAG 2.2 tingkat AA, mendukung keyboard, fokus terlihat, dan pembesaran tampilan.',
    ],

    'contact' => [
        'owner' => env('SYSTEM_OWNER_NAME', 'Sekretariat PB PERKEMI'),
        'support_email' => env('SYSTEM_SUPPORT_EMAIL'),
        'security_email' => env('SYSTEM_SECURITY_EMAIL'),
    ],

    'links' => [
        'documentation' => env('SYSTEM_DOCUMENTATION_URL'),
        'privacy' => env('SYSTEM_PRIVACY_URL'),
    ],

    'licenses' => [
        ['name' => 'Laravel', 'license' => 'MIT'],
        ['name' => 'React', 'license' => 'MIT'],
        ['name' => 'Inertia.js', 'license' => 'MIT'],
        ['name' => 'Tailwind CSS', 'license' => 'MIT'],
        ['name' => 'Spatie Laravel Backup', 'license' => 'MIT'],
        ['name' => 'Lucide', 'license' => 'ISC'],
    ],
];

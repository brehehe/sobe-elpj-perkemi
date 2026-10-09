<?php

return [
    'disk' => env('EVIDENCE_DISK', 'local'),

    'scanner' => env('EVIDENCE_SCANNER', env('APP_ENV') === 'production' ? 'clamav' : 'signature'),

    'max_size_kb' => (int) env('EVIDENCE_MAX_SIZE_KB', 20_480),

    'allowed_extensions' => [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
        ],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
    ],

    'clamav' => [
        'binary' => env('CLAMAV_BINARY', '/usr/bin/clamscan'),
        'timeout_seconds' => (int) env('CLAMAV_TIMEOUT_SECONDS', 60),
    ],
];

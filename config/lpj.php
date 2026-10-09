<?php

return [
    'precheck_policy' => [
        'version' => 'lpj-precheck-v3',
    ],

    'verification_policy' => [
        'version' => 'lpj-verification-v1',
        'clarification_due_days' => 3,
        'stages' => [
            'SYSTEM' => [
                'label' => 'System check',
                'checklist' => [],
            ],
            'ADMINISTRATION' => [
                'label' => 'Administrasi',
                'checklist' => [
                    'Mandat dan identitas kegiatan sesuai',
                    'Peserta dan dokumen administratif lengkap',
                    'Referensi sumber dapat ditelusuri',
                ],
            ],
            'FINANCE' => [
                'label' => 'Keuangan',
                'checklist' => [
                    'RAB dan realisasi konsisten',
                    'Transaksi serta rekonsiliasi telah diperiksa',
                    'Bukti pembayaran relevan dan terbaca',
                ],
            ],
            'SUBSTANTIVE' => [
                'label' => 'Substantif/kepatuhan',
                'checklist' => [
                    'Output sesuai rencana',
                    'Outcome dan KPI didukung bukti',
                    'Kesimpulan serta pembelajaran dapat dipertanggungjawabkan',
                ],
            ],
        ],
    ],

    'approval_policy' => [
        'version' => 'lpj-approval-authority-v1',
        'clarification_due_days' => 3,
        'levels' => [
            'APPROVER' => [
                'label' => 'Approver unit',
                'permission' => 'lpjs.approve',
                'roles' => ['Approver', 'Super Admin'],
                'unit_scope' => 'ACTIVITY_UNIT',
                'max_amount' => null,
            ],
            'PB' => [
                'label' => 'Verifikator PB',
                'permission' => 'lpjs.pb_verify',
                'roles' => ['PB Verifier', 'Super Admin'],
                'unit_scope' => 'GLOBAL',
                'max_amount' => null,
            ],
        ],
    ],

    'verified_pack_policy' => [
        'version' => 'lpj-verified-pack-v1',
        'schema_version' => 1,
        'disk' => 'local',
        'public_verification' => true,
    ],

    'archive_policy' => [
        'version' => 'lpj-archive-retention-v1',
        'schema_version' => 1,
        'disk' => 'local',
        'retention_years' => 10,
    ],

    'reconciliation_exception' => [
        'authority_policy' => [
            'version' => 'reconciliation-exception-authority-v1',
        ],
    ],

    'workflow_tasks' => [
        'version' => 'lpj-work-queue-v1',
        'verification_due_days' => 3,
        'approval_due_days' => 2,
        'pb_verification_due_days' => 2,
        'archive_due_days' => 2,
        'revision_due_days' => 3,
        'escalation_lead_hours' => 12,
    ],

    'template_policy' => [
        'version' => 'lpj-template-v1',
        'default_label' => 'Kegiatan umum',
        'mappings' => [
            ['activity_type' => 'PENATARAN', 'label' => 'Penataran & sertifikasi'],
            ['activity_type' => 'KEJUARAAN', 'label' => 'Kejuaraan & pertandingan'],
            ['activity_type' => 'RAPAT', 'label' => 'Rapat & koordinasi'],
            ['activity_type' => 'PEMBINAAN', 'label' => 'Pembinaan organisasi'],
            ['activity_type' => 'UJIAN', 'label' => 'Ujian kompetensi'],
            ['activity_type' => 'LAINNYA', 'label' => 'Kegiatan umum'],
        ],
        'sections' => ['Identitas', 'Planning', 'Pelaksanaan', 'Keuangan', 'Evidence', 'Evaluasi', 'Findings', 'Audit Pack'],
    ],

    'ai_policy' => [
        'version' => 'lpj-ai-governance-v1',
        'external_provider_enabled' => true,
        'daily_token_budget' => (int) env('OPENAI_DAILY_BUDGET', 0),
        'monthly_token_budget' => (int) env('OPENAI_MONTHLY_BUDGET', 0),
        'warning_percent' => 80,
        'user_rate_per_minute' => 10,
        'unit_rate_per_minute' => 30,
        'organization_rate_per_minute' => 120,
        'review_disclaimer' => 'Hasil AI bersifat pendukung dan wajib ditinjau manusia.',
    ],

    'system_policy' => [
        'version' => 'lpj-system-governance-v1',
        'data_classification' => env('APP_DATA_CLASSIFICATION', 'Internal PB PERKEMI'),
        'maintenance_message' => null,
        'support_message' => 'Hubungi administrator sistem bila kendala menghambat workflow.',
    ],
];

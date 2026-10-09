<?php

return [
    'default_contract_version' => 'unversioned-v1',
    'stale_after_minutes' => (int) env('INTEGRATION_STALE_AFTER_MINUTES', 1440),
    'retry_delays' => [30, 120, 600, 1800],
    'owner_role' => 'Finance PIC',

    'providers' => [
        'SIMPERKEMI' => [
            'name' => 'SIMPERKEMI',
            'description' => 'Identitas anggota, NIK, dan hierarki organisasi.',
            'contract_version' => 'identity-hierarchy-v1',
        ],
        'SMART_EDOJO' => [
            'name' => 'Smart e-Dojo',
            'description' => 'Kegiatan, pelatihan, keuangan, dan evidence dojo.',
            'contract_version' => 'dojo-activity-v1',
        ],
        'E_PENGKOT' => [
            'name' => 'e-Pengkot/Pengkab',
            'description' => 'Review dan portofolio tingkat kota atau kabupaten.',
            'contract_version' => 'regional-review-v1',
        ],
        'E_PENGPROV' => [
            'name' => 'e-Pengprov',
            'description' => 'Oversight dan agregasi tingkat provinsi.',
            'contract_version' => 'province-oversight-v1',
        ],
        'TRAINING_CREDENTIAL' => [
            'name' => 'Training / Exam / Credential',
            'description' => 'Kehadiran, hasil ujian, dan referensi kredensial.',
            'contract_version' => 'training-credential-v1',
        ],
        'E_EVENT' => [
            'name' => 'e-Event',
            'description' => 'Peserta, hasil kegiatan, dan referensi pembayaran.',
            'contract_version' => 'event-participation-v1',
        ],
        'SIMKEU' => [
            'name' => 'e-Finance / Bank',
            'description' => 'Ledger, settlement, transaksi, dan rekonsiliasi.',
            'contract_version' => 'finance-transaction-v1',
        ],
        'E_SURAT_ASSET' => [
            'name' => 'e-Surat / e-Asset',
            'description' => 'Mandat, disposisi, dan referensi aset.',
            'contract_version' => 'mandate-asset-v1',
        ],
    ],
];

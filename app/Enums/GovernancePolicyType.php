<?php

namespace App\Enums;

enum GovernancePolicyType: string
{
    case Verification = 'VERIFICATION';
    case Workflow = 'WORKFLOW';
    case Templates = 'TEMPLATES';
    case Retention = 'RETENTION';
    case Ai = 'AI';
    case System = 'SYSTEM';

    public function label(): string
    {
        return match ($this) {
            self::Verification => 'Checklist verifikasi',
            self::Workflow => 'Aturan workflow',
            self::Templates => 'Template LPJ',
            self::Retention => 'Kebijakan retensi',
            self::Ai => 'AI & penggunaan',
            self::System => 'Pengaturan sistem',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Verification => 'Checklist domain, urutan gate, dan batas waktu klarifikasi.',
            self::Workflow => 'SLA tugas otomatis dan waktu eskalasi pada setiap gate LPJ.',
            self::Templates => 'Pemetaan jenis kegiatan ke template dan bagian wajib LPJ.',
            self::Retention => 'Masa simpan, skema paket, dan penyimpanan arsip immutable.',
            self::Ai => 'Izin provider eksternal, anggaran token, dan batas peringatan penggunaan.',
            self::System => 'Klasifikasi data dan pesan operasional yang tampil ke pengguna.',
        };
    }
}

<?php

namespace App\Enums;

enum LpjStatus: string
{
    case Draft = 'DRAFT';
    case ReadyToSubmit = 'READY_TO_SUBMIT';
    case Submitted = 'SUBMITTED';
    case InVerification = 'IN_VERIFICATION';
    case ClarificationRequired = 'CLARIFICATION_REQUIRED';
    case FindingOpen = 'FINDING_OPEN';
    case Returned = 'RETURNED';
    case Rejected = 'REJECTED';
    case ReadyForApproval = 'READY_FOR_APPROVAL';
    case Approved = 'APPROVED';
    case PbVerified = 'PB_VERIFIED';
    case Archived = 'ARCHIVED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::ReadyToSubmit => 'Siap diajukan',
            self::Submitted => 'Diajukan',
            self::InVerification => 'Dalam verifikasi',
            self::ClarificationRequired => 'Perlu klarifikasi',
            self::FindingOpen => 'Temuan terbuka',
            self::Returned => 'Dikembalikan',
            self::Rejected => 'Ditolak',
            self::ReadyForApproval => 'Siap disetujui',
            self::Approved => 'Disetujui',
            self::PbVerified => 'Terverifikasi PB',
            self::Archived => 'Diarsipkan',
        };
    }
}

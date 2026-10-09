<?php

namespace App\Enums;

enum ReconciliationExceptionStatus: string
{
    public const BLOCKING_VALUES = [
        'OPEN',
        'IN_PROGRESS',
        'PENDING_APPROVAL',
        'REJECTED',
    ];

    case Open = 'OPEN';
    case InProgress = 'IN_PROGRESS';
    case PendingApproval = 'PENDING_APPROVAL';
    case Resolved = 'RESOLVED';
    case ApprovedException = 'APPROVED_EXCEPTION';
    case Rejected = 'REJECTED';
}

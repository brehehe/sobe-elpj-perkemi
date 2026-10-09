<?php

namespace App\Enums;

enum DispositionStatus: string
{
    case Open = 'OPEN';
    case Acknowledged = 'ACKNOWLEDGED';
    case InProgress = 'IN_PROGRESS';
    case EvidenceSubmitted = 'EVIDENCE_SUBMITTED';
    case Closed = 'CLOSED';
    case Reopened = 'REOPENED';

    /** @deprecated Retained for historical records created before the reviewed closure flow. */
    case Completed = 'COMPLETED';

    /** @deprecated Retained for historical records created before the reviewed closure flow. */
    case Cancelled = 'CANCELLED';
}

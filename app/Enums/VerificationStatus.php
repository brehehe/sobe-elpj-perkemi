<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Pending = 'PENDING';
    case Verified = 'VERIFIED';
    case Finding = 'FINDING';
    case Returned = 'RETURNED';
    case ClarificationRequired = 'CLARIFICATION_REQUIRED';
}

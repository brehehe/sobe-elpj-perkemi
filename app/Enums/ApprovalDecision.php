<?php

namespace App\Enums;

enum ApprovalDecision: string
{
    case Approved = 'APPROVED';
    case Returned = 'RETURNED';
    case ClarificationRequired = 'CLARIFICATION_REQUIRED';
    case Rejected = 'REJECTED';
}

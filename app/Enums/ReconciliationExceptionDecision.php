<?php

namespace App\Enums;

enum ReconciliationExceptionDecision: string
{
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
}

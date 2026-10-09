<?php

namespace App\Enums;

enum BudgetStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case Approved = 'APPROVED';
    case Returned = 'RETURNED';
    case Rejected = 'REJECTED';
}

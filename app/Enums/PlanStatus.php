<?php

namespace App\Enums;

enum PlanStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case Approved = 'APPROVED';
    case Returned = 'RETURNED';
    case Rejected = 'REJECTED';
}

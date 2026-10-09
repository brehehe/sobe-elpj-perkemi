<?php

namespace App\Enums;

enum ReconciliationStatus: string
{
    case Unmatched = 'UNMATCHED';
    case Pending = 'PENDING';
    case Matched = 'MATCHED';
    case Exception = 'EXCEPTION';
    case Resolved = 'RESOLVED';
}

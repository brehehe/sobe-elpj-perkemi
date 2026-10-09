<?php

namespace App\Enums;

enum ActivityExecutionStatus: string
{
    case Recorded = 'RECORDED';
    case Open = 'OPEN';
    case Resolved = 'RESOLVED';
}

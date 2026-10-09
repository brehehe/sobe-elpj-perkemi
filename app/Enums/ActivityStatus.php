<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case Planned = 'PLANNED';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}

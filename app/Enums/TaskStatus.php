<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Open = 'OPEN';
    case InProgress = 'IN_PROGRESS';
    case Done = 'DONE';
    case Cancelled = 'CANCELLED';
}

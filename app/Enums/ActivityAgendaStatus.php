<?php

namespace App\Enums;

enum ActivityAgendaStatus: string
{
    case Planned = 'PLANNED';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}

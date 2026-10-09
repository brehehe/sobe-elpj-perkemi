<?php

namespace App\Enums;

enum FindingStatus: string
{
    case Open = 'OPEN';
    case CapaSubmitted = 'CAPA_SUBMITTED';
    case Resolved = 'RESOLVED';
    case Rejected = 'REJECTED';
}

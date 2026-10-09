<?php

namespace App\Enums;

enum IntegrationStatus: string
{
    case Pending = 'PENDING';
    case Processing = 'PROCESSING';
    case PartialFailed = 'PARTIAL_FAILED';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
    case DeadLetter = 'DEAD_LETTER';
}

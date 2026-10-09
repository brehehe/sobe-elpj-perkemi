<?php

namespace App\Enums;

enum IntegrationRetryStatus: string
{
    case NotRequired = 'NOT_REQUIRED';
    case Scheduled = 'SCHEDULED';
    case Queued = 'QUEUED';
    case Retrying = 'RETRYING';
    case Exhausted = 'EXHAUSTED';
}

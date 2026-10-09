<?php

namespace App\Enums;

enum AiReviewStatus: string
{
    case Queued = 'QUEUED';
    case Running = 'RUNNING';
    case ReviewRequired = 'REVIEW_REQUIRED';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';
}

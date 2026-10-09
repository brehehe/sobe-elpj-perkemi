<?php

namespace App\Enums;

enum EvidenceReviewStatus: string
{
    case Pending = 'PENDING';
    case Verified = 'VERIFIED';
    case Rejected = 'REJECTED';
    case Superseded = 'SUPERSEDED';
}

<?php

namespace App\Enums;

enum EvaluationStatus: string
{
    case Submitted = 'SUBMITTED';
    case Accepted = 'ACCEPTED';
    case ClarificationRequired = 'CLARIFICATION_REQUIRED';
    case FindingOpen = 'FINDING_OPEN';
    case Superseded = 'SUPERSEDED';
}

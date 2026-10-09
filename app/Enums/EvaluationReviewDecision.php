<?php

namespace App\Enums;

enum EvaluationReviewDecision: string
{
    case Accepted = 'ACCEPTED';
    case ClarificationRequired = 'CLARIFICATION_REQUIRED';
    case Finding = 'FINDING';
}

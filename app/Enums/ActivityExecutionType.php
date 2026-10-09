<?php

namespace App\Enums;

enum ActivityExecutionType: string
{
    case Progress = 'PROGRESS';
    case Incident = 'INCIDENT';
    case Change = 'CHANGE';
    case Note = 'NOTE';
}

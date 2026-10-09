<?php

namespace App\Enums;

enum VerificationStage: string
{
    case System = 'SYSTEM';
    case Administration = 'ADMINISTRATION';
    case Finance = 'FINANCE';
    case Substantive = 'SUBSTANTIVE';
}

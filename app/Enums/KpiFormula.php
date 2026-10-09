<?php

namespace App\Enums;

enum KpiFormula: string
{
    case HigherIsBetter = 'HIGHER_IS_BETTER';
    case LowerIsBetter = 'LOWER_IS_BETTER';
}

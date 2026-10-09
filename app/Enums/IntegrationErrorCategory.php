<?php

namespace App\Enums;

enum IntegrationErrorCategory: string
{
    case Validation = 'VALIDATION';
    case Mapping = 'MAPPING';
    case Authorization = 'AUTHORIZATION';
    case Transient = 'TRANSIENT';
    case Permanent = 'PERMANENT';
    case Unknown = 'UNKNOWN';
}

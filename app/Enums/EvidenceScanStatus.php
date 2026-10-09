<?php

namespace App\Enums;

enum EvidenceScanStatus: string
{
    case Uploading = 'UPLOADING';
    case Registered = 'REGISTERED';
    case ScanFailed = 'SCAN_FAILED';
}

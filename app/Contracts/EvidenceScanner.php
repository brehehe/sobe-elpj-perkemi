<?php

namespace App\Contracts;

use App\Support\EvidenceScanResult;

interface EvidenceScanner
{
    public function scan(string $absolutePath, string $originalName): EvidenceScanResult;
}
